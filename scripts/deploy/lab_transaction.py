"""Ensaio de transacao de arquivos em laboratorio criado pelo proprio processo.

Nao aceita caminhos externos, nao executa PHP, nao acessa a VPS ou rede.
O smoke test e uma funcao de teste; nao representa autorizacao de deploy.
"""
import hashlib
import os
import tempfile
from pathlib import Path
from contextlib import contextmanager
import fcntl


class LabError(RuntimeError):
    pass


def digest(data):
    return hashlib.sha256(data).hexdigest()


@contextmanager
def laboratory():
    with tempfile.TemporaryDirectory(prefix="astuti-deploy-lab-") as root:
        base = Path(root)
        app = base / "app"
        app.mkdir()
        (app / "Example.php").write_bytes(b"<?php return 1;\n")
        (base / ".env").write_bytes(b"APP_ENV=testing\n")
        yield Lab(base)


class Lab:
    def __init__(self, base):
        self.base = base

    def exercise(self, candidate, smoke):
        """Somente fixture fixa. Simula backup, troca atomica e rollback."""
        if not isinstance(candidate, bytes) or len(candidate) > 65536:
            raise LabError("fixture_invalida")
        with (self.base / "lock").open("ab") as lock:
            try:
                fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
            except BlockingIOError as exc:
                raise LabError("concorrencia") from exc
            return self._exercise(candidate, smoke)

    def _exercise(self, candidate, smoke):
        file = self.base / "app/Example.php"
        env = self.base / ".env"
        old = file.read_bytes()
        env_hash = digest(env.read_bytes())
        backup = self.base / "backup"
        backup.mkdir(exist_ok=False)
        copy = backup / "Example.php"
        copy.write_bytes(old)
        manifest = backup / "SHA256"
        manifest.write_text(digest(old), encoding="ascii")
        if digest(copy.read_bytes()) != manifest.read_text(encoding="ascii"):
            raise LabError("backup_invalido")

        def replace(data):
            with tempfile.NamedTemporaryFile(dir=file.parent, delete=False) as out:
                path = Path(out.name)
                try:
                    out.write(data)
                    out.flush()
                    os.fsync(out.fileno())
                    os.replace(path, file)
                finally:
                    path.unlink(missing_ok=True)

        try:
            replace(candidate)
            if digest(file.read_bytes()) != digest(candidate):
                raise LabError("integridade")
            if smoke(file.read_bytes()) is not True:
                raise LabError("smoke_falhou")
            if digest(env.read_bytes()) != env_hash:
                raise LabError("env_alterado")
        except Exception:
            if digest(copy.read_bytes()) != digest(old):
                raise LabError("backup_corrompido")
            replace(copy.read_bytes())
            if digest(file.read_bytes()) != digest(old):
                raise LabError("rollback_falhou")
            if digest(env.read_bytes()) != env_hash:
                raise LabError("env_alterado")
            return "revertido"
        return "aplicado"
