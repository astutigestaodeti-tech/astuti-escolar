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

## 21/11/2026 - Backup PostgreSQL e migration de filas concluidos

**Evidencia direta:** captura 72 enviada por Jefferson apos execucao automatizada no Debian. O script confirmou primeiro `a27be7b1` e apenas `0001_01_01_000002_create_jobs_table` pendente, com as tres tabelas ausentes.

**Backups anteriores a escrita de esquema:** criados dois arquivos `pg_dump -Fc` em subpasta local `~/astuti-backups/pgsql-antes-jobs-*/`, um para `astuti_platform_dev` e outro para `astuti_monte_sereno_dev`. Ambos passaram por `test -s` e leitura do indice `pg_restore -l`; hashes SHA-256 exibidos. Ainda nao houve ensaio completo de restauracao desses dumps PG; nao publicar arquivos no GitHub.

**Migracao aplicada e conferida:** `php artisan migrate --database=platform --path=database/migrations/platform` executou `0001_01_01_000002_create_jobs_table` com `DONE`. Nova listagem de `migrate:status` mostrou a migration como `[2] Ran` e as outras dez como `[1] Ran`. A verificacao final encontrou as tres tabelas de fila `jobs`, `job_batches` e `failed_jobs` presentes.

**Limites:** houve escrita apenas de estrutura e registro da migration no PostgreSQL central; nao houve copia de dados escolares nem alteracao do MariaDB. Nenhuma importacao real foi testada em PostgreSQL nesta captura. O PR #5 permanece em rascunho.

**Proxima etapa prevista no plano aprovado:** construir/rodar testes de integracao da transferencia MariaDB->PostgreSQL em bancos descartaveis, incluindo sucesso, falha antes dos commits e recuperacao de falha entre commits; conferir permissao, hashes e relacionamentos. Nao usar `--executar` nos destinos principais antes dessas provas e de autorizacao especifica.

## 21/11/2026 - Desenho de testes reais no CI aprovado em principio

**Pedido:** Jefferson aprovou preparar testes automatizados da transferencia MariaDB->PostgreSQL no GitHub antes do ensaio local, mantendo PR #5 draft e notebook preservado.

**Fato distinto de implementacao:** foi **criado somente um documento de desenho**, `docs/superpowers/specs/2026-11-21-testes-integracao-mariadb-postgresql-design.md`, na branch `feature/migracao-postgresql-instalacoes-isoladas`, commit `409b5380d4dfdc7badc76ed35cec418585c4dd0d`. A branch remota passou a esse commit; o notebook foi verificado por ultimo em `a27be7b1` e nao foi sincronizado com essa atualizacao documental.

**Proposta em revisao:** job CI separado com MariaDB+PostgreSQL efemeros e somente bancos `*_test`; fixtures integralmente ficticias; reutilizar a logica de copia sem abrir os bloqueios do comando local; testes de sucesso, rollback, falha entre commits, preservacao de hashes/UUIDs e origem somente leitura. A ideia exige validacao detalhada do desenho antes de gerar o plano de codigo.

**Nao concluido:** nenhum teste novo, servico CI, refatoracao, commit de codigo ou transferencia de dados foi implementado com essa aprovacao inicial. A etapa de desenho segue aguardando validacao de Jefferson e depois planejamento TDD.

## 21/11/2026 - Plano Superpowers de testes reais elaborado e verificado

**Aprovacao recebida:** Jefferson aprovou o desenho `docs/superpowers/specs/2026-11-21-testes-integracao-mariadb-postgresql-design.md`, que preve banco MariaDB+PostgreSQL efemeros no GitHub e nenhum acesso ao notebook. Um check independente de viabilidade do desenho retornou sem questoes importantes pendentes.

**Novo artefato, apenas documentacao:** plano `docs/superpowers/plans/2026-11-21-testes-integracao-mariadb-postgresql.md` criado na branch `feature/migracao-postgresql-instalacoes-isoladas`. Revisao de clareza atualizada no commit `54a6d2a2cf80f1fe76c9fb99bf5b4f17b2b8e32a`. Branch remota contem os commits documentais anteriores `409b5380` (desenho), `42b6b570` (plano) e `54a6d2a2` (correcao do plano). O ultimo HEAD local comprovado por Jefferson segue `a27be7b1`.

**Plano de execucao:** quatro Tasks com 19 passos verificaveis: (1) job CI e fixtures so `*_test`; (2) extrair motor de copia para testes reais preservando as guardas da CLI local; (3) ensaiar erro, rollback, commit parcial, referencias e recuperacao; (4) duas execucoes, CI, revisao independente e documentacao. TDD exige observar falhas especificas antes das mudancas correspondentes.

**Status efetivo:** nenhuma Task executada, nenhum codigo alterado, nenhum teste novo rodado, nenhum dado transferido. PR #5 deve permanecer draft. Proximo passo e Jefferson revisar o plano e escolher execucao direta ou por subagentes, conforme Superpowers; so entao iniciar codigo.

## Modelo para as proximas entradas

### AAAA-MM-DD - Titulo do marco

- **Pedido e objetivo:**
- **Decisoes aprovadas:**
- **Arquivos, branch, commits e PRs:**
- **Evidencias e testes executados:**
- **Banco e infraestrutura:**
- **Pendencias e riscos:**
- **Proxima acao segura:**

## 21/11/2026 - Primeira etapa de teste de integracao MariaDB para PostgreSQL implementada

