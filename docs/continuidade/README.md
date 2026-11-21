# Astuti Gestao Escolar - Ponto de retomada

**Ultima revisao:** 21/11/2026  
**Responsavel:** Jefferson Astuti  
**Repositorio oficial:** `astutigestaodeti-tech/astuti-escolar` (privado)  
**Registro cronologico:** [HISTORICO.md](HISTORICO.md)

> Este documento serve para continuar o desenvolvimento em qualquer novo chat. O estado descrito e datado e deve ser confrontado com o GitHub e o notebook antes de qualquer mudanca.

## 1. Mensagem pronta para novo chat

> Quero continuar o projeto **Astuti Gestao Escolar**. Acesse o repositorio privado `astutigestaodeti-tech/astuti-escolar`, leia `AGENTS.md`, `docs/continuidade/README.md` e `docs/continuidade/HISTORICO.md`, alem dos planos relacionados a etapa atual. Confira branches, PRs, commits, testes e o estado local do meu notebook antes de propor mudancas. Nao repita etapas comprovadamente concluidas, nao exponha credenciais, nao use dados reais de alunos e nao execute operacoes destrutivas. Depois de cada marco, atualize esses documentos no GitHub com evidencias e o proximo passo. Responda em portugues brasileiro e nunca utilize travessao longo.

## 2. Objetivo do produto

O Astuti Gestao Escolar e um software comercial para redes municipais publicas de ensino, com interface simples e acessivel. Seu principal diferencial e reduzir redigitacao na implantacao: importar planilhas e documentos, preservar originais, identificar inconsistencias e duplicatas e exigir **conferencia e aprovacao humana autorizada** antes de gravar dados oficiais.

Ordem funcional: alunos atuais, responsaveis, escolas, turmas e matriculas; depois documentos atuais; em seguida acervo historico, vida escolar, Educacenso e modulos complementares. OCR de PDF/imagens e reconstrucao de historicos sao etapas posteriores. Nunca inventar dados escolares ou gerar segunda via oficial sem validacao institucional.

**Homologacao ficticia:** municipio Monte Sereno v2, com 3 escolas, 115 alunos sinteticos e ano letivo 2027, alem de municipio ficticio de contraste para testes de isolamento. Nenhum dado real de aluno pode entrar no ambiente demonstrativo.

## 3. Arquitetura aprovada em 20/11/2026

- Uma unica base de codigo em PHP 8.4.1+ e Laravel 13, com Blade, Tailwind/Vite, PHPUnit e GitHub Actions.
- **PostgreSQL** como banco do produto em desenvolvimento e nas novas instalacoes. O MariaDB anterior e apenas fonte de migracao/retorno, nao dependencia de producao.
- **Um VPS independente por Prefeitura contratante**, cada um com sua aplicacao, dominio, arquivos, backups, contas, banco `platform` e banco `tenant` PostgreSQL. Nao ha compartilhamento de dados ou sessoes entre Prefeituras.
- Desenvolvimento inicial no notebook Windows 11 Pro com Debian 13 no WSL2. **Nenhum VPS contratado ate a confirmacao do usuario em 21/11/2026.** Infraestrutura de producao sera decidida futuramente.
- Desenvolvimento no GitHub privado empresarial. Segredos e arquivos reais ficam fora do Git.
- Protecao de dados de menores, autorizacao por municipio/escola, 2FA para escritas sensiveis, trilhas de auditoria, arquivos privados, backups externos e testes de restauracao antes de qualquer piloto real.

## 4. Estado comprovado no GitHub em 21/11/2026

