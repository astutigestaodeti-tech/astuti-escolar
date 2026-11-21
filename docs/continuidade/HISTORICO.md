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

## 21/11/2026 - Historico anterior recuperado: backups e simulacao ja realizados

**Fonte:** exportacao integral da conversa anterior `ChatGPT-Astuti Gestao Escolar1-20261121-0059.md`, enviada por Jefferson. O export contem relatos e capturas referenciadas; nesta retomada ainda e necessario confirmar os arquivos existentes no notebook.

**Backup de 20/11/2026 23h44-23h48:** o procedimento anterior criou uma pasta `~/astuti-backups/mariadb-AAAAMMDD-HHMMSS/` com dois arquivos `astuti_platform_dev.sql.gz` e `astuti_monte_sereno_dev.sql.gz`. A conversa registrou respectivamente cerca de 4,7 KB e 17 KB e `gzip -t` sem erro.

**Origem e teste anteriores:** em 20/11 23h51 o MariaDB foi descrito como contendo 3 escolas, 115 alunos ficticios e ano de 2027; em 23h54, o comando sem `--executar` relatou simulacao aprovada, com 1 municipio, 1 usuario, 3 escolas, 115 alunos, 3 turmas, 115 matriculas e 237 auditorias municipais. Nenhuma transferencia efetiva foi autorizada.

**Restauracao de seguranca:** em 21/11 00h00, o assistente anterior relatou que ambos os dumps foram restaurados em bancos descartaveis `astuti_restore_platform_*` e `astuti_restore_monte_*`, com contagens iguais nas 12 tabelas verificadas. Os bancos originais permaneceram inalterados.

**Revisao posterior:** a transferencia `--executar` foi deliberadamente suspensa para revisar filas, integridade e diferencas entre SGBDs. O codigo foi atualizado no GitHub ate `a27be7b1`, agora sincronizado no notebook. A simulacao anterior precede essas alteracoes e deve ser repetida na versao atual.

**Correcao do registro anterior:** a verificacao que disse nao ter encontrado dumps olhou apenas arquivos diretamente na raiz de `~/astuti-backups`, usando `find -maxdepth 1`. Os dumps documentados ficam numa **subpasta** `mariadb-*`; portanto a ausencia na listagem anterior nao prova ausencia de backup. A existencia e integridade dos arquivos ainda devem ser reconfirmadas no notebook.

**Proximo passo:** localizar e verificar os arquivos compactados sem abrir segredos, conferir os bancos restaurados e repetir a simulacao de migracao atualizada, sem `--executar`. Nao apagar, recriar ou reimportar bancos.

## 21/11/2026 - Backups GZIP e bancos restaurados encontrados apos reinicio

**Evidencia direta:** captura 68 do terminal local mostra os dumps em `/home/aflora/astuti-backups/mariadb-20261120-234757/` com 4,7 KB (plataforma) e 17 KB (Monte Sereno). Ambos passaram por `test -s` e `gzip -t`; foram exibidos hashes SHA-256. Isso confirma integridade da compactacao, nao equivale a restauracao/contagem atual.

**Divergencia resolvida:** a primeira consulta `SHOW DATABASES LIKE 'astuti_restore_%'` foi feita com `mariadb -u aflora` e nao listou os bancos de teste. No procedimento de restauracao do chat anterior, eles foram criados com `sudo mariadb`, que possui visibilidade administrativa.

**Evidencia direta:** na captura 69, `sudo mariadb -NBe "SHOW DATABASES LIKE 'astuti_restore_%';"` encontrou ambos os bancos preservados: `astuti_restore_platform_20261120_235929_30385` e `astuti_restore_monte_20261120_235929_30385`. Portanto nao estavam ausentes, apenas invisiveis para a primeira consulta sem permissao. Nao foram recriados nem modificados.

**Nao concluido nesta retomada:** a verificacao atual das contagens das 12 tabelas nos bancos de restauracao e o novo dry-run do comando `astuti:migrar-dados-postgresql` com codigo `a27be7b1` ainda nao aconteceram. O script anterior interrompeu antes da simulacao, por cautela.

**Proxima acao:** conferir por `SELECT COUNT(*)`, via `sudo mariadb`, as seis tabelas centrais e seis municipais entre origens e restauracoes; somente se todas conferirem, repetir a simulacao sem `--executar`. Preservar os bancos de origem, dumps, bancos restaurados e destinos PostgreSQL.

## 21/11/2026 - Restauracoes comparadas e dry-run atual aprovado

**Evidencia direta:** captura 70 do terminal Debian enviada por Jefferson, HEAD local `a27be7b1`. O script comparou as 12 tabelas nas bases MariaDB originais e nas duas restauracoes por `SELECT COUNT(*)` e todas as comparacoes retornaram `OK`.

**Contagens centrais iguais entre original e restauracao:** `municipalities=1`, `users=1`, `municipality_provision_attempts=2`, `memberships=1`, `school_scopes=0`, `platform_audit_logs=7`.