**Autorizacao de Jefferson:** executar automaticamente o maximo das etapas de desenvolvimento, testes, revisao e documentacao no GitHub sem novas solicitacoes para continuar; nao executar qualquer corte nos bancos do notebook sem autorizacao especifica. Foi aplicada a sequencia Superpowers de testes TDD e revisao independente.

**Codigo efetivamente publicado no PR #5 (ainda draft):** commit `d5063d9a2d2580a6ab5fb8b68c329362904166f6`, criado um job independente `migracao-integracao` em `.github/workflows/testes.yml`; adicionados `tests/Support/DatabaseMigration/DisposableMigrationDatabases.php`, `FictitiousMigrationFixture.php` e `tests/Feature/Console/MigrateFictitiousDataToPostgresTest.php`. A implementacao cria quatro bancos descartaveis `*_test` para MariaDB 11.8 e PostgreSQL 17, valida flags de CI, hosts loopback e nomes antes de conectar, aplica as migrations e popula exclusivamente fixtures inventadas.

**Evidencia TDD:** run PR `44867148779` teve RED por helper ausente; run `44867407056` validou nove guardas e teve RED por fixture ausente; run `44867708379` teve GREEN de 10 testes e 90 assercoes no job de integracao, alem de duas execucoes da suite PHP geral (165 testes e 606 assercoes cada), Pint (126 arquivos), build e quatro testes de interface. Ambos jobs concluíram com `success`. Revisao independente Task 1: PASS, sem achado critico/importante; apenas sugestao menor de acrescentar testes para nomes administrativos e variavel `APP_ENV` incorretos.

**Limite do resultado:** esta etapa testou o isolamento e a fixture, nao executou o motor de copia real entre os dois SGBDs. Nenhum banco `*_dev` do notebook foi acessado ou alterado. O ultimo commit local evidenciado no notebook segue `a27be7b1`, e o PR #5 nao foi mesclado.

**Proxima tarefa ja autorizada e iniciada:** Task 2 do plano `docs/superpowers/plans/2026-11-21-testes-integracao-mariadb-postgresql.md`, com extracao do motor de copia e testes reais MariaDB para PostgreSQL nos bancos `*_test`. Apos, Tasks 3-4 para rollback, falha entre commits, referencias e CI repetivel.

## 21/11/2026 - Transferencia real de fixture MariaDB para PostgreSQL testada na CI

**Codigo verificado:** commit `3e02447d3adf3066b64873d9ba6e30c4fbf4a938` no PR #5, ainda em rascunho. Novo servico `app/Support/DatabaseMigration/FictitiousDataTransfer.php` extraiu a rotina de migracao antes restrita ao comando; o comando `astuti:migrar-dados-postgresql` conserva exatamente as guardas de ambiente `local`, nomes `*_dev`, loopback, origem MariaDB e confirmacao explicita. O servico opera sobre conexoes ja validadas e somente fixtures `*_test` no CI, sem credenciais nem acesso ao notebook.

**Testes reais no GitHub Actions:** RED no run PR `44868139213` pela classe ainda ausente. Primeiro GREEN em `44868302439`, 11 testes/112 assercoes. Segundo RED em `44868481142` encontrou a origem MariaDB ficando com preferências de sessao alteradas depois do rollback. A correcao fixou somente a transacao de leitura consistente, deixando as preferencias da sessao intactas. GREEN final no run `44868602481`, 13 testes/157 assercoes de integracao, duas rodadas da suite PHP geral (165 testes/606 assercoes cada), Pint em 127 arquivos, Vite e 4 testes da interface. Ambos jobs passaram também no push do mesmo SHA.

**O que esta comprovado:** simulacao sem escrita, copia de todas as 12 tabelas, identidades UUID, contagens, hashes, escolas/115 alunos ficticios, ano 2027, impedimento de destino ocupado, recusa por FK invalida, rollback e preservacao da origem nas verificacoes testadas. Revisao independente da Task2: PASS sem bloqueios; ressalvas menores sobre ordem das contagens na CLI em caso de erro e exigencia de conexoes nao compartilhadas com transacoes abertas.

**O que falta:** falha apos commit municipal e antes de commit central, retry parcial, integridade entre bancos e procedimento de recuperacao (Task3 em andamento), depois dupla execucao do job, revisao global e registro final (Task4). Nenhum dado real do notebook foi transferido, e o ultimo HEAD local comprovado permanece `a27be7b1`. PR #5 ainda nao foi mesclado; continua proibido usar `--executar` nos bancos do notebook sem revisao e autorizacao especifica.

## 21/11/2026 - Ensaiadas falhas de importacao e integridade interbancos

