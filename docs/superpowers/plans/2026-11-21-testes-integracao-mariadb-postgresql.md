# Testes reais MariaDB -> PostgreSQL - Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Comprovar em CI, com MariaDB e PostgreSQL efemeros, a transferencia de Monte Sereno ficticio, incluindo preservacao da origem, integridade e recuperacao de falhas.

**Architecture:** Manter o comando publico `astuti:migrar-dados-postgresql` restrito aos bancos locais ficticios `*_dev`. Separar a logica de copia de suas protecoes de CLI para que o mesmo mecanismo seja exercitado por testes contra conexoes explicitas `*_test` em servicos efemeros do GitHub. A recuperacao de commit parcial sera testada sem deletar bancos do notebook.

**Tech Stack:** Laravel 13, PHP 8.4.1+, PHPUnit, PostgreSQL 17, MariaDB 11.8, PDO_PGSQL, PDO_MYSQL, GitHub Actions, Pint.

**Spec:** `docs/superpowers/specs/2026-11-21-testes-integracao-mariadb-postgresql-design.md` (complementa o plano aprovado de 20/11/2026, Task 4).

## Global Constraints

- Todos os bancos usados por testes de integracao terminam em `_test` e existem somente nos servicos efemeros CI; nunca conectar nos `*_dev` do notebook.
- O comando de producao local continua exigindo `APP_ENV=local`, nomes exatos `astuti_platform_dev`/`astuti_monte_sereno_dev`, loopback e `--executar --confirmar=MONTE-SERENO-DEV` para gravacao.
- Fixtures sao sinteticas: 1 municipio `monte-sereno`, 1 usuario `.invalid`, 3 escolas, 115 alunos `Estudante fictício`, 3 turmas, 115 matriculas, ano 2027 e auditorias de teste. Nunca usar dumps, senhas ou hashes reais.
- O MariaDB dos testes sera somente leitura durante copia; a origem original do notebook nao podera ser alterada, recriada ou apagada.
- Nenhuma operacao destrutiva fora de bancos explicitamente `*_test` do CI; preferir novos nomes efemeros e descarte automatico dos containers.
- O PR #5 fica em rascunho e sem merge; CI verde nao equivale a corte local, validacao de login do notebook nem restauracao concluida.

## Review Focus

1. Conexao de destino com nome sem sufixo `_test` ou host externo deve ser rejeitada **antes de conectar** (Task 1).
2. Um registro previo em uma das tabelas alvo deve bloquear a copia e deixar ambos destinos intactos (Task 2).
3. FK inexistente, UUID trocado ou hash alterado deve gerar falha sem escrita persistida antes dos commits (Task 2).
4. Falha depois do commit municipal e antes do central nao pode relatar sucesso nem tentar apagar o que foi gravado; diagnostico e recuperacao precisam ser reproduziveis (Task 3).
5. Segunda execucao de CI no mesmo conjunto de containers nao pode reutilizar tabelas contaminadas nem apagar historico de outros testes (Task 4).

---

### Task 1: Ambiente de integracao e fixtures totalmente descartaveis

**Files:**
- Create: `tests/Support/DatabaseMigration/DisposableMigrationDatabases.php`
- Create: `tests/Support/DatabaseMigration/FictitiousMigrationFixture.php`
- Create: `tests/Feature/Console/MigrateFictitiousDataToPostgresTest.php`
- Modify: `.github/workflows/testes.yml`

**Interfaces:**
- Consumes: `tests/TestCase.php`, migrations em `database/migrations/platform` e `tenant`, `config/database.php`.
- Produces: `DisposableMigrationDatabases::create(): array{sourcePlatform:Connection,sourceTenant:Connection,targetPlatform:Connection,targetTenant:Connection}` (conexoes explicitamente validadas `*_test`); `FictitiousMigrationFixture::seed(Connection $sourcePlatform, Connection $sourceTenant): void`.

