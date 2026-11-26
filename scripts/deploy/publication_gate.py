"""Gate de publicacao: metadados selados, workflow fixado e CI atualizado."""
from pathlib import Path
import os

from git_metadata import git
from operational_plan import operational_plan
from publisher import trusted

METADATA = Path("/srv/astuti-releases/publication-metadata")
WORKFLOW = Path("/etc/astuti-deploy/workflow-autorizado.yml")
WORKFLOW_PATH = ".github/workflows/testes.yml"


def verify_metadata(repo):
    # A arvore inteira deve ser imutavel para os usuarios sem privilegios.
    for path in (repo, *repo.parents):
        trusted(path, 0, True)
    trusted(repo / ".git", 0, True)
    for parent, dirs, files in os.walk(repo, followlinks=False):
        for name in dirs:
            trusted(Path(parent) / name, 0, True)
        for name in files:
            trusted(Path(parent) / name, 0)
    # Nao admitir repositorios que deleguem objetos a uma arvore externa.
    for name in ("objects/info/alternates", "objects/info/http-alternates",
                 "commondir", "shallow"):
        if (repo / ".git" / name).exists():
            raise RuntimeError("metadados_externos")


def verify_workflow(repo, data, workflow):
    for path in workflow.parents:
        trusted(path, 0, True)
    trusted(workflow, 0)
    expected = workflow.read_bytes()
    if not expected or len(expected) > 1024 * 1024:
        raise RuntimeError("workflow_autorizado_invalido")
    for sha in (data["old"], data["new"]):
        actual = git(repo, "show", sha + ":" + WORKFLOW_PATH)
        if actual != expected:
            raise RuntimeError("workflow_divergente")


def check(data, planner=operational_plan, repo=METADATA, workflow=WORKFLOW):
    verify_metadata(repo)
    verify_workflow(repo, data, workflow)
    result = planner(repo, data["old"])
    if (result.get("eligible") is not True
            or result.get("installed") != data["old"]
            or result.get("target") != data["new"]
            or result.get("write_authorized") is not False):
        raise RuntimeError("planejamento_nao_elegivel")
    return result
