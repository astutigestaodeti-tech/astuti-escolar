"""Gate de publicacao: revalida CI e diff imediatamente antes da transacao.

Checkout de metadados dedicado, previamente preparado e selado por root.
Nao executar o publicador a partir do checkout candidato.
"""
from pathlib import Path

from operational_plan import operational_plan
from publisher import trusted

METADATA = Path("/srv/astuti-releases/publication-metadata")


def check(data, planner=operational_plan, repo=METADATA):
    trusted(repo, 0, True)
    trusted(repo / ".git", 0, True)
    result = planner(repo, data["old"])
    if (result.get("eligible") is not True
            or result.get("installed") != data["old"]
            or result.get("target") != data["new"]
            or result.get("write_authorized") is not False):
        raise RuntimeError("planejamento_nao_elegivel")
    return result