**Codigo real no GitHub:** em `d5b551b014453c62d301dabdf56fb41655be7b29` (PR #5 draft) foram acrescentados `PartialCommitException`, tratamento de commit parcial no serviço e comando, verificacoes dos escopos escolares e autores da auditoria cruzando central/municipal, e testes com bancos `*_test`. Teste de rollback usa erro tardio de FK em matricula apos insercoes nos dois destinos. Outro teste injeta falha apos confirmar somente o municipio, mostra parcial preservado, segunda tentativa recusada e recuperacao em novo par descartavel, sem remover evidencias.

**Evidencia TDD:** run PR `44869093251` falhou como esperado em 7 casos de falha parcial e orfaos, com 15 testes aprovados (194 assercoes). Run PR `44869277017` concluiu com ambos jobs verdes: 22 testes de integracao e 228 assercoes, suite PHP 165/606 repetida duas vezes, Pint 128 arquivos, Vite e 4 testes da interface. Os dois bancos MariaDB de origem foram conferidos como intactos na fixture; nenhum banco `*_dev` ou dump do notebook foi acessado.

**Revisao independente:** o motor e os testes receberam parecer favoravel, mas a revisao do runbook apontou que backups dos PG eram exigidos para recuperar falha sem estarem na sequencia obrigatoria antes da copia. O guia `docs/operacao/migracao-mariadb-postgresql.md` foi corrigido no commit `6a3e82c4376431c8f9de4e0abf6307aa45865189`: antes de `--executar`, exige dumps `pg_dump -Fc` dos dois destinos vazios, listagens `pg_restore -l`, hashes SHA-256, diretorio restrito, conservacao dos backups anteriores e ensaio de restauracao isolada supervisionada. Nova revisao: PASS. A run de revalidacao desse ajuste documental e `44869577490`.

**Pendente e proximo passo ja autorizado:** Task 4, repetir integralmente teste dirigido duas vezes nos mesmos containers e revisar a seguranca/CI global. A simulação de falha parcial nao equivale a restauracao de backups reais: a restauracao PG do notebook exige ensaio proprio em bases isoladas. PR #5 segue em rascunho; ultimo HEAD do notebook comprovado `a27be7b1`, sem dados copiados.

## 21/11/2026 - Testes de integracao repetiveis e revisao global concluidos

**Fechamento das quatro Tasks Superpowers:** HEAD remoto `5024059ffd8e2728dc712828e72d32ebf74cc4b0` na branch `feature/migracao-postgresql-instalacoes-isoladas`, PR #5 **aberto em rascunho e sem merge**. A Task4 acrescentou ao job de GitHub Actions uma segunda rodada de `MigrateFictitiousDataToPostgresTest` nos mesmos containers MariaDB 11.8/PostgreSQL 17, mas em quatro novas bases administrativas/operacionais `*_test`. Atualizou os testes para detectar duplicacao de destino com nomes dinamicos da rodada. O motor da transferencia nao precisou de alteracoes adicionais.

**Falha real descoberta pela repeticao:** run `44869775716`, job `136741464860`, primeira rodada verde (22/228), segunda rodada com uma falha no teste de destinos duplicados porque se usava nome fixo do primeiro conjunto. A correcao do teste ocorreu em `362eeee9dcf8f23e505b79f3dac1b1228d3643ac`. Run `44869962312` passou com **duas rodadas de 22 testes/228 assercoes cada**, alem de duas rodadas da suite geral com 165 testes/606 assercoes cada, Pint 128 arquivos, build Vite e quatro testes de interface.

**Revisao global independente:** encontrou risco de sobrescrita no exemplo antigo de `mariadb-dump` do runbook. Um comando de redirecionamento para nomes fixos poderia truncar backups anteriores. O risco foi reproduzido em teste shell **simulado, sem banco real**, depois corrigido no commit final `5024059`: diretorio novo com `mktemp -d` e permissao restrita por tentativa, dumps separados, falha em arquivo vazio, checksum SHA-256 e preservacao de todas as copias antigas. A revisao simulou duas tentativas bem-sucedidas e duas falhas sem sobrescrever backups anteriores. Parecer global final: **PASS**, sem achados criticos/importantes pendentes. Ressalvas menores: a CLI ainda apresenta mensagem generica para certas falhas de integridade e o ajuste de sequencia nao e transacional.

**CI final confirmado:** PR run `44870385911`, HEAD `5024059`, job de integracao `136743364474` **success** com 2 x 22/228; job geral `136743364367` **success** com 2 x 165/606, Pint 128, Vite e quatro testes da interface. Foram preservadas as guardas da CLI `APP_ENV=local`, conexoes locais `*_dev` exatas, `--executar --confirmar=MONTE-SERENO-DEV`, origem MariaDB somente leitura e callback de falha inacessivel na CLI. A descricao do PR #5 foi atualizada para informar que o CI testou transferencia **sintetica**, mas que o notebook nao foi migrado.

**Estado da maquina de Jefferson:** ultimo HEAD local efetivamente observado continua `a27be7b1`; nenhum commit novo foi aplicado no notebook por esta sessao. Os dumps MariaDB anteriores, as restauracoes MariaDB, os dois dumps PostgreSQL e os bancos locais sao preservados. Nao houve qualquer copia efetiva para os PostgreSQL do notebook, nem ensaio de restauracao dos dumps PostgreSQL locais, nem login validado apos copia. Nenhum VPS contratado.

**Proximo passo seguro:** no Debian/WSL de Jefferson, verificar `git status --short --branch` e, caso a arvore esteja limpa, sincronizar a branch por `git fetch origin` + `git pull --ff-only origin feature/migracao-postgresql-instalacoes-isoladas`, conferir a versao e repetir simulacao somente de leitura. Depois, com autorizacao especifica e janela de manutencao, ensaiar a restauracao dos backups em novos bancos isolados e, primeiro, a copia em destinos PostgreSQL descartaveis locais com comparacao funcional e login. Nao iniciar `--executar` nos destinos principais sem estas evidencias e nova verificacao. O PR #5 e os dependentes #3/#4 permanecem draft, sem merge.

## 21/11/2026 - Notebook sincronizado ao CI final e nova simulacao aprovada

**Evidencia direta do notebook:** captura 73 mostrou `git fetch origin` e `git pull --ff-only origin feature/migracao-postgresql-instalacoes-isoladas` concluindo por fast-forward de `a27be7b1` para `5024059`, sem alteracoes locais anteriores. A primeira chamada de `php artisan astuti:migrar-dados-postgresql` sem flags de copia foi recusada por precondicao de configuracao, antes de qualquer leitura de origem ou escrita de destino.

**Diagnostico sem mostrar segredos:** captura 74 conferiu `APP_ENV=local`, plataforma e municipio PostgreSQL nos nomes `*_dev` esperados, hosts locais, portas numericas e configuracao sem cache. Somente `ASTUTI_SOURCE_MARIADB_USERNAME` e `ASTUTI_SOURCE_MARIADB_PASSWORD` nao estavam acessiveis nessa invocacao do terminal. As opcoes de MariaDB usadas no comando de simulacao aprovado anteriormente haviam sido informadas por variaveis de ambiente apenas naquela invocacao, sem configuracao permanente no shell.

**Resolucao comprovada:** captura 75 mostra nova simulacao em `5024059` passando as configuracoes da origem **apenas para essa chamada**, com `ASTUTI_SOURCE_MARIADB_USERNAME=aflora`, senha local vazia, host de loopback e socket MariaDB. Contagens: central `municipalities=1`, `users=1`, `municipality_provision_attempts=2`, `memberships=1`, `school_scopes=0`, `platform_audit_logs=7`; municipal `schools=3`, `school_years=1`, `students=115`, `school_classes=3`, `student_enrollments=115`, `audit_logs=237`. Saida final: `Simulacao aprovada. Nenhum dado foi gravado.` Nao foi executado `--executar`, nenhum aluno foi transferido.

**Proximo passo seguro:** verificar por leitura os dumps MariaDB e PostgreSQL locais, localizar seus arquivos sem exposicao de senhas ou conteudo, conferir integridade de arquivos e indices de `pg_restore`, e preparar ensaio supervisionado de restauracao PostgreSQL para **novos** bancos isolados. Nao apagar, sobrescrever nem restaurar sobre `*_dev`. A simulação de CI com fixture sintetica nao substitui a restauracao do notebook. PR #5 continua draft, sem merge; VPS ainda nao contratado.

## 21/11/2026 - Quatro arquivos de backup validados no notebook

**Evidencia direta:** captura 76 do Debian, com verificacao somente de leitura e sem mudanca de banco. Os dois arquivos PostgreSQL em `/home/aflora/astuti-backups/pgsql-antes-jobs-2Xqrc9lg/`, `astuti_platform_dev.dump` e `astuti_monte_sereno_dev.dump`, estavam nao vazios e `pg_restore --list` aceitou ambos. Os dois arquivos MariaDB em `/home/aflora/astuti-backups/mariadb-20261120-234757/`, `astuti_platform_dev.sql.gz` e `astuti_monte_sereno_dev.sql.gz`, estavam nao vazios e passaram em `gzip -t`. O terminal apresentou os quatro hashes SHA-256.

**Limite importante:** a validacao de cabecalhos, indices e compressao nao substitui o teste completo de restauracao. Os dumps PostgreSQL foram feitos **antes** de aplicar a migration de filas `jobs`, por isso a eventual ausencia dessas tabelas numa restauracao isolada e esperada; no banco `astuti_platform_dev` original a migration ja foi aplicada.

**Proximo passo em preparacao:** restaurar os dois arquivos PostgreSQL em dois **novos bancos com nomes unicos**, nunca nos `*_dev`, via `createdb --template=template0` e `pg_restore --single-transaction --exit-on-error --no-owner --no-acl`. Confirmar esquema e contagens nas restauracoes e preservar todos os bancos para auditoria. Este teste **ainda nao foi executado** no notebook: aguarda Jefferson colar o comando e enviar a saida.

## 21/11/2026 - Diagnostico do bloco de restauracao e roteiro automatizado

**Evidencia direta:** captura 77 mostrou a colagem de um bloco extenso no terminal, com o trecho final misturado e sem resultados das restauracoes. A captura 78 executou um inventario PostgreSQL somente de leitura por `sudo -u postgres psql`, listando apenas `astuti_monte_sereno_dev` (7.990 kB) e `astuti_platform_dev` (8.182 kB). **Nenhum banco com prefixo `astuti_restore_pg_` existia** naquela consulta. A exibicao `codigo de saida: 0` veio apos um comando `echo`, portanto nao e prova da execucao ou do sucesso do bloco anterior.

**Correcao operacional no repositorio:** foi adicionado `scripts/operacao/ensaiar-restauracao-postgresql.sh` na branch `feature/migracao-postgresql-instalacoes-isoladas`, com leitura dos dois dumps PG de `~/astuti-backups/pgsql-antes-jobs-2Xqrc9lg/`, nomes exclusivos `astuti_restore_pg_platform_*` e `astuti_restore_pg_monte_*`, restauracao atomica por banco, interrupcao no primeiro erro, contagens nas copias e preservacao de qualquer resultado parcial. O arquivo `tests/Shell/EnsaiarRestauracaoPostgresqlTest.sh` exercita sem tocar em bancos tres cenarios: backups ausentes, fluxo simulado normal e falha no primeiro `pg_restore`; os tres passaram localmente, assim como `bash -n` do script. O workflow `.github/workflows/testes.yml` foi atualizado para executar esse teste em CI; foi adicionado tambem um trecho ao runbook de migracao. HEAD remoto do PR #5 ao concluir as escritas: `6280b4f2e80b230c7f28451b728c72bfc902908f`.

**Limite:** o roteiro **ainda nao rodou no PostgreSQL real do notebook**. O resultado CI do novo commit precisa de verificacao antes de afirmar que passou. O proximo passo para Jefferson e sincronizar sua branch local limpa e executar o teste shell seguido de `bash scripts/operacao/ensaiar-restauracao-postgresql.sh`, enviando a saida. Nao rodar o comando de transferencia `--executar` nos destinos originais nesta etapa.

## 21/11/2026 - Falha controlada do ensaio e causa raiz encontrada

**Evidencia direta:** captura 79 do Debian, branch sincronizada de `5024059` para `6280b4f`. O teste simulado passou 3 cenarios, mas o roteiro real parou na precondicao com `ERRO: backup ausente ou vazio`, antes de chamar `sudo`, `createdb` ou `pg_restore` de gravacao. Nao ha evidencia de banco PostgreSQL de restauracao criado.

**Causa especifica confirmada por comparacao das capturas 76 e 79:** os backups verdadeiros estao na pasta `pgsql-antes-jobs-2Xqrc91g` (digito `1` depois do `9`); o roteiro publicado apontava para `pgsql-antes-jobs-2Xqrc9lg` (letra `l` depois do `9`). O sufixo e gerado e nao deve ser transcrito ou congelado no codigo. O erro foi do roteiro, nao do usuario.

**Correcao sendo testada antes de nova instrucao ao notebook:** localizar automaticamente, sob `~/astuti-backups`, o unico par completo em pasta `pgsql-antes-jobs-*`; validar formato com `pg_restore --list`; se nenhum ou mais de um par completo for encontrado, recusar sem executar `sudo`. Acrescentar regressao para sufixo com digito `1` e para dois pares ambiguos. A restauracao real continua pendente ate a versao corrigida estar no GitHub, validada pelo CI e sincronizada no notebook.

## 21/11/2026 - Roteiro de restauracao corrigido e ampliado com TDD

**Causa raiz eliminada no codigo:** o nome da pasta de backup e dinamico e pode conter caracteres visualmente parecidos. `scripts/operacao/ensaiar-restauracao-postgresql.sh` agora procura sob `~/astuti-backups` exatamente um par completo em `pgsql-antes-jobs-*`; aborta sem `sudo` se houver zero ou mais de um par ou se a busca `find` falhar. `pg_restore --list` continua exigido para ambos os arquivos antes de criar qualquer banco.

**Revisao tecnica independente:** apontou e foram corrigidos dois riscos importantes antes do ensaio real: destinos PostgreSQL sujeitos aos padroes de ambiente do libpq, agora fixados explicitamente no socket local `/var/run/postgresql`, porta 5432 e usuario postgres em todas as chamadas; e mascaramento do codigo de erro do `find`, agora corrigido por manifest temporario e checagem de retorno. Nenhuma mudanca de banco foi feita pela revisao.

**TDD validado em sandbox:** teste novo reproduziu falha anterior por nome com digito `1`, que a primeira versao nao aceitava; outro teste simulou `find` imprimindo resultado parcial antes de sair com erro, que a versao intermediaria aceitava; outro falhou enquanto os comandos nao fixavam host/porta/usuario. Todas as correcoes passaram apos cada etapa. Suite final de shell retornou `PASS: 8 cenarios de seguranca do ensaio PostgreSQL.`, e `bash -n` do script retornou sucesso. Os mocks evitam acessos reais a PostgreSQL e `sudo`. O runbook da migracao foi atualizado.

**GitHub:** script, testes e guia foram atualizados na branch `feature/migracao-postgresql-instalacoes-isoladas`, HEAD `88d2267cb2e5e8762eb2be70d79a44adbf7c8fdb`, PR #5 permanece draft. O sucesso do CI nesse novo HEAD e a restauracao em PostgreSQL real ainda **precisam ser confirmados** antes de registrar a etapa como concluida. Nenhuma copia MariaDB -> PostgreSQL original foi executada.

## 21/11/2026 - Restauracao real dos dois backups PostgreSQL concluida

**Evidencia direta:** captura 80 do terminal Debian enviada por Jefferson. A branch local foi sincronizada por fast-forward de `6280b4f` para `88d2267`. O script `scripts/operacao/ensaiar-restauracao-postgresql.sh` encontrou e aceitou os dois arquivos customizados em `/home/aflora/astuti-backups/pgsql-antes-jobs-2Xqrc91g/`, sem exigir a digitacao do sufixo variavel.

**Dois bancos isolados efetivamente criados e restaurados:**
- Central: `astuti_restore_pg_platform_20261121_100115_4075`. Contagens mostradas por `psql`: `migrations=10`, `municipalities=0`, `users=0`.
- Municipal: `astuti_restore_pg_monte_20261121_100115_4075`. Contagens: `migrations=8`, `schools=0`, `students=0`.

**Saida final do roteiro:** `ENSAIO DE RESTAURACAO CONCLUIDO`, com preservacao dos bancos PostgreSQL de origem `*_dev` e dos arquivos. Nenhuma copia MariaDB -> PostgreSQL foi executada. Os arquivos PG sao snapshots **anteriores** a criacao das tabelas de filas `jobs`; portanto o total de migrations da restauracao central (10) e menor que o banco de desenvolvimento atual (11), como previsto. Estes bancos restaurados devem ser preservados por enquanto, sem limpeza automatica.

**GitHub Actions posterior a correcao:** run `44906655873` do HEAD `88d2267` teve os dois jobs marcados `completed / success`: `Testes PHP e interface` e `Integracao MariaDB para PostgreSQL`, incluindo o novo passo de teste shell. O CI usa mocks e servicos sinteticos; a captura 80 e a evidencia independente do ensaio local real.

**Proxima fronteira:** a restauracao PostgreSQL foi demonstrada, mas **os dados ficticios MariaDB ainda nao foram copiados ao PostgreSQL do notebook**. Antes de usar `--executar` nos `*_dev`, preparar backups novos com o esquema PostgreSQL atual (apos a migration de `jobs`) e ensaiar a transferencia dos dados ficticios em bancos-alvo descartaveis locais, comparando linhas, hashes, permissao e login. Nao confundir teste de restauracao de dados vazios com validacao da importacao. PR #5 continua em rascunho, sem merge; sem VPS contratado.

## 21/11/2026 - Backup do PostgreSQL atual e restauracao novamente aprovados

**Evidencia direta:** captura 81 do Debian. A branch local `feature/migracao-postgresql-instalacoes-isoladas` recebeu fast-forward de `88d2267` para `574401b1`, incorporando `scripts/operacao/criar-backups-pre-copia-postgresql.sh`, testes e workflow CI. O roteiro conferiu PostgreSQL local, existencia da tabela `jobs` e ausencia de linhas nas 12 tabelas de destino antes de gravar backups.

**Backup novo e exclusivo:** em `/home/aflora/astuti-backups/pgsql-pre-copia-hNtsqqkz/` foram criados os dois arquivos `astuti_platform_dev.dump` e `astuti_monte_sereno_dev.dump`, ambos listados como `OK`. `sha256sum -c SHA256SUMS` devolveu `OK` para cada arquivo. O conteudo e completo (estrutura e dados) e foi salvo fora do GitHub.

**Restauração real do esquema ATUAL:** o mesmo roteiro invocou `ensaiar-restauracao-postgresql.sh --backup-dir` e restaurou ambos os arquivos em dois novos bancos locais: `astuti_restore_pg_platform_20261121_101039_3726` e `astuti_restore_pg_monte_20261121_101039_3726`. A captura mostrou, no primeiro, `migrations=11`, `municipalities=0`, `users=0`; no segundo, `migrations=8`, `schools=0`, `students=0`. Saida final: `BACKUPS PRE-COPIA E RESTAURACAO CONFERIDOS`. Os bancos `*_dev` originais e os backups anteriores foram preservados, sem copia MariaDB -> PostgreSQL.

**CI do codigo do roteiro:** GitHub Actions run `44907410354` do HEAD `574401b1` concluiu os dois jobs com `success`, incluindo 6 testes shell simulados de backup pre-copia, 8 testes shell do ensaio isolado, PHP e interface e integracao sintetica MariaDB/PostgreSQL. Os testes simulados nao substituem a captura 81, que comprova a restauracao real.

**Proximo passo e guarda de seguranca:** criar um mecanismo de ensaio LOCAL de transferencia dos dados ficticios MariaDB para PostgreSQL exclusivamente em destinos descartaveis, sem enfraquecer as protecoes da CLI normal (que so aceita nomes exatos `*_dev`). Comparar dados, relacionamentos, hashes e login. Nao executar `php artisan astuti:migrar-dados-postgresql --executar` nos `*_dev` originais ate esse ensaio e uma avaliacao especifica; nao mesclar PR #5 ainda.


## 21/11/2026 - Roteiro de transferencia ficticia em cluster PostgreSQL isolado preparado

**Contexto:** a captura 81 confirmou o backup atual e a restauracao das duas bases PostgreSQL com `migrations=11` no central e `migrations=8` no municipal, sem importar alunos. Nao reexecutar essas etapas desnecessariamente.

**Acao no codigo:** criado `scripts/operacao/ensaiar-copia-isolada-postgresql.sh` na branch `feature/migracao-postgresql-instalacoes-isoladas`, acompanhado de `tests/Shell/EnsaiarCopiaIsoladaPostgresqlTest.sh`, passo de CI e plano curto `docs/superpowers/plans/2026-11-21-ensaio-copia-cluster-isolado.md`. O roteiro reaproveita a CLI original e cria um **cluster PostgreSQL 17 novo**, com porta exclusiva entre 55432 e 55531, nome unico, escuta apenas 127.0.0.1, usuario de banco temporario e `start.conf=manual`. Restaura apenas dumps PostgreSQL atuais nesse outro cluster; evita editar .env, usar a porta 5432 como destino ou limpar o cache global Laravel. Antes de permitir copia, confere SHA-256 dos dois dumps, identidade fisica diferente do servidor original, contagens originais vazias, nomes/porta/usuario de ambas as conexoes SQL reais do Laravel e simula a migracao. Somente depois executa `--executar` no cluster de ensaio. Ao sair tenta desligar o cluster, preservando a configuracao e as bases temporarias para auditoria.

**TDD e revisao:** teste shell escrito e observado falhar antes de criar o roteiro. Oito cenarios simulados de seguranca passaram localmente. Revisao independente identificou e motivou correcoes de manifesto incompleto, parada de cluster, falsa seguranca de testes com `! grep`, cache global, verificacao SQL de conexoes e inicializacao adequada no Debian/WSL. A conta MariaDB `aflora` tem privilegios locais amplos, mas o servico ja abre transacoes de origem explicitamente `READ ONLY` e termina com rollback; conta exclusiva `SELECT` sera exigencia antes de qualquer operacao com dados reais ou piloto. A rotina nao modifica usuarios ou permissoes MariaDB.

**CI comprovado:** no commit `c0333a409970dda71f9f5183ffb96c9f8578ff42`, o GitHub Actions run [44908611752](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44908611752) terminou com **ambos os jobs `success`**: `Testes PHP e interface`, incluindo os novos testes do roteiro, e `Integracao MariaDB para PostgreSQL`. Em seguida, commit documental `6a7b012` atualizou o plano e nao alterou o codigo. A branch segue no PR #5 em rascunho, sem merge.

**Status efetivo em 21/11/2026:** o roteiro esta versionado e testado, mas **a copia real dos 115 alunos ficticios do MariaDB do notebook ainda NAO foi executada nem comprovada**. Os bancos PostgreSQL `astuti_platform_dev` e `astuti_monte_sereno_dev` originais continuam sem dados, conforme a ultima captura 81. Nao alegar validacao funcional do login nem produto pronto.

**Proximo comando unico no Debian do notebook** (aguardar a captura da saida):

```bash
cd ~/astuti-escolar && git pull --ff-only origin feature/migracao-postgresql-instalacoes-isoladas && bash scripts/operacao/ensaiar-copia-isolada-postgresql.sh
```

O script pede sudo e pode levar alguns minutos. Nao executar manualmente `php artisan astuti:migrar-dados-postgresql --executar` sem o roteiro isolado. Se a rotina falhar, registrar mensagem e nao continuar a copia nos destinos originais. Depois de sucesso, conferir linha a linha, hashes, integridade e login no banco de ensaio; apenas entao revisar a futura transferencia principal.


## 21/11/2026 - Captura 82: falha de inicializacao do cluster de ensaio e correcao

**Prova no notebook:** Jefferson executou o comando unico da etapa anterior, atualizou a branch de `574401b` para `6a7b012`, validou novamente os dois backups como `OK` e passou pela verificacao `CONFERINDO BANCOS ORIGINAIS, SOMENTE LEITURA`. A rotina criou o cluster PostgreSQL 17 `astuti_ens_20261121_103015_6876` na porta **55432**, com `start-conf=manual`. O PostgreSQL recusou inicializar: `syntax error in file .../postgresql.conf line 60, near token ".0"` e `FATAL: configuration file contains errors`. A rotina interrompeu com mensagem `Ensaio interrompido. Os backups e os bancos originais foram preservados`. **Nenhum banco do cluster foi restaurado e nenhum aluno foi copiado.**

**Causa-raiz confirmada:** o roteiro invocava `pg_conftool set listen_addresses '127.0.0.1'`. Na biblioteca `PgCommon::quote_conf_value` do Debian, o padrao de numero `^-?[\\d.]+$` classifica incorretamente um IPv4 composto de digitos e pontos como numero e o escreve **sem aspas** em `postgresql.conf`. O PostgreSQL exige uma string valida para `listen_addresses` e, por isso, nao iniciou. O script foi corrigido para `pg_conftool ... set listen_addresses localhost`, identificador simples valido com escuta apenas no loopback, de acordo com a documentacao PostgreSQL. O teste shell foi alterado **antes do patch** para simular a falha observada e comprovadamente ficou vermelho; depois da correcao passou.

**Correcoes publicadas na branch:** `0056f65` (teste de regressao) e `cf7a046` (alteracao minima no roteiro); `f908b61` documentou a falha e a recuperacao. A suite shell local retornou: 8 cenarios de ensaio de copia, 6 de backup pre-copia e 8 de restauracao, todos PASS. **CI da correcao aprovado:** no commit `f908b61`, o workflow [44909385490](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44909385490) concluiu os dois jobs com `success`: testes PHP/interface (incluindo regressao shell) e integracao MariaDB/PostgreSQL. O CI nao substitui a proxima prova no notebook.

**Estado material:** o cluster de teste `astuti_ens_20261121_103015_6876` permaneceu parado na porta 55432. Foi preservado para auditoria, **nao deve ser eliminado sem identificar seu alvo explicitamente**. Como o script so escolhe portas nao reservadas, nova execucao devera utilizar uma proxima porta livre, sem sobrescrever o cluster antigo. Origem MariaDB, PostgreSQL originais na porta 5432 e dumps seguem intactos conforme o comando. **A copia real de 115 estudantes ficticios continua pendente**. Nao fazer corte ou merge do PR #5.

**Proximo passo:** apos CI do patch, no Debian, rodar novamente o mesmo comando seguro, que fara um cluster novo para o ensaio. Se houver erro, interromper, registrar saida e depurar sem tocar os bancos originais.


## 21/11/2026 - Captura 83: simulacao aprovada, copia isolada interrompida

**Evidencia real no notebook:** na branch de trabalho atualizada de `6a7b012` para `f908b61`, os dois dumps `pgsql-pre-copia-*` novamente passaram checksum. A verificacao dos bancos PostgreSQL originais passou. O roteiro criou um segundo cluster `17/astuti_ens_20261121_103538_6170` na porta **55433**, desta vez iniciou o servidor e criou o papel temporario `astuti_ensaio`. Os dois backups foram restaurados para esse cluster, sem uso da porta original 5432. A **simulacao MariaDB -> cluster isolado passou** e mostrou 1 municipio, 1 usuario, 2 tentativas, 1 vinculo, 7 auditorias centrais, 3 escolas, 1 ano, 115 alunos, 3 turmas, 115 matriculas e 237 auditorias municipais nas origens.

**Falha observada:** na fase `COPIA FICTICIA APENAS NO CLUSTER ISOLADO`, o comando `astuti:migrar-dados-postgresql --executar --confirmar=MONTE-SERENO-DEV` retornou `Migracao interrompida por falha de precondicao ou integridade`. O `catch (Throwable)` em `app/Console/Commands/MigrateFictitiousDataToPostgres.php` suprime a excecao concreta. **A causa raiz ainda nao foi identificada**: pode ser falha de SQL/constraint ou comparacao de integridade apos a insercao. O codigo preservou o cluster para auditoria e parou sua execucao. Nao presumir que os destinos internos de ensaio estejam vazios ou que transacoes dos dois bancos tenham sido revertidas: conferir antes de qualquer tentativa. Nao executar a copia novamente nem nos bancos PostgreSQL originais.

**Proxima acao objetiva e somente leitura:** consultar no notebook o log do cluster especifico `/var/log/postgresql/postgresql-17-astuti_ens_20261121_103538_6170.log`, exibindo apenas linhas `ERROR:`, `FATAL:` ou `PANIC:`, sem linhas `STATEMENT:` e sem dados de estudantes. Se nao houver erro SQL registrado, planejar diagnostico pontual e sanitizado na camada Laravel antes de nova tentativa. Preservar cluster 55433 e backups; nao limpar automaticamente. PR #5 segue em rascunho e nenhum corte ocorreu.


## 21/11/2026 - Captura 84: log SQL vazio, diagnostico seguro implementado e CI aprovado

**Saida no notebook:** Jefferson executou consulta de leitura ao log PostgreSQL do cluster de ensaio `17/astuti_ens_20261121_103538_6170` (porta 55433). A mensagem foi literalmente `NENHUM ERRO SQL REGISTRADO`. **Isso nao prova que inexiste falha SQL**; significa apenas que o log consultado nao continha linhas `ERROR:`, `FATAL:` ou `PANIC:`. Nao executar nova transferencia sem obter diagnostico da excecao Laravel.

**Trabalho no GitHub, branch `feature/migracao-postgresql-instalacoes-isoladas`:** implementada uma descricao *sanitizada* da causa em `SafeMigrationDiagnostic`, com allowlist de mensagens de integridade geradas pelo proprio programa e apenas SQLSTATE do driver, nunca texto SQL, valores de estudantes, credenciais ou mensagens arbitrarias. O marcador `ASTUTI_MIGRATION_DIAGNOSE_ISOLATED=1` e exportado somente no subprocesso do roteiro. A aplicacao so exibe o detalhe quando o ambiente e local e a configuracao corresponde ao usuario temporario `astuti_ensaio`, aos dois bancos ficticios e a uma porta entre 55432 e 55531, fora dos destinos PG originais (5432). A transferencia recebeu callback opcional que mostra **etapa e nome de tabela**, sem linhas ou valores, antes de inserir/conferir. A semantica de copia nao foi alterada e nenhum controle de seguranca foi desativado.

**TDD:** teste novo do classificador foi comprovado vermelho no CI `44910431332` por classe ausente, depois passou. Teste de callback da transferencia foi comprovado vermelho no CI `44910716936`, falha esperada `Failed asserting that an array contains 'inserindo:municipalities'`, depois passou. Teste shell do marcador foi comprovado vermelho localmente, corrigido e validado com os outros testes shell. **CI final verde** no commit `c762abb3`, workflow [44911118712](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44911118712): jobs `Integração MariaDB para PostgreSQL` e `Testes PHP e interface` com `success`, incluindo 171 testes PHP aprovados por rodada, testes de integracao de dois bancos, Pint e build/interface. Houve uma falha intermediaria apenas de estilo `single_quote`, corrigida no commit final.

**Limite da verificacao:** o CI simula bases descartaveis e usa fixture sintetica; ele **nao** reproduz necessariamente o conjunto historico de 7 auditorias centrais e 237 municipais do notebook. O comando Artisan end-to-end nos nomes `*_dev` nao foi executado no CI, por protecoes deliberadas. A falha de copia real da captura 83 **continua sem causa-raiz identificada**. Testes de borda do gate diagnostico e prova operacional final no notebook ficam pendentes. Clusters parados 55432 e 55433 devem continuar preservados; nao limpar nem mesclar PR #5.

**Proxima acao:** somente apos CI verde, rodar no Debian o roteiro de ensaio em um **terceiro cluster novo e porta livre**, com a branch atualizada; observar a ultima linha `Etapa do cluster isolado` e `Diagnostico do cluster isolado`. Se houver falha, enviar resultado e interromper. A partir da etapa/tabela identificada, corrigir a causa raiz com teste antes de novo ensaio. Os bancos PG originais 5432/MariaDB nao devem receber `--executar` manualmente.
