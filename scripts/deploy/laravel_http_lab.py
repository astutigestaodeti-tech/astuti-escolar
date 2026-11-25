"""Ensaio HTTP real em duas copias descartaveis do Laravel, apenas no CI.

Nao copia .env, storage, .git ou caches da instalacao original.
Nao utiliza credenciais do workflow. Nao altera a arvore original.
"""
import hashlib
import os
from pathlib import Path
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.request
from contextlib import contextmanager


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def switch(root, version):
    link = root / "active.next"
    link.symlink_to(version)
    os.replace(link, root / "active")


def prepare(source, destination, key):
    destination.mkdir()
    for name in ("app", "bootstrap", "config", "routes", "resources",
                 "public", "vendor", "database"):
        shutil.copytree(source / name, destination / name,
                        ignore=shutil.ignore_patterns("*.sqlite", "*.sqlite-*",
                                                     ".env", "hot", "storage"))
    cache = destination / "bootstrap/cache"
    shutil.rmtree(cache, ignore_errors=True)
    cache.mkdir()
    for name in ("app/private", "app/public", "framework/cache/data",
                 "framework/sessions", "framework/views", "logs"):
        (destination / "storage" / name).mkdir(parents=True)
    shutil.copy2(source / "artisan", destination / "artisan")
    shutil.copy2(source / "composer.json", destination / "composer.json")
    shutil.copy2(source / "composer.lock", destination / "composer.lock")
    (destination / ".env").write_text(
        "APP_ENV=testing\nAPP_DEBUG=false\nAPP_KEY=" + key +
        "\nDB_CONNECTION=sqlite\nDB_DATABASE=:memory:\n"
        "PLATFORM_DB_HOST=127.0.0.1\nPLATFORM_DB_PORT=1\n"
        "PLATFORM_DB_DATABASE=isolated\nPLATFORM_DB_USERNAME=isolated\n"
        "CACHE_STORE=array\nSESSION_DRIVER=array\nQUEUE_CONNECTION=sync\n"
        "MAIL_MAILER=array\nLOG_CHANNEL=stderr\n"
    )


@contextmanager
def server(root):
    # Porta efemera local; eventual disputa resulta em falha do ensaio.
    with socket.socket() as sock:
        sock.bind(("127.0.0.1", 0))
        port = sock.getsockname()[1]
    release = (root / "active").resolve(strict=True)
    env = {"PATH": os.environ["PATH"], "HOME": str(root), "APP_ENV": "testing"}
    with tempfile.TemporaryFile() as log:
        process = subprocess.Popen(
            ["php", "-S", f"127.0.0.1:{port}", "-t", "public", "public/index.php"],
            cwd=release, env=env, stdout=log, stderr=log,
        )
        try:
            url = f"http://127.0.0.1:{port}/up"
            opener = urllib.request.build_opener(urllib.request.ProxyHandler({}))
            for _ in range(50):
                if process.poll() is not None:
                    raise RuntimeError("servidor_encerrado")
                try:
                    with opener.open(url, timeout=2) as response:
                        status = response.status
                        headers = response.headers
                except urllib.error.HTTPError as error:
                    status, headers = error.code, error.headers
                except (OSError, urllib.error.URLError):
                    time.sleep(0.1)
                    continue
                yield status, headers
                return
            raise RuntimeError("http_indisponivel")
        finally:
            process.terminate()
            try:
                process.wait(timeout=5)
            except subprocess.TimeoutExpired:
                process.kill()
                process.wait(timeout=5)


def run(source):
    import base64
    key = "base64:" + base64.b64encode(os.urandom(32)).decode()
    with tempfile.TemporaryDirectory(prefix="astuti-http-ci-") as path:
        root = Path(path)
        prepare(source, root / "v1", key)
        prepare(source, root / "v2", key)
        old_env = sha(root / "v1/.env")
        new_env = sha(root / "v2/.env")
        switch(root, "v1")
        with server(root) as (status, headers):
            assert status == 200, f"v1: {status}"
            assert headers.get("X-Content-Type-Options") == "nosniff"
        # Falha exclusiva da copia candidata, sem tocar no repositorio.
        (root / "v2/public/index.php").write_text(
            "<?php http_response_code(503); echo 'fixture failure';"
        )
        switch(root, "v2")
        with server(root) as (status, _):
            assert status == 503, f"falha esperada: {status}"
        switch(root, "v1")
        with server(root) as (status, headers):
            assert status == 200, f"rollback: {status}"
            assert headers.get("X-Frame-Options") == "DENY"
        assert sha(root / "v1/.env") == old_env
        assert sha(root / "v2/.env") == new_env
        # Agora uma candidata valida e uma nova troca.
        shutil.copy2(source / "public/index.php", root / "v2/public/index.php")
        switch(root, "v2")
        with server(root) as (status, headers):
            assert status == 200, f"v2: {status}"
            assert headers.get("X-Content-Type-Options") == "nosniff"
        print("HTTP Laravel: 200 -> 503 -> rollback 200 -> nova versao 200")


if __name__ == "__main__":
    if os.environ.get("GITHUB_ACTIONS") != "true":
        raise SystemExit("Ensaio exclusivo do CI descartavel")
    run(Path(__file__).resolve().parents[2])
