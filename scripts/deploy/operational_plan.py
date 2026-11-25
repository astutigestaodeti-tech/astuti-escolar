"""Planejador operacional de leitura. Executar a partir de codigo confiavel,
fora do checkout candidato. Sem credenciais, sem publicacao ou migracao.
O operador fornece o SHA instalado e o checkout limpo desse SHA.
"""
import argparse
from dataclasses import asdict
import json
import re
import sys
import urllib.request
from urllib.parse import urlencode

from eligibility import SHA, WORKFLOW_ID
from git_metadata import git
from prepare_plan import prepare

BASE = "https://api.github.com/repos/astutigestaodeti-tech/astuti-escolar"
LIMIT = 2 * 1024 * 1024


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def read_api(path):
    opener = urllib.request.build_opener(
        urllib.request.ProxyHandler({}), NoRedirect()
    )
    request = urllib.request.Request(BASE + path, headers={
        "Accept": "application/vnd.github+json",
        "X-GitHub-Api-Version": "2022-11-28",
        "User-Agent": "astuti-release-planner",
    })
    with opener.open(request, timeout=15) as response:
        raw = response.read(LIMIT + 1)
    if len(raw) > LIMIT:
        raise ValueError("resposta_excessiva")
    return json.loads(raw)


def snapshot(read=read_api):
    branch = read("/branches/main")
    target = branch["commit"]["sha"]
    if not SHA.fullmatch(target) or branch.get("protected") is not True:
        raise ValueError("main_nao_protegida_ou_sha_invalido")
    query = urlencode({"branch": "main", "event": "push",
                       "head_sha": target, "per_page": 100})
    result = read(f"/actions/workflows/{WORKFLOW_ID}/runs?{query}")
    runs = result["workflow_runs"]
    if result["total_count"] != len(runs) or not 0 < len(runs) <= 100:
        raise ValueError("execucoes_incompletas")
    if any(r.get("head_sha") != target or r.get("head_branch") != "main"
           or r.get("event") != "push" or r.get("workflow_id") != WORKFLOW_ID
           or type(r.get("id")) is not int or r["id"] <= 0
           for r in runs):
        raise ValueError("execucao_invalida")
    run = max(runs, key=lambda r: r["id"])
    attempt = run.get("run_attempt")
    if type(attempt) is not int or attempt < 1:
        raise ValueError("tentativa_invalida")
    if run.get("status") != "completed" or run.get("conclusion") != "success":
        raise ValueError("ci_nao_aprovado")
    result = read(f"/actions/runs/{run['id']}/attempts/{attempt}/jobs?per_page=100")
    jobs = result["jobs"]
    if result["total_count"] != len(jobs) or len(jobs) != 2:
        raise ValueError("jobs_incompletos")
    if any(j.get("run_id") != run["id"] or j.get("run_attempt") != attempt
           or j.get("head_sha") != target for j in jobs):
        raise ValueError("job_divergente")
    return target, run, jobs


def operational_plan(repo, installed, read=read_api):
    if not SHA.fullmatch(installed):
        raise ValueError("sha_instalado_invalido")
    target, run, jobs = snapshot(read)
    if target == installed:
        return {"eligible": False, "reason": "sem_atualizacao", "target": target}
    # O checkout deve ter sido abastecido pelo operador, nunca buscar ou
    # executar codigo candidato durante a consulta.
    if git(repo, "rev-parse", "refs/remotes/origin/main").strip() != target.encode():
        raise ValueError("referencia_local_desatualizada")
    plan = prepare(repo, installed, target, run, jobs)
    # Detecta mudanca do main ou nova tentativa do CI durante o planejamento.
    after, latest, final_jobs = snapshot(read)
    if (after, latest, final_jobs) != (target, run, jobs):
        raise ValueError("github_alterado_durante_consulta")
    result = asdict(plan)
    result["run_id"] = run["id"]
    result["run_attempt"] = run["run_attempt"]
    result["write_authorized"] = False
    return result


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--repo", required=True)
    parser.add_argument("--installed", required=True)
    args = parser.parse_args()
    try:
        result = operational_plan(args.repo, args.installed)
        print(json.dumps(result, ensure_ascii=True, sort_keys=True))
        return 0 if result["eligible"] else 2
    except Exception as exc:
        # Nao imprimir respostas remotas ou credenciais.
        print(json.dumps({"eligible": False, "write_authorized": False,
                          "reason": "consulta_bloqueada",
                          "error_type": type(exc).__name__}))
        return 2


if __name__ == "__main__":
    sys.exit(main())