- [ ] **Step 1:** escrever `test_ambientes_descartaveis_exigem_quatro_bancos_test_e_loopback` que rejeita nomes `*_dev`, host externo e duplicacao de destino. Falha esperada: classe/helper ainda inexistente.
- [ ] **Step 2:** executar `php artisan test --filter=MigrateFictitiousDataToPostgresTest` no job CI de integracao com servicos PostgreSQL e MariaDB; verificar falha pelo helper ausente, nao por driver/conexao. Se o job ainda nao existe, criar inicialmente apenas o job/servicos necessarios para observar essa falha, sem alterar o comando de migracao.
- [ ] **Step 3:** implementar `DisposableMigrationDatabases::create()` criando novos bancos com nomes unicos terminados em `_test` exclusivamente quando `APP_ENV=testing` e `ASTUTI_MIGRATION_INTEGRATION=1`, verificando host loopback e nomes antes de conectar; preparar esquemas PG pelas migrations ja existentes, evitando gatilhos PostgreSQL na origem MariaDB.
- [ ] **Step 4:** criar schema MariaDB de fixture com colunas usadas nas 12 tabelas e `FictitiousMigrationFixture::seed()` com IDs/relacoes deterministas, incluindo ao menos um `school_scopes` valido e auditorias sinteticas. Testar `test_fixture_tem_3_escolas_115_alunos_2027_sem_dados_reais`.
- [ ] **Step 5:** instalar `pdo_mysql` no job CI separado, executar teste dirigido (verde) e `php artisan test` (suíte atual sem regressao). Commit `test(pgsql): preparar bancos descartaveis de migracao no CI`.

### Task 2: Mesma copia da CLI testada de ponta a ponta

**Files:**
- Create: `app/Support/DatabaseMigration/FictitiousDataTransfer.php`
- Modify: `app/Console/Commands/MigrateFictitiousDataToPostgres.php`
- Modify: `tests/Feature/Console/MigrateFictitiousDataToPostgresTest.php`
- Check: `app/Support/DatabaseMigration/MigrationVerifier.php`

**Interfaces:**
- Consumes: conexoes retornadas por Task 1 e listas `MigrationVerifier::PLATFORM_TABLES`/`TENANT_TABLES`.
- Produces: `FictitiousDataTransfer::run(Connection $sourcePlatform, Connection $sourceTenant, Connection $targetPlatform, Connection $targetTenant, bool $execute, ?Closure $afterMunicipalCommit = null): array`. Retorno: `array{platform:array<string,int>,tenant:array<string,int>}`. O callback e interno/injetado por teste, **nao** opcao de CLI. `MigrateFictitiousDataToPostgres` continua responsavel por validar `safeContext` e flags antes de construir conexoes; delega ao servico sem relaxar restricoes.

- [ ] **Step 1:** escrever `test_simulacao_nao_escreve_e_copia_preserva_conteudo_uuid_hash_e_contagens` usando conexoes MariaDB/PG reais `*_test` e assertivas nas 12 tabelas; confirmar falha porque o servico nao existe.
- [ ] **Step 2:** extrair a logica existente de leitura consistente, verificacao de fixture/esquema, copia ordenada, comparacao linha a linha, `setval` e rollback para o servico com a assinatura acima; manter mensagens CLI e guarda `APP_ENV=local` existentes.
- [ ] **Step 3:** executar teste dirigido e `php artisan test` duas vezes no CI; exigir aprovacao sem alterar MariaDB de fixture. Conferir que o teste existente `PostgresSchemaPreparationTest::test_a_migracao_de_dados_nao_roda_no_ambiente_de_testes` continua verde.
- [ ] **Step 4:** adicionar `test_destino_nao_vazio_e_fk_invalida_bloqueiam_sem_escrever`, conferindo antes/depois por `SELECT COUNT(*)` e valores de hashes, com falha esperada antes da correcao minima de integridade se houver; realizar somente o ajuste exigido pelo teste. Commit `refactor(pgsql): testar copia real sem afrouxar guardas da CLI`.

### Task 3: Falha parcial, integridade interbancos e recuperacao

**Files:**
- Create: `app/Support/DatabaseMigration/PartialCommitException.php`
- Modify: `app/Support/DatabaseMigration/FictitiousDataTransfer.php`
- Modify: `app/Console/Commands/MigrateFictitiousDataToPostgres.php`
- Modify: `tests/Feature/Console/MigrateFictitiousDataToPostgresTest.php`
- Modify: `docs/operacao/migracao-mariadb-postgresql.md`

