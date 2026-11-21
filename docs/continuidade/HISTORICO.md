# Astuti Gestao Escolar - Historico cronologico de continuidade

Este diario e **aditivo**: preservar entradas anteriores, registrar correcoes com nova data e apontar quais afirmacoes sao comprovadas. Guardar apenas fatos tecnicos do projeto, sem segredos, prints sensiveis ou transcricoes de outros assuntos pessoais.

## 20/11/2026 - Definicao do produto e ambiente inicial

**Decisoes:** criar um software comercial para Prefeituras, com ambiente de homologacao ficticio Monte Sereno; foco inicial em importar alunos atuais, escolas e matriculas com revisao humana. Registrar origem de dados, autorizacao por escola, auditoria e isolamento municipal. Adiar OCR historico, Educacenso e outros modulos para incrementos proprios.

**Ambiente preparado anteriormente:** Windows 11 Pro, Debian/WSL2, Laravel 13, PHP 8.4, Composer, Node/Vite e MariaDB inicial em bancos de desenvolvimento. Progresso tecnico evoluiu depois para autenticacao, auditoria, consultas de escolas, turmas, alunos e telas demonstrativas, conforme commits e PRs.

**Mudanca aprovada na mesma data:** migrar para PostgreSQL e adotar uma instalacao independente por Prefeitura, com um VPS exclusivo quando houver cliente. O desenvolvimento permanece no notebook por enquanto. A arquitetura do VPS e diretriz futura, nao contratacao confirmada.

**Codigo e documentos:** foram organizados PRs encadeados para incrementos funcionais e criada a branch `feature/migracao-postgresql-instalacoes-isoladas`, com guias de migracao e operacao. O processo de migracao deve preservar o MariaDB original.

## 21/11/2026 - CI da migracao e verificacao pos-reinicio

**GitHub verificado:** PR #5 ainda em rascunho contra `feature/incremento-03-cadastro-importacao`. A branch remota PostgreSQL estava no commit `a27be7b1`. As execucoes GitHub Actions `44860110321` e `44860112824` terminaram com `success`. Os PRs #1, #3 e #4 tambem permaneciam abertos, sem merge.

**Notebook, captura de terminal do usuario:** o Linux foi reaberto apos reinicio do Windows. A tentativa de executar `wsl -d Debian` de dentro do proprio Debian retornou `wsl: command not found`, comportamento esperado, sem problema no WSL. `pwd` confirmou `/home/aflora/astuti-escolar`; Debian 13, PHP 8.4.28, Composer 2.8.8, PostgreSQL 17.12 e `pdo_pgsql` estavam disponiveis; `pg_isready` confirmou conexoes em `127.0.0.1:5432`.

**Banco verificado sem alteracao:** `php artisan db:show --database=platform` mostrou `astuti_platform_dev`, usuario `astuti_app`, 11 tabelas e PostgreSQL 17.12. `php artisan migrate:status --database=platform --path=database/migrations/platform` mostrou as dez migrations centrais como `Ran`. O banco municipal nao foi verificado nesse print.

**Diferenca a conferir:** no terminal local o ultimo commit era `ec78138`, enquanto a branch remota ja tinha `a27be7b1`. Nenhum `git pull` ou migracao de dados foi comprovado nesta etapa.

**Novo pedido de Jefferson:** manter no proprio repositorio GitHub um historico tecnico para poder retomar o desenvolvimento em futuros chats. Foram definidos `AGENTS.md`, este diario e `docs/continuidade/README.md` como ponto de retomada permanente. Nao registrar automaticamente conversas pessoais ou segredos.

**Pendencia para a proxima conversa:** comparar branches com seguranca, conferir banco municipal e bancos MariaDB de origem, backups, simulacao de migracao, testes locais e eventual corte autorizado. Nao pressupor VPS contratado nem migracao concluida.

## 21/11/2026 - Notebook sincronizado com a branch PostgreSQL

**Evidencia:** nova captura do terminal Debian enviada por Jefferson. Apos `git status --short --branch` sem arquivos modificados, `git fetch origin` mostrou 0 commits locais a frente e 4 commits remotos faltantes. `git pull --ff-only origin feature/migracao-postgresql-instalacoes-isoladas` aplicou fast-forward de `ec78138` para `a27be7b1`.

**Resultado comprovado:** `git log -1 --oneline` mostrou `a27be7b1`, e `git status --short --branch` voltou a mostrar a branch `feature/migracao-postgresql-instalacoes-isoladas` acompanhando seu remoto, sem alteracoes locais pendentes. A atualizacao mudou arquivos de migrations e testes, nao os bancos de dados.

**Proximo passo:** verificar por consultas sem escrita se os bancos PostgreSQL central e municipal existem, quais tabelas estao presentes e qual o estado das migracoes; depois conferir a origem MariaDB e os backups antes de qualquer importacao.

## 21/11/2026 - Verificacao dos dois bancos PostgreSQL

**Evidencia:** captura do terminal Debian apos atualizacao para `a27be7b1`. Consulta `pg_database` confirmou a existencia de `astuti_platform_dev` e `astuti_monte_sereno_dev`. Consulta a `pg_tables` no banco municipal mostrou sete tabelas: `audit_logs`, `migrations`, `school_classes`, `school_years`, `schools`, `student_enrollments` e `students`.

**Migracoes centrais:** dez migrations anteriores marcadas como `Ran`; a migration `0001_01_01_000002_create_jobs_table`, recentemente movida para `database/migrations/platform`, aparece como `Pending`.

**Nao verificado:** se as tabelas centrais e municipais possuem registros, se os bancos MariaDB de origem ainda guardam o cenario ficticio completo, se ha backups restauraveis e se os destinos estao vazios para a transferencia. Nenhuma migration nem transferencia foi executada neste passo.

**Proximo passo seguro:** consultas somente de leitura de contagens no PostgreSQL e inventario das bases MariaDB de origem, sem exibir dados pessoais ou credenciais.

## 21/11/2026 - Contagens iniciais PostgreSQL e inventario MariaDB

**Evidencia:** captura enviada pelo usuario apos consultas somente de leitura. No PostgreSQL `astuti_platform_dev`, `municipalities`, `users` e `memberships` tinham 0 registros. No PostgreSQL `astuti_monte_sereno_dev`, `schools`, `school_years`, `school_classes`, `students` e `student_enrollments` tinham 0 registros.

**MariaDB original:** foram encontradas as bases `astuti_monte_sereno_dev`, `astuti_municipio_b_dev` e `astuti_platform_dev`. A existencia das bases nao comprova as contagens nem a integridade das tabelas de origem.

**Backups:** em `~/astuti-backups` apareceu apenas um arquivo de configuracao com nome iniciado por `env-pre-postgresql-`, tamanho 1.185 bytes. Nao foi identificado dump de banco nessa pasta. O arquivo pode conter segredos e nao deve ser enviado ao chat ou ao GitHub.

**Pendencias:** verificar as demais tabelas de destino e as contagens exatas das tabelas MariaDB exigidas pela rotina de transferencia; preparar backups de banco com restauracao ensaiada antes de qualquer comando de copia.

## Modelo para as proximas entradas

### AAAA-MM-DD - Titulo do marco

- **Pedido e objetivo:**
- **Decisoes aprovadas:**
- **Arquivos, branch, commits e PRs:**
- **Evidencias e testes executados:**
- **Banco e infraestrutura:**
- **Pendencias e riscos:**
- **Proxima acao segura:**
