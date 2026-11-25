"""Recuperacao entre processos em laboratorio, nao destinada a producao.

Diretorio deve ser privado, descartavel, criado pelo operador de teste.
Nao recebe codigo remoto nem acessa o Laravel, banco ou rede.
"""
import json
import os
from pathlib import Path
from release_lab import ReleaseLab, write_atomic, sync_dir


class PersistentLab(ReleaseLab):
    def __init__(self, root):
        self.root = Path(root).resolve(strict=True)
        if self.root.stat().st_uid != os.getuid() or self.root.stat().st_mode & 0o077:
            raise RuntimeError("laboratorio_nao_privado")
        self.journal = self.root / "pending.json"
        self.active = self.root / "active"
        self.env = self.root / ".env"
        self.state = self.root / "lab-state.json"
        with self.lock():
            if not self.state.exists():
                if {p.name for p in self.root.iterdir()} != {"lock"}:
                    raise RuntimeError("laboratorio_nao_vazio")
                write_atomic(self.env, b"APP_ENV=testing\n")
                self.env_hash = self.hash(self.env)
                for version in ("v1", "v2"):
                    path = self.root / version
                    path.mkdir(mode=0o700)
                    for name in ("A.php", "B.php"):
                        write_atomic(path / name,
                                     ("<?php return '%s-%s';\n" % (version, name)).encode())
                self.manifests = {v: self.manifest(v) for v in ("v1", "v2")}
                self.switch("v1")
                write_atomic(self.state, json.dumps({
                    "schema": 1, "env_hash": self.env_hash,
                    "manifests": self.manifests,
                }, sort_keys=True).encode())
                sync_dir(self.root)
            else:
                if self.state.is_symlink() or not self.state.is_file():
                    raise RuntimeError("estado_invalido")
                state = json.loads(self.state.read_text())
                if state.get("schema") != 1 or set(state) != {
                    "schema", "env_hash", "manifests"
                } or set(state["manifests"]) != {"v1", "v2"}:
                    raise RuntimeError("estado_invalido")
                self.env_hash = state["env_hash"]
                self.manifests = state["manifests"]
                # Nunca recalcular hashes esperados a partir de versoes alteradas.
                self.verify("v1")
                if self.hash(self.env) != self.env_hash:
                    raise RuntimeError("env_alterado")
                self.current()

    def __enter__(self):
        return self

    def __exit__(self, *args):
        # A vida util do diretorio pertence ao teste que o criou.
        return None
