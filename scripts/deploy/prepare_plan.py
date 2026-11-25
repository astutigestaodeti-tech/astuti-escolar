"""Fluxo de homologacao somente leitura: prepara plano, nunca implanta.

O chamador deve fornecer um checkout isolado, metadados de CI obtidos
por canal confiavel e exclusividade de acesso. Nenhum dado recebido
constitui autorizacao para escrita na instalacao.
"""
from dataclasses import dataclass

from eligibility import avaliar
from git_metadata import collect, MetadataError


@dataclass(frozen=True)
class Change:
    path: str
    status: str
    old_mode: str
    new_mode: str


@dataclass(frozen=True)
class Plan:
    installed: str
    target: str
    eligible: bool
    reason: str
    changes: tuple[Change, ...] = ()


def prepare(repo, installed, target, run, jobs):
    """Combina politica e Git; falha fechada. Nao executa codigo candidato."""
    try:
        changes = collect(repo, installed, target)
        eligible, reason = avaliar(instalado=installed, remoto=target,
                                   run=run, jobs=jobs, alteracoes=changes)
        if not eligible:
            return Plan(installed, target, False, reason)
        return Plan(installed, target, True, reason,
                    tuple(Change(**change) for change in changes))
    except (MetadataError, OSError, ValueError, TypeError):
        return Plan(installed, target, False, "metadados_locais_invalidos")
