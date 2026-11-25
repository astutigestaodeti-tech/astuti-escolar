# Otimização do CI

Decisão de 24/11/2026, issue #196, acompanhamento #193.

## Linha de base

Execução [45527099404](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/45527099404), head do PR #195 `2ff1649505d8061a94678dcd1f6b2f1da3203882`, três jobs aprovados.

| Job | Duração |
| --- | ---: |
| Testes PHP e interface | 11 min 20 s |
| Integração MariaDB para PostgreSQL | 1 min 27 s |
| PostgreSQL 17 como usuario web restrito | 38 s |

As duas rodadas integrais de PHP juntas consumiram 603 segundos; as duas rodadas de transferência MariaDB consumiram 21 e 20 segundos. Os jobs são paralelos. A soma das durações é 13 min 25 s de trabalho dos jobs, não o tempo de espera e nem a cobrança efetiva do GitHub. Esta é uma amostra, não uma mediana.

## Primeira etapa

- Cache dos downloads Composer por sistema operacional, PHP 8.4 e hash de composer.lock, nos três jobs; continuar instalando a partir do lockfile. Não guardar vendor, .env, bancos ou arquivos da aplicação no cache.
- Separar os logs das duas rodadas integrais, usar o perfil de testes e medir o ganho em execuções com cache frio e quente.
- Preservar os três jobs, seus nomes, todas as verificações existentes e a repetição da suíte.
- Verificar a saída do perfil e o tempo registrado nas etapas; coletar os testes e asserções de cada rodada. Não supor melhoria antes de medir.

## Segunda etapa: MariaDB legado

A operação oficial é exclusivamente PostgreSQL 17+, conforme vps-prefeitura.md. O comando de transferência MariaDB é específico da base fictícia histórica, conforme migracao-mariadb-postgresql.md. Não confundir com os importadores do produto.

A consulta da branch main em 24/11/2026 confirmou três checks obrigatórios, inclusive Integração MariaDB para PostgreSQL. O conector não possui acesso administrativo para consultar os detalhes da proteção. Não remover nem simular o check obrigatório, nem alterar regras de proteção sem autorização específica.

Após confirmar que os ambientes antigos não dependem mais dessa ferramenta, preparar workflow manual para a migração histórica, validar a execução manual e combinar com o administrador a retirada apenas do check legado da proteção. Manter os checks PHP e PostgreSQL obrigatórios. Não apagar a origem ou backups.

## Etapas posteriores

Medir e comparar múltiplas execuções equivalentes. Avaliar o custo de migrations, limpeza de fixtures e hashing. Avaliar grupos paralelos com bancos descartáveis independentes e nomes terminados em _test. A segunda rodada comprova repetibilidade e não será removida sem teste equivalente e revisão documentada. Não retirar testes de autorização por escola ou isolamento A/B/A. As mudanças serão avaliadas em PRs separados.

Não há percentual de redução comprovado nesta etapa. A validação depende do CI do PR e de novas medições.
