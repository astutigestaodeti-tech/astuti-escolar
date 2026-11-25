"""Politica de elegibilidade: somente leitura, sem executar Git ou implantar."""
import re

WORKFLOW_ID = 425304833
JOBS = {"Testes PHP e interface", "PostgreSQL 17 como usuario web restrito"}
SHA = re.compile(r"[0-9a-f]{40}")
PATH = re.compile(r"(?:app|tests)/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_-]+\.php")


def avaliar(instalado, remoto, run, jobs, alteracoes):
    """Recebe metadados ja coletados. Nao constitui autorizacao de escrita.

    alteracoes: registros locais completos com path, status, old_mode,
    new_mode. Modos devem ser strings do Git (100644 ou 100755).
    """
    if not all(isinstance(x, str) and SHA.fullmatch(x)
               for x in (instalado, remoto)):
        return False, "sha_invalido"
    if instalado == remoto:
        return False, "sem_atualizacao"
    if not isinstance(run, dict) or (
        run.get("head_sha") != remoto
        or run.get("head_branch") != "main"
        or run.get("event") != "push"
        or run.get("workflow_id") != WORKFLOW_ID
        or run.get("status") != "completed"
        or run.get("conclusion") != "success"
    ):
        return False, "ci_invalido"
    if not isinstance(jobs, list) or len(jobs) != len(JOBS):
        return False, "jobs_incompletos"
    if any(not isinstance(j, dict) or j.get("status") != "completed"
           or j.get("conclusion") != "success"
           or not isinstance(j.get("name"), str) for j in jobs):
        return False, "job_invalido"
    if {j["name"] for j in jobs} != JOBS:
        return False, "jobs_incorretos"
    if not isinstance(alteracoes, list) or not 0 < len(alteracoes) < 300:
        return False, "alteracoes_invalidas"
    vistos = set()
    for item in alteracoes:
        if not isinstance(item, dict):
            return False, "arquivo_invalido"
        path = item.get("path")
        if not isinstance(path, str) or not PATH.fullmatch(path) or path in vistos:
            return False, "caminho_restrito"
        vistos.add(path)
        status = item.get("status")
        antigo = item.get("old_mode")
        novo = item.get("new_mode")
        if novo != "100644" or (
            status == "A" and antigo != "000000"
        ) or (
            status == "M" and antigo != novo
        ) or status not in ("A", "M"):
            return False, "tipo_restrito"
    return True, "candidato_sem_autorizacao_de_escrita"
