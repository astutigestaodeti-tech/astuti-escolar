"""Publicador de releases seladas. Nao executa Git, PHP ou comandos privilegiados.

Integracao prevista: releases e autorizacao somente root; estado exclusivo do
usuario publicador; Nginx separado usa o link active. Nao instalar no site
existente antes de homologar compartilhamento de storage e configuracao.
"""
import fcntl
import hashlib
import json
import os
from pathlib import Path
import re
import stat
import urllib.request

from release_lab import sync_dir, write_atomic

SHA = re.compile(r"[0-9a-f]{40}")
DIGEST = re.compile(r"[0-9a-f]{64}")
ROOT = Path("/srv/astuti-releases/published")
STATE = Path("/srv/astuti-releases/publication-state")
AUTH = Path("/etc/astuti-deploy/publication.json")


def trusted(path, uid, directory=False):
    s = path.lstat()
    kind = stat.S_ISDIR if directory else stat.S_ISREG
    if (not kind(s.st_mode) or s.st_uid != uid or s.st_mode & 0o022
            or (not directory and s.st_nlink != 1)):
        raise RuntimeError("permissao_ou_tipo_inseguro")


def manifest(root, uid):
    trusted(root, uid, True)
    result = {}
    for parent, dirs, files in os.walk(root, followlinks=False):
        for name in sorted(dirs):
            trusted(Path(parent) / name, uid, True)
        for name in sorted(files):
            path = Path(parent) / name
            trusted(path, uid)
            with path.open("rb") as handle:
                result[path.relative_to(root).as_posix()] = hashlib.file_digest(
                    handle, "sha256").hexdigest()
    if not result:
        raise RuntimeError("release_vazia")
    return result


def digest_manifest(root, uid):
    return hashlib.sha256(json.dumps(manifest(root, uid), sort_keys=True,
                                     separators=(",", ":")).encode()).hexdigest()


class Publisher:
    def __init__(self, releases, state, authorization, owner=0):
        self.releases = Path(releases)
        self.state = Path(state)
        self.authorization = Path(authorization)
        self.owner = owner
        trusted(self.releases, owner, True)
        trusted(self.authorization.parent, owner, True)
        trusted(self.state, os.getuid(), True)
        self.active = self.state / "active"
        self.pending = self.state / "pending.json"

    def lock(self):
        fd = os.open(self.state / "lock",
                     os.O_CREAT | os.O_RDWR | os.O_NOFOLLOW, 0o600)
        handle = os.fdopen(fd, "r+b")
        try:
            fcntl.flock(handle, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BaseException:
            handle.close()
            raise
        return handle

    def authorization_data(self):
        trusted(self.authorization, self.owner)
        data = json.loads(self.authorization.read_text())
        if set(data) != {"enabled", "environment", "old", "new", "digests"}:
            raise RuntimeError("autorizacao_invalida")
        if data["enabled"] is not True or data["environment"] != "homologacao":
            raise RuntimeError("publicacao_desabilitada")
        old, new = data["old"], data["new"]
        if not all(isinstance(x, str) and SHA.fullmatch(x) for x in (old, new)) or old == new:
            raise RuntimeError("sha_invalido")
        if set(data["digests"]) != {old, new} or not all(
            isinstance(d, str) and DIGEST.fullmatch(d) for d in data["digests"].values()
        ):
            raise RuntimeError("manifestos_invalidos")
        return data

    def verify(self, sha, data):
        if sha not in data["digests"] or digest_manifest(
            self.releases / sha, self.owner
        ) != data["digests"][sha]:
            raise RuntimeError("integridade_release")

    def current(self):
        raw = os.readlink(self.active)
        path = Path(raw)
        if path.parent != self.releases or not SHA.fullmatch(path.name):
            raise RuntimeError("link_ativo_invalido")
        return path.name

    def switch(self, sha):
        if not SHA.fullmatch(sha):
            raise RuntimeError("sha_invalido")
        temporary = self.state / "active.next"
        temporary.unlink(missing_ok=True)
        temporary.symlink_to(self.releases / sha)
        os.replace(temporary, self.active)
        sync_dir(self.state)

    def recover_locked(self, smoke):
        if not self.pending.exists():
            return "sem_pendencia"
        trusted(self.pending, os.getuid())
        record = json.loads(self.pending.read_text())
        data = self.authorization_data()
        if record != data:
            raise RuntimeError("autorizacao_alterada_durante_transacao")
        if self.current() not in (data["old"], data["new"]):
            raise RuntimeError("estado_ativo_invalido")
        self.verify(data["old"], data)
        self.switch(data["old"])
        if smoke(data["old"]) is not True:
            raise RuntimeError("recuperacao_http_falhou")
        self.pending.unlink()
        sync_dir(self.state)
        return "revertido"

    def recover(self, smoke):
        with self.lock():
            return self.recover_locked(smoke)

    def publish(self, smoke, checkpoint=lambda _: None):
        with self.lock():
            if self.pending.exists() or self.pending.is_symlink():
                raise RuntimeError("recuperacao_pendente")
            data = self.authorization_data()
            if self.current() != data["old"]:
                raise RuntimeError("versao_anterior_divergente")
            for sha in (data["old"], data["new"]):
                self.verify(sha, data)
            if smoke(data["old"]) is not True:
                raise RuntimeError("versao_anterior_indisponivel")
            write_atomic(self.pending, json.dumps(data, sort_keys=True).encode())
            checkpoint("journal")
            self.switch(data["new"])
            checkpoint("switched")
            try:
                if smoke(data["new"]) is not True:
                    raise RuntimeError("http_falhou")
                self.verify(data["new"], data)
                if self.authorization_data() != data:
                    raise RuntimeError("autorizacao_alterada")
            except Exception:
                return self.recover_locked(smoke)
            checkpoint("validated")
            self.pending.unlink()
            sync_dir(self.state)
            return "aplicado"


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        return None


def local_smoke(sha):
    # O virtual host exclusivo deve servir o marcador diretamente da release.
    opener = urllib.request.build_opener(urllib.request.ProxyHandler({}), NoRedirect())
    try:
        for route in ("/release-id.txt", "/up", "/release-id.txt"):
            request = urllib.request.Request("http://127.0.0.1:18084" + route,
                                             headers={"Cache-Control": "no-cache"})
            with opener.open(request, timeout=5) as response:
                if response.status != 200:
                    return False
                if route == "/release-id.txt" and response.read(128).strip() != sha.encode():
                    return False
        return True
    except OSError:
        return False


def main():
    import argparse
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("action", choices=("publish", "recover"))
    args = parser.parse_args()
    if os.getuid() == 0:
        raise SystemExit("publicador_nao_pode_ser_root")
    publisher = Publisher(ROOT, STATE, AUTH)
    result = getattr(publisher, args.action)(local_smoke)
    print(result)
    return 2 if args.action == "publish" and result != "aplicado" else 0


if __name__ == "__main__":
    raise SystemExit(main())