**Interfaces:**
- Consumes: `FictitiousDataTransfer::run(..., ?Closure $afterMunicipalCommit)`.
- Produces: `PartialCommitException` para caso de falha apos commit municipal e antes de confirmar central, sem expor credenciais; recuperacao documentada somente com backups e inspecao supervisoras.

- [ ] **Step 1:** escrever `test_falha_antes_do_commit_reverte_ambos_destinos` com violacao de FK na fixture de insercao **depois** de a copia ter comecado e antes de qualquer commit; confirmar que nenhum dos dois destinos persistiu linhas. Este caso amplia o teste de destino invalido da Task 2 e nao precisa de callback adicional.
- [ ] **Step 2:** escrever `test_falha_entre_commits_explica_estado_parcial_e_recusa_retry` com callback que lanca excecao apos o commit municipal; confirmar banco municipal preenchido, central vazio, excecao tipada, origem preservada e nova tentativa recusada.
- [ ] **Step 3:** implementar apenas a classificacao necessaria de erro e checkpoint test-only interno, preservando a ordem atual de commits e sem adicionar `--forcar`/atalhos no CLI; fazer testes RED/GREEN e repetir `php artisan test`.
- [ ] **Step 4:** escrever `test_escopo_escolar_e_usuarios_auditados_referenciam_registros_existentes`; implementar conferencia interbancos antes de permitir commit, sem criar FK impossivel entre databases.
- [ ] **Step 5:** documentar checklist de recuperacao manual em `docs/operacao/migracao-mariadb-postgresql.md` e ensaiar somente em novos bancos `*_test` descartaveis, sem `DROP` em `*_dev`. Commit `test(pgsql): cobrir falhas entre commits e referencias interbancos`.

### Task 4: Portao CI, repetibilidade e encerramento da etapa

**Files:**
- Modify: `.github/workflows/testes.yml`
- Modify: `docs/operacao/migracao-mariadb-postgresql.md`
- Update: `docs/continuidade/README.md` e `HISTORICO.md` em `main` apos evidencias reais, sem mesclar PR #5

**Interfaces:**
- Consumes: testes de Tasks 1-3.
- Produces: job CI independente `migracao-integracao`, com MariaDB+PostgreSQL efemeros, que roda o teste `MigrateFictitiousDataToPostgresTest` duas vezes e publica falhas; workflow principal mantem PHPUnit/Pint/Vite/UI.

- [ ] **Step 1:** configurar o job para executar duas vezes `php artisan test --filter=MigrateFictitiousDataToPostgresTest` nos mesmos containers; conferir que ambos passam. Se a segunda falhar por colisao de fixtures, reproduzir a falha e corrigir exclusivamente o helper de nomes unicos `*_test`, sem limpar bancos locais.
- [ ] **Step 2:** verificar no GitHub Actions sucesso real de ambos jobs, `vendor/bin/pint --test`, `npm run build`, `npm run test:ui`, e teste PHP geral duas vezes; registrar IDs e conclusoes sem presumir resultado.
- [ ] **Step 3:** solicitar revisao independente do PR #5 focada em precondicoes, guardas, origem read-only, falha parcial, auditoria e segredos; corrigir pontos criticos/importantes antes de qualquer corte.
- [ ] **Step 4:** atualizar documentos de continuidade com commits/CI e pendencias, manter PR em rascunho e **nao** executar `astuti:migrar-dados-postgresql --executar` no notebook. Commit `ci(pgsql): exigir teste real de migracao com bancos efemeros`.

## Self-review

- Tasks 1-4 cobrem cada criterio do desenho: servicos reais efemeros, isolamento `*_test`, mesmo motor da CLI, sucesso/erro, rollback, falha entre commits, integridade interbancos, repetibilidade, CI e documentacao.
- `FictitiousDataTransfer::run()` e `DisposableMigrationDatabases::create()` sao interfaces fixadas uma unica vez e consumidas de forma consistente.
- Cada comportamento novo inicia com teste falhando observado, segue implementacao minima e verificacao dirigida+geral; CI verde nao comprova dados do notebook.
- Limite de risco: o motor de migracao existente nao pode perder seu bloqueio `APP_ENV=local`; nao mudar `MigrationVerifier::safeContext` para aceitar producao ou `*_dev` em testes.
