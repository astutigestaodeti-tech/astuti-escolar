"""Laboratorio de troca atomica de versoes, sem acesso a instalacoes reais.

Usa apenas fixtures geradas pelo proprio processo. O journal demonstra
recuperacao apos interrupcao do processo, nao certifica durabilidade
contra falhas de hardware nem autoriza deploy real.
"""
import fcntl
import hashlib
import json
import os
import tempfile
from pathlib import Path


def sync_dir(path):
    fd = os.open(path, os.O_RDONLY | os.O_DIRECTORY)
    try:
        os.fsync(fd)
    finally:
        os.close(fd)


def write_atomic(path, data):
    with tempfile.NamedTemporaryFile(dir=path.parent, delete=False) as out:
        tmp = Path(out.name)
        try:
            out.write(data)
            out.flush()
            os.fsync(out.fileno())
            os.replace(tmp, path)
            sync_dir(path.parent)
        finally:
            tmp.unlink(missing_ok=True)


class ReleaseLab:
    """Fixtures isoladas. Nao recebe diretorio de instalacao externo."""

    def __init__(self):
        self.temp = tempfile.TemporaryDirectory(prefix="astuti-release-lab-")
        self.root = Path(self.temp.name)
        self.journal = self.root / "pending.json"
        self.active = self.root / "active"
        self.env = self.root / ".env"
        write_atomic(self.env, b"APP_ENV=testing\n")
        self.env_hash = self.hash(self.env)
        for version in ("v1", "v2"):
            path = self.root / version
            path.mkdir()
            for name in ("A.php", "B.php"):
                write_atomic(path / name, ("<?php return '%s-%s';\n" %
                                          (version, name)).encode())
        self.manifests = {v: self.manifest(v) for v in ("v1", "v2")}
        self.switch("v1")

    def __enter__(self):
        return self

    def __exit__(self, *args):
        self.temp.cleanup()

    @staticmethod
    def hash(path):
        return hashlib.sha256(path.read_bytes()).hexdigest()

    def manifest(self, version):
        path = self.root / version
        if path.is_symlink() or not path.is_dir():
            raise RuntimeError("versao_invalida")
        files = sorted(path.iterdir())
        if any(p.is_symlink() or not p.is_file() for p in files):
            raise RuntimeError("arquivo_invalido")
        return {p.name: self.hash(p) for p in files}

    def verify(self, version):
        if version not in ("v1", "v2") or self.manifest(version) != self.manifests[version]:
            raise RuntimeError("integridade_versao")

    def current(self):
        value = os.readlink(self.active)
        if value not in ("v1", "v2"):
            raise RuntimeError("versao_ativa_invalida")
        return value

    def switch(self, version):
        if version not in ("v1", "v2"):
            raise RuntimeError("versao_invalida")
        tmp = self.root / "active.next"
        tmp.unlink(missing_ok=True)
        tmp.symlink_to(version)
        os.replace(tmp, self.active)
        sync_dir(self.root)

    def clear(self):
        self.journal.unlink()
        sync_dir(self.root)

    def lock(self):
        fd = (self.root / "lock").open("ab")
        try:
            fcntl.flock(fd, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BaseException:
            fd.close()
            raise
        return fd

    def deploy(self, smoke, checkpoint=lambda phase: None):
        with self.lock():
            if self.journal.exists() or self.current() != "v1":
                raise RuntimeError("estado_nao_pronto")
            self.verify("v1")
            self.verify("v2")
            if self.hash(self.env) != self.env_hash:
                raise RuntimeError("env_alterado")
            write_atomic(self.journal, json.dumps({"old": "v1", "new": "v2"}).encode())
            checkpoint("journal")
            self.switch("v2")
            checkpoint("switched")
            try:
                if smoke(self.active) is not True:
                    raise RuntimeError("smoke_falhou")
                self.verify("v2")
                if self.hash(self.env) != self.env_hash:
                    raise RuntimeError("env_alterado")
            except Exception:
                self._recover()
                return "revertido"
            checkpoint("validated")
            self.clear()
            return "aplicado"

    def recover(self):
        with self.lock():
            return self._recover()

    def _recover(self):
        if not self.journal.exists():
            return "sem_pendencia"
        record = json.loads(self.journal.read_text())
        if record != {"old": "v1", "new": "v2"}:
            raise RuntimeError("journal_invalido")
        self.verify("v1")
        self.switch("v1")
        self.verify("v1")
        if self.hash(self.env) != self.env_hash:
            raise RuntimeError("env_alterado")
        self.clear()
        return "revertido"