| Item | Estado verificado |
| --- | --- |
| Repositorio | `astutigestaodeti-tech/astuti-escolar`, privado |
| `main` antes deste registro | `edf52e89` - base antiga do projeto, sem receber ainda os incrementos em PR |
| Branch de trabalho mais recente | `feature/migracao-postgresql-instalacoes-isoladas` |
| Cabeca da branch remota | `a27be7b1` (`refactor(pgsql): retirar migration operacional do caminho raiz`) |
| PR #1 | `feature/incremento-01-saude` para `main`, rascunho/aberto |
| PR #3 | Incremento 02 para Incremento 01, rascunho/aberto |
| PR #4 | Incremento 03 para Incremento 02, rascunho/aberto |
| PR #5 | PostgreSQL e VPS por Prefeitura para Incremento 03, rascunho/aberto |
| CI na branch PostgreSQL | Duas execucoes Actions sobre `a27be7b1` com conclusao `success` em 21/11/2026 |

O sucesso do GitHub Actions comprova as verificacoes automatizadas executadas por aquele workflow, **nao** a transferencia concluida dos bancos do notebook nem a homologacao do sistema para Prefeituras.

Documentos tecnicos atuais, localizados na branch do PR #5:

- `docs/superpowers/specs/2026-11-20-postgresql-instalacoes-isoladas-design.md`
- `docs/superpowers/plans/2026-11-20-postgresql-instalacoes-isoladas.md`
- `docs/operacao/instalacao-postgresql-debian.md`
- `docs/operacao/migracao-mariadb-postgresql.md`
- `docs/operacao/vps-prefeitura.md`

**Atencao:** titulos ou campos antigos desses documentos podem dizer `proposta` ou `aguardando aprovacao`; Jefferson aprovou posteriormente a migracao PostgreSQL e a arquitetura por Prefeitura. Reavaliar futuras mudancas estruturais antes de implementa-las.

## 5. Estado comprovado no notebook em 21/11/2026

Evidencia: capturas do terminal Debian enviadas pelo usuario em conversa de continuidade.

| Verificacao | Resultado visto |
| --- | --- |
| Diretorio do projeto | `/home/aflora/astuti-escolar` |
| Sistema | Debian GNU/Linux 13 (trixie) em WSL2 no Windows 11 Pro |
| PHP e extensoes | 8.4.28; `pdo_pgsql`, `pgsql` e `pdo_mysql` presentes |
| Composer | 2.8.8 |
| PostgreSQL | 17.12; `127.0.0.1:5432` aceitando conexoes; servico ativo |
| Nginx | Ausente, o que nao impede desenvolvimento local com `artisan serve` |
| Branch local no ultimo print | `feature/migracao-postgresql-instalacoes-isoladas`, HEAD `a27be7b1`, arvore limpa |
| PostgreSQL Laravel | Conexao `platform`, banco `astuti_platform_dev`, host `127.0.0.1`, porta `5432`, usuario `astuti_app` |
| Banco central | 11 tabelas exibidas por `php artisan db:show` |
| Migracoes centrais | 10 migracoes especificas de plataforma exibidas como `Ran`; nova migration de `jobs` em `Pending` apos o pull |
| Banco municipal PostgreSQL | `astuti_monte_sereno_dev` confirmado, com 7 tabelas de estrutura escolar, auditoria e migrations |
| Contagens PostgreSQL verificadas | Central: `municipalities`, `users`, `memberships` com 0; municipal: escolas, anos, turmas, alunos e matriculas com 0 |
| MariaDB de origem | 3 bases `astuti_*_dev` confirmadas; 12 tabelas de origem comparadas as restauracoes, com contagens iguais (captura 70) |
| Bancos restaurados MariaDB | Capturas 69 e 70: `astuti_restore_platform_20261120_235929_30385` e `astuti_restore_monte_20261120_235929_30385`; 12 de 12 tabelas com contagens iguais as origens |
| Dry-run atual PostgreSQL | Captura 70: `php artisan astuti:migrar-dados-postgresql` sem `--executar` foi aprovado no commit `a27be7b1`; nenhuma gravacao |
| Backups MariaDB | Captura 68: dois dumps `.sql.gz` localizados em `~/astuti-backups/mariadb-20261120-234757/`, tamanhos 4,7 KB e 17 KB, ambos aprovados em `gzip -t`; sem alteracao |

