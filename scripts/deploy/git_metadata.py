"""Coleta local em repositorio de homologacao confiavel, sem escrita ou rede."""
import os
import re
import subprocess
from pathlib import Path

SHA = re.compile(r"[0-9a-f]{40}")
HEADER = re.compile(rb":([0-7]{6}) ([0-7]{6}) ([0-9a-f]{40}) ([0-9a-f]{40}) ([AMDRTCUXB])")


class MetadataError(RuntimeError):
    pass


def git(repo, *args):
    env = {"PATH": "/usr/bin:/bin", "LC_ALL": "C",
           "GIT_CONFIG_NOSYSTEM": "1", "GIT_CONFIG_GLOBAL": "/dev/null",
           "GIT_OPTIONAL_LOCKS": "0", "GIT_NO_REPLACE_OBJECTS": "1",
           "GIT_TERMINAL_PROMPT": "0", "GIT_NO_LAZY_FETCH": "1"}
    try:
        result = subprocess.run(
            ["/usr/bin/git", "-C", str(repo), "-c", "core.fsmonitor=false",
             "-c", "core.hooksPath=/dev/null", *args],
            env=env, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
            timeout=30, check=True,
        )
    except (OSError, subprocess.SubprocessError) as exc:
        raise MetadataError("consulta_git_falhou") from exc
    return result.stdout


def collect(repo, installed, target):
    """Retorna diff completo de objetos locais; nao autoriza implantacao.

    Requer checkout isolado e confiavel, sem configuracoes Git hostis.
    Chamador deve manter exclusividade e validar a origem dos SHAs.
    """
    if not all(isinstance(s, str) and SHA.fullmatch(s)
               for s in (installed, target)) or installed == target:
        raise MetadataError("sha_invalido")
    repo = Path(repo).resolve(strict=True)
    if git(repo, "rev-parse", "--show-toplevel").rstrip(b"\n") != os.fsencode(repo):
        raise MetadataError("raiz_incorreta")
    if git(repo, "rev-parse", "--is-shallow-repository").strip() != b"false":
        raise MetadataError("historico_incompleto")
    if git(repo, "rev-parse", "HEAD").strip() != installed.encode("ascii"):
        raise MetadataError("head_divergente")
    if git(repo, "status", "--porcelain=v1", "-z", "--untracked-files=all",
           "--ignore-submodules=none"):
        raise MetadataError("arvore_suja")
    for sha in (installed, target):
        if git(repo, "cat-file", "-t", sha).strip() != b"commit":
            raise MetadataError("objeto_nao_commit")
    git(repo, "merge-base", "--is-ancestor", installed, target)
    raw = git(repo, "diff-tree", "--no-commit-id", "-r", "--raw", "-z",
              "--no-renames", "--no-abbrev", "--no-ext-diff", "--no-textconv",
              "--ignore-submodules=none", installed, target, "--")
    if not raw or not raw.endswith(b"\0"):
        raise MetadataError("diff_vazio_ou_invalido")
    fields = raw[:-1].split(b"\0")
    if len(fields) % 2 or not 0 < len(fields) // 2 < 300:
        raise MetadataError("diff_incompleto_ou_excessivo")
    changes = []
    for index in range(0, len(fields), 2):
        match = HEADER.fullmatch(fields[index])
        if not match:
            raise MetadataError("cabecalho_diff_invalido")
        old_mode, new_mode, _, _, status = match.groups()
        try:
            path = fields[index + 1].decode("ascii", errors="strict")
        except UnicodeDecodeError as exc:
            raise MetadataError("caminho_nao_ascii") from exc
        changes.append(dict(path=path, status=status.decode("ascii"),
                            old_mode=old_mode.decode("ascii"),
                            new_mode=new_mode.decode("ascii")))
    if git(repo, "rev-parse", "HEAD").strip() != installed.encode("ascii") or git(
        repo, "status", "--porcelain=v1", "-z", "--untracked-files=all",
        "--ignore-submodules=none"
    ):
        raise MetadataError("arvore_alterada_durante_consulta")
    return changes