**Contagens municipais iguais entre original e restauracao:** `schools=3`, `school_years=1`, `students=115`, `school_classes=3`, `student_enrollments=115`, `audit_logs=237`. O cenario ficticio Monte Sereno continua preservado.

**Simulacao atualizada:** apos as doze comparacoes, foi executado `php artisan astuti:migrar-dados-postgresql` **sem `--executar`**, no codigo `a27be7b1`, com conexao local a origem MariaDB. A rotina listou as contagens esperadas de seis tabelas centrais e seis municipais e terminou com `Simulacao aprovada. Nenhum dado foi gravado.` O script externo tambem terminou com `Nenhum dado foi transferido ou alterado.`

**Limites da evidencia:** comparacao de contagens nao significa verificacao linha a linha, de hashes ou integridade referencial completa. A simulacao e somente leitura. Nao houve copia definitiva dos dados para os bancos PostgreSQL, nem revalidacao de login, painel ou build local nesta etapa. A migration `jobs` central continua documentada como `Pending` ate nova verificacao.

**Proximo passo:** revisao tecnica do comando de copia, verificacao de riscos de falha parcial entre os bancos e da migration de filas `jobs`; somente depois decidir o proximo comando seguro, sem executar `--executar` prematuramente. PR #5 permanece em rascunho; nenhuma mesclagem ou VPS foi contratado.

## 21/11/2026 - Revisao tecnica pre-transferencia do PR #5

**Revisao somente leitura:** inspecao da branch `feature/migracao-postgresql-instalacoes-isoladas` HEAD `a27be7b1`, incluindo comando de migracao, verificador, migrations, guia e automacao CI. PR #5 segue aberto em rascunho. Nao houve alteracao de codigo nem dos bancos nesta revisao.

**Pontos positivos:** bloqueio de execucao fora do ambiente ficticio local, leitura MariaDB em transacoes somente leitura, exigencia de flags explicitas, destinos vazios, conferencia de linhas/identidades/contagens e preservacao de hashes. O ajuste de sequencia cobre a unica coluna serial copiada (`municipality_provision_attempts.id`).

**Risco comprovado no fluxo de codigo:** os commits dos bancos PostgreSQL central e municipal ocorrem separadamente. Se o primeiro concluir e o segundo falhar, nao existe rollback atomico entre os dois bancos; o procedimento de recuperacao de uma copia parcial ainda precisa de ensaio controlado em destinos descartaveis.

**Pendencias identificadas:** migration `0001_01_01_000002_create_jobs_table` central estava `Pending` no ultimo print, sem verificacao atual da existencia fisica das tabelas `jobs`, `job_batches` e `failed_jobs`; nao foi encontrada cobertura de CI que execute a copia real MariaDB->PG (workflow sem servico MariaDB/`pdo_mysql`); referencias logicas entre bancos e hashes/login exigem validacao funcional. Nao ha evidencia de corrupcao atual ou divergencia de contagens.

**Decisao de seguranca:** NAO liberar `--executar` nos destinos PostgreSQL principais agora e nao mesclar PR #5. Primeiro conferir tabelas fisicas e migrations sem gravacao; depois resolver schema pendente com backup/verificacao, criar testes de integracao e realizar ensaio de corte/recuperacao em bases descartaveis com autorizacao, antes de nova avaliacao. Os dumps e origens MariaDB permanecem preservados.

## 21/11/2026 - Tabelas de fila PostgreSQL confirmadas ausentes

**Evidencia direta:** captura 71 do terminal Debian. `php artisan migrate:status --database=platform --path=database/migrations/platform` voltou a mostrar a migration `0001_01_01_000002_create_jobs_table` como `Pending`, e as outras dez migrations centrais como `Ran`.

**Verificacao fisica:** consulta read-only ao PostgreSQL `astuti_platform_dev`, com `to_regclass`, confirmou `jobs`, `job_batches` e `failed_jobs` todas `AUSENTE`. Portanto nao ha colisao observada com tabelas previamente criadas. Nao houve qualquer alteracao de banco no passo.

**Proxima acao planejada, ainda nao executada:** criar backups `pg_dump -Fc` dos bancos PostgreSQL de desenvolvimento fora do Git, verificar legibilidade com `pg_restore -l` e checksum; revalidar que apenas a migration `jobs` esta pendente e as tres tabelas seguem ausentes; entao aplicar exclusivamente migrations do caminho de plataforma com `php artisan migrate --database=platform --path=database/migrations/platform` e verificar status/tabelas depois. Nunca rodar `migrate:fresh`/rollback automatico. Ainda falta teste integrado de migracao MariaDB->PostgreSQL em destinos descartaveis, antes de `--executar` nos destinos principais.

## Modelo para as proximas entradas

### AAAA-MM-DD - Titulo do marco

- **Pedido e objetivo:**
- **Decisoes aprovadas:**
- **Arquivos, branch, commits e PRs:**
- **Evidencias e testes executados:**
- **Banco e infraestrutura:**
- **Pendencias e riscos:**
- **Proxima acao segura:**