**Sincronizacao concluida em 21/11/2026:** o notebook estava no commit `ec78138`, quatro commits atras do remoto. Jefferson executou `git pull --ff-only` e comprovou HEAD `a27be7b1`, branch correta e arvore limpa. O historico detalhado esta em `HISTORICO.md`.

As capturas confirmam a estrutura das sete tabelas municipais PostgreSQL, os dois dumps GZIP, a existencia das restauracoes MariaDB e a igualdade de contagens em 12 tabelas. **A simulacao de migracao sem `--executar` no commit `a27be7b1` foi aprovada em 21/11/2026, sem gravacoes.** Nao foi comprovada a copia efetiva para o PostgreSQL, a validacao linha a linha, o login ou os testes locais completos. **Nao executar importacao efetiva, `migrate:fresh`, `db:wipe` ou seed de demonstracao antes da revisao tecnica e autorizacao.**

## 6. Proximo passo seguro

1. **Concluido:** branch local `feature/migracao-postgresql-instalacoes-isoladas` sincronizada em `a27be7b1`; bancos PostgreSQL central e municipal criados, com tabelas de negocio principais vazias.
2. **Concluido em capturas 68-70:** dois dumps MariaDB GZIP integros, restauracoes `astuti_restore_platform_20261120_235929_30385` e `astuti_restore_monte_20261120_235929_30385` preservadas; contagens iguais nas 12 tabelas comparadas. Nunca recriar, apagar ou sobrescrever origens e backups sem motivo e autorizacao.
3. **Concluido em captura 70:** simulacao `php artisan astuti:migrar-dados-postgresql` sem `--executar` aprovada no codigo `a27be7b1`, com 1 municipio, 1 usuario, 3 escolas, 115 alunos, 3 turmas, 115 matriculas e 237 auditorias municipais. Nenhum dado foi gravado.
4. **Revisao tecnica pre-corte em 21/11/2026:** comando restringe ambiente, confere conteudo e hashes, mas commits nos dois bancos PG nao sao atomicamente conjuntos. CI do PR #5 passou, porem nao executa transferencia real MariaDB->PG. Nao assumir copia concluida nem prontidao para producao.
5. **Proximo comando:** somente consultas para checar fisicamente `jobs`, `job_batches` e `failed_jobs` no PostgreSQL central, alem do `migrate:status --database=platform --path=database/migrations/platform`. A migration de jobs apareceu `Pending` no ultimo print; antes de executar qualquer migration, determinar se essas tabelas ja existem.
6. **Antes da copia real:** preparar backup verificavel do PG central, resolver migration jobs pendente de forma supervisionada, adicionar testes de integracao e ensaiar copia/recuperacao em destinos PostgreSQL descartaveis, inclusive falha entre commits. Conferir referencias entre bancos, FKs, auditoria, login, hash e telas.
7. **Nao executar `--executar` nos destinos principais nem mesclar PR #5 nesta etapa.** Fazer nova revisao e obter autorizacao especifica depois dos testes. Manter PRs anteriores em rascunho ate os criterios de aceitacao.

## 7. Como manter este registro util

A cada marco ou fim de sessao relevante:

1. Acrescentar ao `HISTORICO.md` uma entrada com data, objetivo, decisoes, evidencias, commits/PRs, testes, resultado, pendencias e proximo comando seguro.
2. Atualizar neste arquivo apenas a fotografia atual: codigo, banco, ambiente e proximo passo, distinguindo fatos observados de tarefas previstas.
3. Fazer commit com mensagem clara, preferencialmente `docs(continuidade): atualizar estado em AAAA-MM-DD`.
4. Revisar se nenhum segredo, dado escolar real, dump ou informacao pessoal de outros assuntos foi incluido.

**Limite importante:** o ChatGPT nao arquiva automaticamente todas as mensagens de todos os chats no GitHub. Este registro tecnicamente relevante deve ser atualizado pelo assistente quando houver acesso autorizado ao repositorio ou por um processo de sincronizacao que venha a ser implementado e autorizado. Ele nao substitui o historico integral de conversas.
