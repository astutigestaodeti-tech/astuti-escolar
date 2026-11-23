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


## 21/11/2026 - Captura 85: divergencia em platform_audit_logs, diagnostico por coluna criado

**Prova no notebook:** o terceiro ensaio gerou o cluster PostgreSQL isolado `17/astuti_ens_20261121_105834_239` na porta **55434**. O PostgreSQL iniciou, o usuario temporario foi criado, os dois dumps foram restaurados no cluster isolado e a simulacao MariaDB foi aprovada. Foram contados na origem: central com 1 municipio, 1 usuario, 2 tentativas de provisionamento, 1 vinculo, 0 escopos de escola e **7 logs de auditoria**; municipal com 3 escolas, 1 ano, **115 estudantes ficticios**, 3 turmas, 115 matriculas e **237 logs de auditoria**.

**Falha localizada:** durante `COPIA FICTICIA APENAS NO CLUSTER ISOLADO`, o processo concluiu insercao e conferencia das cinco primeiras tabelas centrais; interrompeu em `conferindo platform_audit_logs` com `Conteudo da tabela platform_audit_logs divergiu apos copia.`. O erro e de **comparacao de conteudo**, nao ha evidencias suficientes para afirmar qual coluna ou se houve perda semantica. A transferencia estava antes dos commits; o comando interrompeu, preservou o cluster 55434 parado para auditoria, preservou MariaDB, dumps e os PG originais na porta 5432. Nao considerar concluida a copia dos 115 estudantes.

**Correcao de diagnostico no GitHub (branch do PR #5):** foi criado o tipo `RowContentMismatchException` e a conferência `MigrationVerifier` passou a anexar **apenas nomes de colunas divergentes** a uma excecao tipada. O `SafeMigrationDiagnostic` exibe esses nomes somente para as tabelas de auditoria e somente na porta/usuario/ambiente isolados previamente autorizados; limita a exibicao a 12 nomes de coluna predefinidos, sem valores, identificadores, senhas ou SQL. A igualdade rigorosa permanece obrigatoria, nao houve copia ou alteracao de bancos por esta mudanca de codigo. Foram criados testes para ausencia de vazamento de dados, preservacao da associacao coluna/registro e equivalencia semantica de JSON. TDD vermelho demonstrado em [44911879568](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44911879568) nos dois testes novos antes da implementacao. Implementacao no commit `296db3a`; **CI final aprovado** no workflow [44912032171](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44912032171): ambos os jobs com `success`, 174 testes PHP aprovados (23 ignorados no job geral), 2 rodadas de integracao MariaDB/PostgreSQL, Pint, build de interface e testes shell. A correcao foi confirmada apenas em fixture descartavel; nao prova que a copia historica do notebook funcionara.

**Pendente:** ainda nao sabemos qual campo de `platform_audit_logs` produz a diferenca no conjunto historico local. A fixture de CI tem apenas 1 auditoria central; nao reproduz os sete eventos da origem. A proxima acao, apos CI verde, e executar o roteiro isolado no notebook com a branch atualizada e anotar apenas `Campos divergentes`. **Nao corrigir ou normalizar dados sem identificar o campo e reproduzir em teste.** Preservar os clusters 55432, 55433 e 55434 para auditoria; nao realizar corte dos PG originais e nao mesclar PR #5.


### Revisao da captura 85: precisao do diagnostico e CI final (21/11/2026)

A revisao independente das quatro alteracoes encontrou **nenhuma falha critica** e identificou dois problemas de precisao do diagnostico, sem evidencias de vazamento: (1) linhas ausentes/adicionadas eram descritas como divergencia de todas as suas colunas e (2) igualdade canonica de JSON numerico `1` e `1.0` podia ser relatada incorretamente quando outro campo divergisse. Ambos os problemas foram reproduzidos com testes vermelhos no CI [44912523608](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44912523608), com dois testes falhando como esperado. Corrigidos no commit **`235846c`**, distinguindo erro de identidade de erro de campo e usando a mesma codificacao canonica no diagnostico e na conferencia principal.

**CI final aprovado:** workflow [44912663585](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44912663585), ambos os jobs com `success`; 176 testes PHP aprovados (23 ignorados no job geral), duas rodadas de integracao MariaDB/PostgreSQL, shell, Pint e interface. A revisao lembra que a autorizacao do *diagnostico* nao substitui a autorizacao e as verificacoes fisicas do *roteiro de copia*. Nunca chamar `php artisan ... --executar` manualmente nos bancos originais na porta 5432. A prova do notebook continua pendente; o proximo passo e sincronizar a branch e rodar **somente** `scripts/operacao/ensaiar-copia-isolada-postgresql.sh` para identificar nomes de campos que diferem sem imprimir valores. Preservar clusters anteriores, dumps e bancos originais. Nao mesclar PR #5.


## 21/11/2026 - Captura 86: causa da divergencia temporal reproduzida e corrigida em teste

**Evidencia no notebook:** o quarto ensaio isolado criou o cluster `17/astuti_ens_20261121_111614_25474`, porta **55435**, restaurou os dois dumps e passou na simulacao de leitura MariaDB. Na copia transacional passou por `municipalities`, `users`, `municipality_provision_attempts`, `memberships`, `school_scopes` e falhou ao conferir os 7 eventos de `platform_audit_logs`. O novo diagnostico seguro apontou **somente `occurred_at`** como coluna divergente. O roteiro interrompeu a tentativa, preservou cluster de ensaio e declarou os bancos PG originais da porta 5432 e MariaDB intocados. Os 115 alunos ficticios NAO foram copiados em um destino definitivo.

**Causa tecnica reproduzida:** `occurred_at` tem precisao de seis casas nos bancos. A origem MariaDB pode devolver frações como `.123400`, e PostgreSQL representa o mesmo instante como `.1234`. O comparador anterior normalizava apenas frações integralmente zero (`.000000`), rejeitando frações nao nulas com zeros finais. Essa hipótese foi demonstrada em teste unitario de duas representacoes equivalentes e **em integracao real MariaDB/PostgreSQL com sete eventos de auditoria sinteticos**, obtendo a mesma excecao `Conteudo da tabela platform_audit_logs divergiu apos copia.` na primeira rodada de testes: workflow vermelho [44913274817](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44913274817), commit de testes `91dce398`.

**Correcao implementada:** o commit `f0735cb` em `feature/migracao-postgresql-instalacoes-isoladas` normaliza apenas os zeros finais da parte fracionaria de timestamps na comparacao canonica, sem alterar dados no banco, SQL, nem os microssegundos de valor nao zero. O teste garante que uma diferenca real de um microssegundo continua bloqueada e que os seis registros adicionais preservam o valor integral `to_char(...US)` apos a copia. **CI verde comprovado:** [44913413409](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44913413409), dois jobs `success`: 177 testes PHP aprovados (24 ignorados no job geral), duas rodadas de integracao com 24 testes aprovados cada, scripts shell, Pint e interface.

**Limites e proxima acao:** a equivalencia temporal foi demonstrada em ambiente descartavel, mas **o notebook ainda nao rodou a versao corrigida**. Nao afirmar que a transferencia completa passou. Proximo comando no Debian: `cd ~/astuti-escolar && git pull --ff-only origin feature/migracao-postgresql-instalacoes-isoladas && bash scripts/operacao/ensaiar-copia-isolada-postgresql.sh`. O roteiro deve usar cluster temporario novo e validar isolamento antes de qualquer copia. Nao executar Artisan `--executar` manualmente nos PG `*_dev` originais, nao apagar clusters de teste 55432-55435, nao mesclar PR #5 ou fazer corte. Se nova falha, registrar etapa e diagnostico; se passar, conferir contagens, auditoria e testes funcionais no cluster isolado antes de qualquer promocao.


## 21/11/2026 - Captura 87: copia completa aprovada no cluster PostgreSQL isolado

**Evidencia no notebook:** a execucao de `scripts/operacao/ensaiar-copia-isolada-postgresql.sh` criou e utilizou o cluster PostgreSQL de ensaio `17/astuti_ens_20261121_112332_22176` na porta exclusiva **55436**. Os dois dumps foram restaurados nesse cluster, a simulacao MariaDB -> PostgreSQL passou e a copia transacional ficticia chegou ate o fim. A saida registrou insercao e conferencia das tabelas centrais e municipais, inclusive `platform_audit_logs`, `audit_logs` e o ajuste final de `municipality_provision_attempts`. A validacao reportou **1 municipio, 3 escolas e 115 alunos ficticios copiados**; a simulacao anterior conferiu 115 matriculas, 7 auditorias centrais e 237 auditorias municipais de origem. Mensagem final: `Copia concluida e conferida em ambos os destinos PostgreSQL.`, seguida de `ENSAIO ISOLADO CONCLUIDO`.

**Preservacao:** o roteiro informa que os bancos PostgreSQL originais na porta **5432**, o MariaDB de origem e os backups nao foram alterados. O cluster 55436 foi preservado para auditoria e, conforme a rotina de finalizacao do script, **parado** ao encerrar o comando. Os clusters de tentativas anteriores 55432-55435 continuam fora de escopo de limpeza. **Nao houve corte**, nem migracao dos dados para os bancos PostgreSQL `*_dev` originais, nem merge do PR #5.

**Ainda nao demonstrado:** inicializacao e navegacao da aplicacao no notebook, login com a senha ficticia existente, controles de permissao na interface, validacao das telas de escolas/turmas/alunos usando o cluster copiado, backup pos-copia e restauracao de teste desse estado. A demonstracao publica `/demonstracao` exibe dados apenas em memoria e pode ser verificada separadamente; seu funcionamento nao prova acesso aos 115 registros copiados.

**Proxima acao:** conferir com seguranca o servidor local em `127.0.0.1:8001`, primeiro `/saude` e `/demonstracao` com sessao/cache em arquivos e configuracao temporaria, sem consultas a dados escolares; depois preparar um teste de login e consulta **explicitamente conectado ao cluster isolado 55436**, sem alterar `.env` persistente, sem usar a porta 5432 e sem expor credenciais. Nao executar `astuti:demo-preparar`, `migrate:fresh` ou o comando de transferencia manual nos bancos originais. O PR #5 permanece rascunho.


## 21/11/2026 - Captura 88: interface de login abre, credenciais demonstrativas rejeitadas

**Evidencia visual:** a pagina `Entrar no sistema` do Astuti Gestao Escolar abriu no navegador do notebook, com estilo aplicado e validacao de formulario. Ao tentar o login da conta ficticia de Monte Sereno, apareceu `As credenciais informadas nao correspondem aos nossos registros.`. **Nao houve comprovacao de login autenticado nem de consulta aos 115 alunos migrados.** Nao registrar senha ou detalhes privados da tentativa.

**Analise de configuracao:** o comando recomendado para servir a demonstracao isolou sessao/cache em arquivos e definiu `APP_ENV=local`, mas **nao substituiu `PLATFORM_DB_*`**; logo, o login real continuou dependente da conexao central configurada no `.env` do notebook. Capturas anteriores mostraram que o PostgreSQL original `astuti_platform_dev` na porta **5432** tinha zero usuarios, enquanto o ensaio com usuario ficticio copiado esta no cluster **55436**, que o roteiro para ao finalizar. O resultado e compativel com login apontando ao banco central sem a conta; **a conexao efetiva do processo e a senha nao foram verificadas nesta captura**, portanto nao afirmar causa definitiva ou senha incorreta. O `LoginController` registra uma auditoria de tentativa falha no banco central acessivel; nao presumir ausencia de escritas no banco original apos essa tentativa.

**Proximo passo seguro e imediato:** abrir `http://127.0.0.1:8001/demonstracao` para a interface sintetica independente do login, com o servidor local ja iniciado. Para validar o login e consultas reais, preparar comando/roteiro separado que ligue exclusivamente o cluster isolado **55436**, verifique nome/identidade da instancia antes de qualquer escrita, configure sessao e conexoes somente para o processo (sem alterar `.env`) e nunca faça a copia ou preparacao nos bancos `*_dev` originais na porta **5432**. Nao rodar novamente o roteiro de transferencia por causa dessa mensagem; PR #5 continua rascunho.


## 21/11/2026 - Captura 89: demonstracao visual aberta no notebook

**Evidencia visual:** o usuario abriu a pagina de demonstracao do Astuti Gestao Escolar em navegador no notebook. A captura mostra a visao geral de Monte Sereno, menu lateral com `Visao geral`, `Escolas`, `Alunos`, `Matriculas`, `Importacoes`, `Documentos` e `Indicadores`, layout e identidade visual carregados, e o aviso `Ambiente de demonstracao. Municipio ficticio, sem acesso a dados reais. Dados 100% ficticios.`. A captura comprova **renderizacao da pagina inicial demonstrativa**, mas ainda nao documenta navegacao em cada item, login autenticado, permissao de usuario nem consulta real ao banco copiado.

**Numeros da demonstracao:** 1.284 alunos, 4 escolas, 48 turmas e 26 registros para conferir sao indicadores ilustrativos gerados para a interface; **nao** correspondem ao banco PostgreSQL de ensaio, que teve 115 alunos ficticios e 3 escolas copiados no cluster `17/astuti_ens_20261121_112332_22176`, porta 55436.

**Proxima etapa tecnica:** validar inicio de sessao e consultas reais exclusivamente no cluster isolado 55436, com prechecagens de identidade, contagens e credenciais temporarias somente em processo local; preservar arquivos `.env`, bancos PostgreSQL originais 5432, MariaDB, backups e clusters antigos. O PR #5 continua sem corte ou merge.


## 21/11/2026 - Captura 90: inventario local confirma o ensaio 55436 desligado

**Evidencia do comando `pg_lsclusters` no segundo terminal Debian:** PostgreSQL 17 `main` na porta **5432** esta `online`. Os cinco clusters de ensaio estao presentes e `down`: `astuti_ens_20261121_103015_6876` (55432), `astuti_ens_20261121_103538_6170` (55433), `astuti_ens_20261121_105834_239` (55434), `astuti_ens_20261121_111614_25474` (55435) e **`astuti_ens_20261121_112332_22176` (55436)**. Os diretórios de dados e logs de cada um aparecem individualmente. O servidor Laravel demonstrativo segue em outro terminal; a imagem confirma a existencia dos clusters, **nao** a conectividade ao 55436 ou o login.

**Proximo passo nao destrutivo:** iniciar especificamente o cluster de ensaio 55436 por `sudo pg_ctlcluster --skip-systemctl-redirect 17 astuti_ens_20261121_112332_22176 start` e fazer consultas `SELECT` via socket com `psql -p 55436` para verificar `users`, `schools` e `students` antes de configurar um teste de login vinculado so a esse cluster. Nao executar operacoes nos demais clusters nem modificar `.env`; o `main` 5432 permanece separado.

## 21/11/2026 - Captura 91: cluster ficticio 55436 iniciado e dados conferidos no notebook

**Evidencia visual:** no segundo terminal Debian, o usuario iniciou exatamente `17/astuti_ens_20261121_112332_22176` com `sudo pg_ctlcluster --skip-systemctl-redirect ... start`. As consultas `SELECT` via socket Unix, `-p 55436`, retornaram **`astuti_platform_dev | 1 usuario`** e **`3 escolas | 115 alunos`** em `astuti_monte_sereno_dev`. Isso confirma a disponibilidade do banco copiado de demonstracao no cluster de ensaio distinto; nao comprova login autenticado nem acesso aos dados na interface. O PostgreSQL principal segue atribuido a 5432, sem ordem de migracao definitiva.

**Proximo passo correto:** criar comando de inicio do Laravel estritamente no 55436, com teste previo de identidade fisica e contagens, autenticacao temporaria de desenvolvimento no banco copiado e sessao distinta da demonstracao 8001. Nunca gravar a senha em chat ou GitHub.

## 21/11/2026 - Captura 92: roteiro de login isolado criado, testado e publicado no GitHub

**Implementacao real na branch `feature/migracao-postgresql-instalacoes-isoladas`:**

- `scripts/operacao/testar-login-isolado-postgresql.sh`: exige cluster nomeado `astuti_ens_20261121_112332_22176` online em **55436**, compara `pg_control_system().system_identifier` com PostgreSQL principal **5432**, confere 1 municipio, 1 usuario ativo com e-mail `demo.monte-sereno@astuti.invalid`, 1 vinculo, 3 escolas, 115 alunos e 115 matriculas. Sem aprovacao do preflight, para antes de rotacionar qualquer senha.
- No processo local de ensaio, gera credenciais aleatorias e modifica **apenas** `astuti_ensaio` e o hash de senha do **usuario ficticio copiado** em 55436. Valida Laravel e conexoes efetivas antes de alterar o hash; nao muda `.env`, nao executa migracao, nao toca MariaDB, backups ou PostgreSQL principal. Usa cookie de sessao exclusivo `astuti_ensaio_55436` e servidor HTTP **127.0.0.1:8002**, separado da demo sintetica em 8001. Imprime a senha **somente no terminal Debian** (nao fotografar/divulgar).
- `tests/Shell/TestarLoginIsoladoPostgresqlTest.sh`: **5 cenarios** Bash simulados passando localmente: cluster parado, identidade coincidente, contagens divergentes, Laravel inseguro e inicializacao valida. Testes anteriores tambem passaram: 9 copia isolada, 8 restauracao, 6 backup. O workflow `.github/workflows/testes.yml` passou a executar o novo teste.
- **GitHub Actions confirmado verde na execucao #352:** [44915694895](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44915694895), commit `786ee7d`, que contem script e teste (2 commits anteriores) e a integracao ao CI. A atualizacao posterior de documentacao nao altera o comportamento de teste.
- Documentacao operacional na mesma branch, secao `Testar login e consultas reais no notebook, sem corte`, atualizada.

**Comando para o notebook, com o cluster 55436 ja online** (nao usar a janela ocupada com o servidor 8001):

```bash
cd ~/astuti-escolar && git pull --ff-only origin feature/migracao-postgresql-instalacoes-isoladas && bash scripts/operacao/testar-login-isolado-postgresql.sh
```

Depois, abrir **http://127.0.0.1:8002/login**, usar e-mail e senha impressos no proprio terminal. Deixar terminal aberto; Ctrl+C encerra servidor. **Estado pendente:** o comando ainda nao foi executado no notebook neste chat; login, navegacao em listas reais e permissao autenticada ainda nao estao validados. Os testes automatizados Bash nao equivalem a teste integrado no notebook. PR #5 permanece rascunho; sem corte, merge, alteracao de `.env` ou exclusao dos clusters anteriores.

## 21/11/2026 - Captura 93: incompatibilidade psql corrigida antes de qualquer escrita

**Capturas do notebook:** o usuario executou `git pull --ff-only` na branch `feature/migracao-postgresql-instalacoes-isoladas` e iniciou `bash scripts/operacao/testar-login-isolado-postgresql.sh`. O Git atualizou corretamente quatro arquivos. Na primeira leitura de identidade, o PostgreSQL respondeu literalmente `psql: unrecognized option '--database=postgres'` e o roteiro encerrou com `ERRO: identidade principal indisponivel.`. O navegador em `127.0.0.1:8002/login` mostrou `ERR_CONNECTION_REFUSED` porque o servidor da porta 8002 **nunca chegou a iniciar**. O fluxo parou **antes de alterar** papel PostgreSQL ou senha ficticia.

**Causa confirmada:** o script novo usou `--database=` em tres invocacoes da CLI `psql`; esta opcao nao existe no `psql` 17 do Debian. O projeto ja utilizava corretamente `-d` em outros roteiros. O teste Bash anterior nao reproduzia a incompatibilidade, pois o mock aceitava toda opcao enviada.

**Correcao publicada no GitHub, mesma branch:** teste de regressao passou a rejeitar `--database=` nas chamadas simuladas a `psql` e **falhou antes da alteracao**, reproduzindo o sintoma. Em seguida, as tres chamadas foram corrigidas para `-d`, inclusive a leitura de identidade, contagens e alteracao de role via stdin; commits `cf733b6` (teste) e `ab82dc5` (script). Em nova execucao local, **5 cenarios de login, 9 de copia, 8 de restauracao e 6 de backups passaram**, alem de `bash -n`. Testes locais usam mocks porque o executor nao possui PostgreSQL; nao afirmar login real ate a proxima captura do Debian.

**Comando unico corrigido para o notebook** (cluster 55436 online, outro terminal nao ocupado pelo servidor 8001):

```bash
cd ~/astuti-escolar && git pull --ff-only origin feature/migracao-postgresql-instalacoes-isoladas && bash scripts/operacao/testar-login-isolado-postgresql.sh
```

A senha temporaria, quando aparecer, deve permanecer somente no notebook. Nao enviar capturas que mostrem credenciais. O PR #5 continua rascunho, sem corte, migracao nos bancos originais 5432 ou exclusao de backups/clusters.

## 21/11/2026 - Captura 94: autenticacao concluida no Laravel real (porta 8002)

**Evidencia visual enviada pelo usuario:** a pagina autenticada mostra `Ola, Secretaria Escolar Ficticia` e a selecao da rede `Prefeitura Municipal de Monte Sereno`, incluindo as opcoes de seguranca da conta e sair. O ensaio anterior de PostgreSQL isolado havia confirmado 1 usuario na plataforma, 3 escolas e 115 alunos no banco municipal. **Agora o login foi efetivamente demonstrado na interface**, no ambiente de desenvolvimento com dados ficticios. Essa tela nao comprova ainda todos os controles de permissao por acesso direto, nem a abertura da lista dos 115 alunos.

## 21/11/2026 - Captura 95: listagem autenticada de tres escolas autorizadas

**Evidencia visual enviada pelo usuario:** a tela `Escolas autorizadas` da Prefeitura Municipal de Monte Sereno apresenta `3 unidades`, todas com selo `Ativa`: **EMEF Caminhos do Saber**, **EMEF Rio Claro** e **EMEI Jardim das Flores**. A interface identifica a sessao `Secretaria Escolar Ficticia`, ambiente de desenvolvimento e dados ficticios. A mensagem da pagina informa verificacao de permissoes em cada abertura, mas a imagem **nao constitui teste de tentativa de acesso nao autorizado**. A listagem das escolas no fluxo autenticado esta visualmente concluida. Permanecem pendentes a abertura de uma unidade, consulta aos alunos e matriculas reais do cluster copiado e testes negativos de autorizacao.

## 21/11/2026 - Captura 96: senha de demonstracao escolhida, apenas no ensaio isolado

**Mudanca implementada na branch `feature/migracao-postgresql-instalacoes-isoladas`:** o roteiro `scripts/operacao/testar-login-isolado-postgresql.sh` agora aceita opcionalmente `ASTUTI_DEMO_PASSWORD` como senha alfanumerica de 6 a 64 caracteres **somente para o usuario ficticio do cluster PostgreSQL de ensaio 55436**. Sem essa variavel, gera senha temporaria aleatoria como antes. A senha do role PostgreSQL continua independente e aleatoria. A variavel escolhida e removida do ambiente antes do `php artisan serve`. Preflight de identidade fisica, municipio, usuario, vinculo, escolas e alunos permanece obrigatorio e recusa gravacoes fora do cluster copiado. Nao altera `.env`, banco principal 5432, MariaDB original, backups ou clusters antigos.

**Verificacao local concluida:** regressao introduzida antes da alteracao e observada falhando, depois **7 cenarios de login + 9 de copia isolada + 8 de restauracao + 6 de backups** passaram em mocks, alem de `bash -n`. A alteracao foi publicada na branch; **a senha solicitada ainda nao foi aplicada no notebook** e nao se deve publicar senhas reais no repositorio.

**Proxima acao operacional:** no terminal do servidor real da porta 8002, pressionar `Ctrl+C`, atualizar com `git pull --ff-only` a branch e reiniciar exclusivamente o roteiro de login com `ASTUTI_DEMO_PASSWORD` definida para a senha ficticia desejada. Conferir novo login e depois navegar por `Escolas -> Abrir escola -> Alunos`, comparando com 3 escolas/115 alunos no banco isolado. O PR #5 permanece rascunho, sem corte/merge nem alteracao dos bancos originais.

## 21/11/2026 - Capturas 97 a 100: consulta autenticada da unidade, alunos e turmas no notebook

**Novas evidencias visuais da aplicacao real em ambiente de desenvolvimento, usando somente pessoas ficticias:**

- **Detalhes da escola:** ao abrir `EMEF Caminhos do Saber` pela listagem autorizada, a pagina identifica a unidade como **Ativa / Em funcionamento**, com `Codigo INEP: Nao informado`, e disponibiliza as consultas de turmas e alunos. Essa lacuna no codigo INEP e um dado do conjunto de demonstracao, nao prova de erro funcional.
- **Alunos:** o usuario entrou em `Alunos de EMEF Caminhos do Saber`. O cabecalho da pagina informa **45 alunos** com vinculo ativo. A primeira pagina mostra `Estudante ficticio 001` em diante, com anos de nascimento; a continuacao fotografada apresenta ate `Estudante ficticio 025` e o rodape `Pagina 1 de 2`. Logo, **a tela de consulta e a primeira pagina da lista funcionam**. O botao `Proxima` e o campo de pesquisa aparecem, mas as imagens nao comprovam clique na pagina 2 nem execucao de pesquisa.
- **Turmas:** a consulta autenticada `Turmas de EMEF Caminhos do Saber` mostra **1 turma ativa**, `1º Ano A`, `Ensino Fundamental`, modalidade `Regular`, turno `Manha`, `45 vagas`, ano letivo `2027`. O ano 2027 e exibido no dado da turma; nao assumir que e defeito sem confrontar a regra de ano letivo. O numero de vagas nao equivale por si so a quantidade de matriculados.
- As paginas exibem aviso de desenvolvimento e **somente consulta, por enquanto**. Inclusao/alteracao de alunos, matriculas, anos letivos e turmas seguem explicitamente indisponiveis ate implementar autenticacao reforcada, auditoria e autorizacoes correspondentes. Nao classificar essas operacoes como prontas.

**Marco funcional verificado:** login -> municipio -> escolas -> unidade -> alunos e turmas, todas as telas observadas com conta ficticia. A contagem municipal de **115 alunos** ja havia sido comprovada por `SELECT` no cluster isolado **55436**; as imagens atuais comprovam **45 na primeira escola**, nao as outras 70 nem total na interface. Testes de busca, paginacao, abertura das outras duas escolas e acesso direto nao autorizado ainda pendentes.

**Proxima verificacao nao destrutiva:** usar o botao `Proxima` e confirmar pagina 2 de 2 na EMEF Caminhos do Saber; acessar Alunos das outras duas escolas e confrontar somatorio com 115; testar busca por nome e tentativa de URL de escola sem permissao em conta ficticia restrita. Nao executar migracao, nem alterar dados reais, bancos originais, backups, `.env` ou PR #5 em rascunho.

## 21/11/2026 - Validacao visual completa das tres escolas, 115 alunos e paginacao (12 imagens 100-111)

**Evidencia nova do notebook em ambiente autenticado:** o usuario navegou pelas paginas de detalhes, alunos e turmas das tres unidades ficticias da rede Monte Sereno e enviou capturas das duas paginas das listas de alunos. As contagens exibidas nas proprias interfaces conferem exatamente com os **115 alunos** anteriormente verificados por `SELECT` no PostgreSQL isolado de ensaio **55436**.

| Unidade ficticia | Alunos ativos na interface | Turma ativa exibida | Etapa, modalidade e turno | Vagas | Ano |
| --- | ---: | --- | --- | ---: | --- |
| EMEF Caminhos do Saber | **45** | 1º Ano A | Ensino Fundamental, Regular, Manha | 45 | 2027 |
| EMEI Jardim das Flores | **40** | Infantil IV A | Educacao Infantil, Regular, Tarde | 40 | 2027 |
| EMEF Rio Claro | **30** | 2º Ano A | Ensino Fundamental, Regular, Integral | 30 | 2027 |
| **Total** | **115** | **3 turmas mostradas, uma por unidade** | - | **115 vagas** | - |

**Paginacao agora efetivamente observada:** a segunda pagina de `EMEF Caminhos do Saber` exibe estudantes ficticios **026-045** e `Pagina 2 de 2`; a segunda pagina da `EMEI Jardim das Flores` exibe estudantes **071-085**, tambem `Pagina 2 de 2`; a segunda pagina da `EMEF Rio Claro` exibe estudantes **111-115**, `Pagina 2 de 2`. As primeiras paginas das tres escolas tambem foram fotografadas, com intervalos de alunos respectivamente **001-025**, **046-070** e **086-110**. Os nomes ficticios e as contagens fecham a sequencia **001-115**, sem afirmar que as imagens substituem testes automatizados de integridade.

**Detalhes de unidade:** as escolas Rio Claro e Jardim das Flores exibem estado `Ativa / Em funcionamento`, rotas para alunos e turmas, e `Codigo INEP: Nao informado` no cadastro ficticio. A primeira escola ja havia sido registrada na etapa anterior. Todas as turmas apresentadas sao de 2027; registrar o ano como valor do dado de demonstracao, nao presumir defeito. **Vaga nao equivale necessariamente a matricula** fora deste conjunto de teste.

**Marco alcançado:** fluxo autenticado completo de consulta foi demonstrado visualmente para as tres escolas, suas turmas e listas paginadas de alunos, com contagens compativeis com a copia isolada. **Ainda pendente e nao implementado:** cadastro e alteracao de alunos/turmas/matriculas, importacao e emissao de documentos, autenticacao reforcada e trilha de auditoria para escritas. **Ainda nao testado:** busca por nome, autorizacao negativa usando usuario restrito e URLs diretas de outra escola, acessibilidade/responsividade e testes automatizados de ponta a ponta.

**Proximo passo de desenvolvimento:** consolidar testes de autorizacao e pesquisa/listas de consulta sobre o ambiente ficticio, e implementar as proximas funcionalidades conforme plano aprovado com testes e revisao. Nao executar migracao definitiva, nao alterar PostgreSQL principal 5432, MariaDB, `.env`, backups ou clusters historicos; PR #5 segue rascunho.

## 21/11/2026 - Busca de alunos compatível com maiúsculas e minúsculas no PostgreSQL

**Problema identificado:** a consulta de alunos usava `LIKE`, sensível a diferenças de maiúsculas e minúsculas no PostgreSQL. Um termo como `mari` não encontrava `Maria do Campo`. O controle de acesso por escola, município e vínculo ativo já existia e tinha cobertura automatizada.

**Correção na branch `feature/migracao-postgresql-instalacoes-isoladas`:** plano `docs/superpowers/plans/2026-11-21-busca-alunos-sem-distincao-maiusculas-postgresql.md`; teste regressivo no commit `6a1f483` e ajuste de `SchoolStudentController` no commit `b89fe64`. Para o driver `pgsql`, a pesquisa parcial passa a usar `ILIKE`; outros drivers continuam com `LIKE`. O escape dos caracteres `%` e `_`, o limite de 120, a paginação de 25, a filtragem por vínculo ativo e a autorização foram preservados.

**TDD real confirmado no GitHub Actions:** no commit só com o teste, a execução [44919446199](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44919446199) falhou exatamente na asserção `Maria do Campo` (177 testes passaram, 1 falhou). Após a correção, a execução [44919611025](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44919611025) ficou verde: **178 testes Laravel aprovados em ambas as rodadas, 633 asserções por rodada, 24 testes ignorados por critérios ambientais**, Pint verificou 132 arquivos, build e quatro testes UI passaram e a integração MariaDB → PostgreSQL passou.

**Limites:** a busca ainda não foi conferida manualmente no notebook pelo Jefferson; testes automáticos de autorização por URL direta já existem, mas a negativa ainda não foi simulada pelo usuário na interface real. Sem gravações nos bancos do notebook, sem acesso a dados reais de menores e sem merge do PR #5, que permanece draft.

**Próximo incremento:** desenvolver ficha individual de consulta do aluno com link pela lista e vínculo autorizado, mantendo a aplicação somente leitura.

## 21/11/2026 - Ficha individual de aluno em consulta segura

**Novo incremento entregue na branch `feature/migracao-postgresql-instalacoes-isoladas`:** `GET /m/{municipio}/escolas/{escola}/alunos/{aluno}` apresenta ficha de aluno com nome, ano de nascimento opcional, escola e anos letivos de vínculos ativos. A listagem de alunos agora contém link por nome. Somente leitura, sem novas migrações, cadastro ou escrita.

**Controle de acesso:** middleware de município e autenticação, autorização da escola ativa via `Gate` e busca do aluno condicionada a matrícula ativa na mesma escola. Acesso de escola não autorizada retorna 403, aluno de outra escola ou vínculo inativo retorna 404, visitante vai ao login. Dados variáveis são escapados por Blade; vínculo de outra escola não é carregado.

**Desenvolvimento TDD:** o commit `7006891` contém quatro testes de integração; a execução [44920622258](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44920622258) falhou como esperado em quatro cenários porque a rota ainda não existia (178 testes anteriores passaram). Implementação nos commits `fd42a5c` (rota), `f625da4` (controller), `7863e42` (view) e `b70cf3b` (link na lista). A execução [44920820454](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44920820454) passou: **182 testes PHP em duas rodadas, 653 asserções por rodada, 24 ignorados por ambiente, Pint 133 arquivos, build, quatro testes UI e integração MariaDB → PostgreSQL**. PR #5 permanece draft.

**Revisão independente do diff:** nenhum problema crítico ou importante. Um aprimoramento menor foi registrado para futura cobertura: aluno com vínculos ativos simultâneos em duas escolas e um vínculo antigo encerrado não deve exibir histórico da outra unidade. O controller já restringe por escola e status; o novo teste de regressão pode ser adicionado posteriormente.

**Pendente no notebook:** atualizar o código da mesma branch de ensaio e conferir visualmente a ficha no ambiente fictício 55436 / interface 8002. Nenhum banco original foi alterado, e a tela não deve receber dados reais de menores.

## 21/11/2026 - Contagem de alunos ativos por escola autorizada

**Nova melhoria na branch de ensaio:** os cartões de `GET /m/{municipio}/escolas` apresentam a contagem de alunos ativos da unidade. A contagem usa subconsulta PostgreSQL correlacionada a cada escola, com `Student::query()->whereHas('enrollments')`, logo considera cada aluno uma única vez mesmo quando tenha duas matrículas ativas na mesma unidade. Vínculos encerrados não contam, alunos sem matrícula ativa não contam e o filtro de escolas autorizadas permanece.

**TDD e evidências:** testes novos no commit `1607d2b` falharam primeiro na execução [44921401296](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44921401296), por ausência dos textos esperados `2 alunos ativos` e `0 alunos ativos` (182 testes prévios passaram). Implementação `5edbad6` (controller) e `b13ea12` (view). Após a correção, [44921547716](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44921547716) ficou verde: **184 testes Laravel passando em duas rodadas, 663 asserções por rodada, 24 testes condicionais ignorados, Pint 134 arquivos, build, 4 testes UI e integração MariaDB → PostgreSQL**.

**Teste local ainda necessário:** ao atualizar a branch no Debian e recarregar a página Escolas da interface fictícia 8002, os cartões devem exibir **45 alunos ativos** na Caminhos do Saber, **40** na Jardim das Flores e **30** na Rio Claro, totalizando 115 alunos fictícios previamente verificados. São expectativas com base no ensaio anterior, não uma nova leitura remota do notebook. A feature não modifica qualquer banco original, arquivo de backup ou `.env`. PR #5 continua draft.

**Próximas frentes:** conferir a tela individual e as novas contagens no notebook, e evoluir o cadastro escolar somente após regras explícitas de gravação auditada e validação de 2FA.

**Revisão independente complementar da contagem:** nenhum achado crítico ou importante; dois pontos menores foram adiados para os próximos testes: (1) gestor que vê simultaneamente duas escolas com contagens diferentes, para proteger a correlação por escola; (2) alunos/matrículas com exclusão lógica (`deleted_at`) para proteger a aplicação dos escopos de `SoftDeletes`. O código atual foi considerado correto, inclusive na correlação SQL e nos índices, mas não houve `EXPLAIN ANALYZE` com base grande nem inspeção visual do notebook. Revisão confirmou que o PR #5 permanece draft.

## 21/11/2026 - Evidência visual no notebook: contagens e sincronização

**Captura 112, enviada pelo usuário:** a tela autenticada de `Escolas autorizadas` em Monte Sereno exibiu **45 alunos ativos** na EMEF Caminhos do Saber, **30** na EMEF Rio Claro e **40** na EMEI Jardim das Flores, totalizando **115 alunos fictícios**. A imagem confirma que a nova contagem por escola está disponível e coerente no notebook, além dos testes automatizados já aprovados. Não valida, por si só, busca sem distinguir maiúsculas, ficha individual de aluno ou tentativas de acesso negado.

**Captura 113, enviada pelo usuário:** terminal Debian executou o procedimento guardado de conferência de branch/árvore limpa seguido de `git pull --ff-only origin feature/migracao-postgresql-instalacoes-isoladas`. A atualização foi **fast-forward `a890083..b13ea12`**, com 14 arquivos atualizados e retorno ao prompt sem erro. Confirma a atualização local até o commit da interface de contagem.

**Próximo passo de validação no notebook:** abrir uma escola fictícia, consultar os alunos, clicar no nome de um estudante para conferir a ficha individual de leitura e testar pesquisa pelo termo `estudante` em minúsculas. Não usar dados reais de menores. PostgreSQL original 5432, MariaDB, backups e `.env` permanecem fora de escopo; PR #5 continua draft.

## 21/11/2026 - Validação visual da ficha individual no notebook

**Captura 114, enviada pelo usuário:** a navegação autenticada `Escolas > EMEF Caminhos do Saber > Alunos > Ficha do aluno` abriu a ficha de **Estudante fictício 001** no ambiente de desenvolvimento do notebook. A interface mostra **ano de nascimento 2017**, **escola EMEF Caminhos do Saber**, status **Vínculo ativo** e **Ano letivo 2027**. O retorno à lista de alunos está disponível e a página informa que é **somente consulta**. Portanto, a ficha individual está visualmente validada nesse percurso com dados fictícios.

**Limites da evidência:** a imagem não comprova ainda os cenários negativos de autorização (403/404), nem a busca em minúsculas em PostgreSQL. Esses cenários foram abordados por testes de integração, mas a busca permanece pendente de prova manual no notebook.

**Próximo teste manual:** voltar à lista de alunos e pesquisar `estudante` em minúsculas; conferir o resultado e a paginação. Nenhuma alteração no PostgreSQL principal 5432, MariaDB, backups ou `.env`; PR #5 permanece draft.

## 21/11/2026 - Conferência visual da busca de alunos em minúsculas

**Captura 115, enviada pelo usuário:** na tela autenticada `Escolas > EMEF Caminhos do Saber > Alunos`, o campo de pesquisa contém `estudante` (todas as letras minúsculas), enquanto os resultados exibem `Estudante fictício 001`, `Estudante fictício 002` e demais nomes iniciados por letra maiúscula. O contador indica **45 alunos** da escola. Evidência visual consistente com a correção `ILIKE` da busca no PostgreSQL e com a ficha individual já validada pela captura 114.

**Limite da captura:** não mostra a URL com os parâmetros de busca nem a ação de submissão; portanto é evidência da interface com o termo e resultados visíveis, complementando os testes automatizados da consulta. Para conferência manual discriminante futura, pesquisar um termo que restrinja os resultados, como `fictício 04`, e comparar a contagem da lista.

**Marco funcional:** cartões com contagens por escola (captura 112), ficha individual com vínculo ativo (captura 114) e pesquisa por texto em minúsculas (captura 115) demonstrados visualmente no ambiente fictício de notebook, com os testes automatizados aprovados. Nenhuma mudança em dados reais, PostgreSQL principal 5432, MariaDB, backups ou `.env`; PR #5 continua draft.

**Próxima frente:** planejar e desenvolver as próximas funcionalidades de consulta e o alicerce de autenticação reforçada/auditoria antes de liberar cadastro ou alteração de alunos.

## 21/11/2026 - Segundo fator confirmado antes de consultas protegidas

**Correção de segurança na branch de ensaio `feature/migracao-postgresql-instalacoes-isoladas`:** a autenticação TOTP, recuperação e a trilha de auditoria já existiam, mas uma conta com TOTP habilitado podia abrir `/` e as rotas municipais de escolas, turmas e alunos por URL direta antes de concluir o desafio. O middleware `RequireLoginTwoFactor` passa a exigir confirmação válida da sessão *antes* da resolução do município e das consultas. Contas ainda sem TOTP e visitantes preservam os acessos anteriores; não foi habilitada escrita.

**Revisão independente e correções adicionais:** a primeira revisão identificou possível retorno indevido via `Referer` em requisições JSON/HEAD e o link de volta que criava ciclo no desafio. A implementação final grava apenas caminho e consulta internos para GET de navegação, responde **403** para JSON sem TOTP, não usa o `Referer` em HEAD e oferece `Sair da conta` via POST. Um teste com município inexistente comprova que o desafio é exigido antes da consulta municipal. Sessões TOTP expiram após 15 minutos, conforme a regra existente.

**TDD e verificação:** teste inicial [44923347336](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44923347336) reproduziu três violações; depois [44923532996](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44923532996) e [44923537141](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44923537141) passaram com 188 testes e 695 asserções em cada execução. Testes adicionais para URL de retorno, JSON, HEAD e ordem do middleware reproduziram três falhas em [44924023257](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44924023257). Versão final no commit `908f203`, [CI verde 44924396571](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44924396571): **192 testes PHP em duas execuções, 708 asserções cada, 24 testes condicionais ignorados, Pint 136 arquivos, build da interface, 4 testes UI e integração MariaDB → PostgreSQL aprovada**. A revisão não identificou bypass nas rotas protegidas; dois achados de navegação foram corrigidos.

**Limites:** ainda não houve ensaio manual deste incremento no notebook; não ativar TOTP na conta fictícia sem registrar os códigos de recuperação. Nenhum banco original PostgreSQL 5432, MariaDB, backup ou `.env` foi modificado. Nenhum corte de dados ou nova migração de esquema. [PR #5](https://github.com/astutigestaodeti-tech/astuti-escolar/pull/5) continua draft, sem merge.

## 21/11/2026 - Trilha de auditoria para abertura de ficha individual

**Segundo marco do ciclo de segurança na branch `feature/migracao-postgresql-instalacoes-isoladas`:** uma consulta autorizada da ficha `GET /m/{municipio}/escolas/{escola}/alunos/{aluno}` agora acrescenta ao audit log imutável do respectivo município um evento `student.viewed`, com identificador do aluno (UUID), conta responsável, papel, instante e metadados de requisição já previstos no serviço `AuditTrail`. Não inclui nome, data de nascimento, matrícula ou snapshots do aluno. A gravação ocorre apenas depois de confirmar acesso à escola e vínculo ativo; falha da auditoria impede concluir a consulta silenciosamente.

**Casos negativos:** aluno de outra escola, escola proibida e conta com TOTP ativado ainda sem desafio não registram consulta bem-sucedida. O evento é gravado exclusivamente no banco municipal ativo, nunca no de outro município. Sem abertura de edição ou cadastramento.

**Prova TDD:** [RED 44924927676](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44924927676) confirmou falta do evento (1 teste novo falhou, 194 passaram); implementação no commit `429b2c9`. [GREEN final 44925099952](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44925099952): **195 testes PHP aprovados em cada uma de duas rodadas, 721 asserções por rodada, 24 testes condicionais ignorados, Pint 136 arquivos, build, 4 testes UI e integração MariaDB → PostgreSQL aprovada**. Revisão independente aprovada sem achados críticos/importantes.

**Aprimoramentos menores adiados:** (1) acrescentar verificação explícita de zero eventos após tentativa de visitante; (2) simular falha do AuditTrail para provar que não se retorna a ficha sem registrar evento. A implementação atual nega visitante antes do controller e não captura exceção de auditoria, comportamentos já inspecionados na revisão.

**Operação e limites:** teste manual ainda pendente no notebook. A trilha de consulta grava registros apenas quando o código novo estiver ativo em ambiente de ensaio; nenhum PostgreSQL original 5432, MariaDB, backup, `.env` ou dado real de aluno foi acessado/modificado nesta entrega. Sem migração de esquema, sem merge. [PR #5](https://github.com/astutigestaodeti-tech/astuti-escolar/pull/5) continua draft.


## 21/11/2026 - Histórico de vínculos escolares na ficha individual

**Evolução da consulta:** na branch isolada `feature/migracao-postgresql-instalacoes-isoladas`, a ficha de aluno passou a exibir, dentro da escola autorizada, seus vínculos ativos e anteriores, com ano letivo, situação em português e datas de início/encerramento quando registradas. Uma matrícula de outra unidade não aparece mesmo que pertença ao mesmo aluno.

**Condição de segurança inalterada:** a escola da URL precisa estar autorizada e o aluno precisa ter ao menos um vínculo **ativo nessa escola** antes da abertura. Apenas depois dessa validação o controlador carrega vínculos de todas as situações filtrando `school_id` pela mesma unidade. TOTP, isolamento municipal, auditoria `student.viewed`, páginas somente leitura e exclusão lógica continuam vigentes. Nenhuma turma é inferida, pois matrícula não contém identificador de turma.

**TDD e CI:** teste regressivo novo no commit `0f3fce1` reproduziu a falta do histórico em [RED 44926162809](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44926162809): **1 teste falhou, 195 passaram**. Código e view nos commits `a1f900d` e `1a5dadd`; [GREEN 44926359914](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44926359914): **196 testes PHP em duas execuções, 730 asserções por execução, 24 testes condicionais ignorados, Pint, build e verificações UI aprovados**. Job de integração MariaDB → PostgreSQL também passou com **24 testes em duas rodadas**, bancos descartáveis. O cenário novo comprova 2027 ativo e 2026 concluído com datas somente na escola A, ocultando 2025 da escola B.

**Operação:** falta conferência visual dessa nova apresentação no notebook. O banco fictício atual demonstrado possui apenas vínculos de 2027, portanto o novo histórico de 2026 só aparecerá se houver um vínculo anterior no próprio ambiente de ensaio; não criar dados fictícios adicionais automaticamente. Nenhum PostgreSQL principal 5432, MariaDB original, backup ou `.env` foi modificado. Nenhuma migração nova, nenhuma gravação em dados reais e nenhum merge. [PR #5](https://github.com/astutigestaodeti-tech/astuti-escolar/pull/5) continua draft.

**Revisão independente concluída:** parecer favorável, sem achados críticos/importantes. Duas lacunas menores de teste ficam registradas para fortalecimento posterior: (1) matrícula ativa somente em escola diferente com vínculo concluído na escola consultada deve negar a ficha; (2) verificar explicitamente a ordem decrescente dos anos via `assertSeeInOrder()`. O código atual já bloqueia o primeiro cenário pelo `whereHas` da escola e já ordena com `sortByDesc`; os testes adicionais não são bloqueantes para a entrega em rascunho.


## 21/11/2026 - Consulta somente leitura de matrículas ativas por escola

**Nova funcionalidade na branch de ensaio `feature/migracao-postgresql-instalacoes-isoladas`:** página `GET /m/{municipio}/escolas/{escola}/matriculas`, acessível pelo cartão de cada escola autorizada. Lista uma linha por matrícula ativa, com nome do aluno (link para ficha), ano letivo, situação e início quando cadastrado. Busca por nome literal (PostgreSQL `ILIKE`, protegendo `%` e `_`), ordenação estável por ano, nome e UUID e paginação de 25 vínculos. Alunos excluídos logicamente não aparecem. Vínculos concluídos e de outras escolas não são apresentados.

**Segurança:** rota usa os mesmos middlewares de autenticação, TOTP e resolução municipal; controlador exige escola ativa e `Gate::authorize('view')`. Consulta de `StudentEnrollment` sempre filtra `school_id` e `status = active` no banco municipal; nenhum endpoint de criação/edição foi implementado e POST de matrícula continua 405. Não há nova migração nem alteração de dados reais.

**Prova TDD:** [RED 44928913940](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44928913940) reproduziu quatro falhas 404 pela rota ausente, enquanto **196 testes existentes passaram**. Implementação nos commits `e703a2c`, `e117858`, `c5cb4b5` e `ddc07fd`. [GREEN 44929178490](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44929178490) aprovou **200 testes Laravel × 2, 762 asserções em cada rodada, 24 testes condicionais ignorados, Pint, build, verificações UI e integração MariaDB → PostgreSQL em bancos descartáveis (24 testes × 2, 242 asserções/rodada)**.

**Notebook:** ainda não há captura visual dessa nova página. Após atualizar a branch com árvore limpa, abrir uma das três escolas do ensaio Monte Sereno e clicar em `Consultar matrículas desta escola`; são esperadas 45, 30 e 40 matrículas ativas respectivamente se o banco fictício seguir com um vínculo ativo para cada um dos 115 alunos, como na preparação anterior. Estas contagens são expectativa, não verificação nova de banco. Nenhum PostgreSQL original na porta 5432, MariaDB, backup ou `.env` foi modificado. Em produção continua **uma VPS e PostgreSQL exclusivos para cada Prefeitura**. [PR #5](https://github.com/astutigestaodeti-tech/astuti-escolar/pull/5) segue draft, sem merge.

**Revisão independente:** aprovada sem achados críticos/importantes. Três itens menores de prevenção de regressões foram adiados: (1) testes diretos de matrícula e aluno com exclusão lógica; (2) cobertura de `_`/barra invertida, ano/UUID na ordenação, busca preservada na paginação e busca maior que 120 caracteres; (3) teste explícito de TOTP na nova rota. O código atual usa escopo de exclusão lógica, entrada SQL vinculada, ordenação estável e middleware TOTP; nenhum bypass foi identificado na revisão.

## 21/11/2026 - Datas do histórico do aluno não ultrapassam o cartão

**Problema observado no notebook:** em `Escolas > Alunos > Ficha do aluno`, o campo `Início: 01/02/2027` aparecia para fora do cartão estreito `Histórico de vínculos nesta escola`. Causa confirmada: a regra compartilhada `.documentos li` organizava etiquetas e datas em `display:flex` sem quebra, com preenchimento lateral adicional em um cartão de 300 px.

**Correção na branch isolada:** a lista de histórico recebeu a classe exclusiva `vinculos-aluno`; os elementos fazem quebra de linha, sem o preenchimento lateral duplicado. As datas de início e encerramento ficam em `div.vinculo-datas` própria, ocupando toda a largura útil abaixo das etiquetas. As demais listas e os controladores não foram alterados. Commits `dcbdb01` e `b727220`.

**Teste RED/GREEN:** [RED 44930144492](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44930144492) falhou no teste de estrutura das datas enquanto 199 demais testes Laravel passaram. [GREEN 44930327439](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44930327439) aprovou **200 testes Laravel × 2 (763 asserções por rodada), Pint, build e 4 testes UI**, além de ambas as rodadas de integração MariaDB → PostgreSQL em bancos descartáveis. Revisão independente não identificou achados críticos/importantes; observou como item menor a necessidade de confirmar visualmente os limites do cartão no notebook, já que o teste automatizado verifica a estrutura HTML, não a geometria real no navegador.

**Próxima conferência:** após `git pull --ff-only` com branch correta e árvore limpa, reabrir a ficha de um aluno fictício e confirmar que `Início: 01/02/2027` permanece dentro do cartão em largura desktop e reduzida. Não executar migrações nem tocar PostgreSQL original 5432, MariaDB, backups ou `.env`. [PR #5](https://github.com/astutigestaodeti-tech/astuti-escolar/pull/5) permanece draft, sem merge; produção conserva uma VPS exclusiva por Prefeitura.


## 21/11/2026 - Testes adicionais de matrículas e conferência visual concluída

**Conferência no notebook:** Jefferson confirmou com “Deu certo 👏🏻” que a data de início do vínculo não ultrapassa mais o cartão da ficha individual do aluno. Esse retorno encerra a pendência de verificação visual do ajuste CSS anterior, sem acesso do agente aos bancos ou ao notebook.

**Reforço de regressão na branch `feature/migracao-postgresql-instalacoes-isoladas`:** a consulta somente leitura de matrículas recebeu testes de vínculo arquivado, aluno logicamente excluído, autenticação TOTP pendente, busca literal de `_` e barra invertida, ordenação por ano e nome, limite de 120 caracteres e preservação do filtro na URL real de paginação. A revisão detectou que o primeiro teste de arquivamento ocultava duas causas simultâneas; os cenários foram separados. Para verificar a defesa contra registros legados inconsistentes, um teste cria **somente em banco PostgreSQL descartável `*_test`** um aluno arquivado com matrícula ainda ativa e confirma que ele não aparece. O fluxo normal de produção continua impedindo esse estado. Commits `ef716875`, `f5cb5a4` e `7d51d26`; sem alteração de controlador, rota ou estrutura de banco.

**Verificação final:** [GitHub Actions 44932406521](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44932406521) aprovou **206 testes PHP em duas rodadas, 793 asserções por rodada**, Pint, compilação da interface e 4 testes UI. A integração MariaDB → PostgreSQL aprovou **24 testes em duas rodadas, 242 asserções por rodada**, usando serviços descartáveis. Revisão independente complementar sem achados críticos/importantes remanescentes; registro de observações menores não bloqueantes.

**Restrições mantidas:** nenhuma nova migração, escrita em dados reais, mudança em `.env`, PostgreSQL original 5432, MariaDB, backups ou contratação de VPS. PR #5 continua aberto como rascunho, sem merge. Produção prevista com VPS e PostgreSQL exclusivos por Prefeitura.


## 21/11/2026 - Filtro por ano letivo nas matrículas e arquitetura de instalação esclarecida

**Esclarecimento do Jefferson:** em produção, cada Prefeitura contratante terá **uma instalação independente do Astuti Escolar, uma VPS exclusiva, uma aplicação própria e dois bancos PostgreSQL locais próprios (`platform` e `tenant`)**. Não há um VPS ou uma aplicação de produção compartilhados entre Prefeituras. Os municípios A/B nos testes são apenas fixtures fictícias e descartáveis para exercitar autorização; não representam a implantação comercial.

**Funcionalidade na branch de ensaio:** `Escolas > Escola autorizada > Matrículas` ganhou seletor `Todos os anos / Ano letivo YYYY`. As opções vêm somente de matrículas ativas da escola autorizada, sem matrícula, aluno ou ano letivo logicamente arquivados; não revelam anos de outras escolas. É possível combinar ano com busca por nome. O filtro preserva os parâmetros ao avançar página, mantém ordenação estável e valida o ano contra as opções autorizadas. Sem alteração de escrita, esquema, autenticação ou middlewares. Commits de especificação, plano, testes e implementação `6f95361`, `ab62912`, `d243c78`, `7fc1a4c`, `84cd5a7`, `e65dcc2`.

**TDD comprovado:** [RED 44933023191](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44933023191) trouxe **3 falhas esperadas na funcionalidade nova, com 206 testes anteriores aprovados**. [GREEN 44933258928](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44933258928) aprovou **209 testes Laravel em duas rodadas, 828 asserções por rodada**, Pint (138 arquivos), build, 4 testes UI e integração MariaDB → PostgreSQL com **24 testes em duas rodadas, 242 asserções por rodada**, em serviços descartáveis. Revisão independente sem Critical/Important.

**Pendência manual e menores:** filtro ainda não conferido visualmente no Debian/Edge do notebook. Após atualização segura da branch com árvore limpa, abrir uma escola fictícia e conferir seletor, busca, paginação e largura estreita. A mensagem visível para ano inválido foi concluída no registro seguinte; permanecem como possível evolução mais casos de `availableYears` para arquivados. Nenhuma migração, alteração de banco original 5432, MariaDB, backups, `.env` ou dados reais. PR #5 continua rascunho, sem merge.

## 21/11/2026 - Aviso seguro para ano letivo inválido em matrículas

A tela de consulta `Escolas > Matrículas` passou a recuperar a rota da própria escola quando um ano letivo inexistente ou inválido é enviado por URL. A busca válida é preservada, inclusive quando o termo é `0`; o aviso em português é exibido com `role="alert"` após o redirecionamento do navegador. Em vez de depender da página inicial genérica, há um retorno seguro e compreensível. Sem alterar gravações, esquemas ou permissões.

**TDD:** [RED 44934127503](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44934127503) mostrou uma falha nova na URL de retorno (209 anteriores aprovados). A primeira tentativa de verificação revelou necessidade de acompanhar o redirecionamento com a sessão do navegador; o teste foi ajustado sem relaxar a expectativa de mensagem visível. [GREEN 44934623041](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44934623041) aprovou **210 testes Laravel em duas rodadas (839 asserções em cada)**, Pint, compilação, 4 testes UI e **24 testes de integração MariaDB → PostgreSQL em duas rodadas (242 asserções em cada)**. HEAD da melhoria `43b2a56`.

**Restrições:** aplicação apenas no PR #5 rascunho da branch de ensaio, sem merge. Não houve migração, acesso ao notebook, alteração de banco real, PostgreSQL 5432, MariaDB, backup ou `.env`. A arquitetura de produção continua: uma VPS, uma aplicação e os dois bancos PostgreSQL privados exclusivos por Prefeitura.


## 22/11/2026 - Desvínculo de responsável com justificativa e segundo fator recente

Implementado em PR independente derivado do main consolidado o fluxo de desvincular o responsável **do aluno**, sem excluir o cadastro municipal do responsável nem afetar irmãos. A tela mostra nome, parentesco e exige motivo de 10 a 500 caracteres; o POST exige segundo fator recente. A exclusão é transacional e auditada como `deleted` em `StudentGuardian`, preservando snapshot sem CPF e telefone em claro. Sem contato principal, a ficha orienta a marcar outro manualmente. Incluídos testes de autorização, escola, motivo, auditoria, irmãos e retomada do formulário após o código. Validação final do CI ficará registrada no PR. Nenhum dado real ou banco do notebook foi alterado.


## 22/11/2026 - Consulta da turma com data de corte e ocupação

Nova ficha somente de consulta da turma ligada à lista da escola. Apresenta ano, etapa, turno, vagas preenchidas e alerta acima da capacidade; alunos com matrícula ativa no próprio contexto escolar são ordenados sem efeito de acentos, com nascimento, idade em 31/03 do ano letivo, vínculo com a ficha e contato principal carregado em lote. A regra pedagógica conservadora marca "Conferir idade" somente em Pré I/II e 1º a 9º ano reconhecíveis, sem tolerância, e não marca turmas ambíguas nem estudantes sem nascimento. Sem migração nem alterações em importadores; testes apenas fictícios. Resultados do CI e commit disponíveis no PR correspondente.

## 22/11/2026 - Frequência: chamada diária por turma (issue #54)

Implementada a chamada de alunos com matrícula ativa por turma e data, usando as novas tabelas tenant `attendance_sessions` e `attendance_marks` (migração 000025). Professores com acesso de consulta à escola podem marcar faltas e salvar a chamada de hoje; a gestão pode corrigir chamadas já registradas de datas anteriores, com motivo de 10 a 500 caracteres e segundo fator recente. Dados e alunos são validados sob bloqueio transacional, respeitando o isolamento escolar.

A tela utiliza as fotos internas de identificação apenas para consulta, com todos presentes inicialmente. A lista da turma recebe percentual mensal e alertas de três faltas consecutivas ou frequência abaixo de 75%, carregados em lote. Observações e motivo de correção não aparecem em claro nos registros de auditoria; o histórico registra os status alterados, responsáveis e horários. Sem alterações nos arquivos de importação, saída ou ficha individual, sem dados reais ou operações no notebook. Implementação e testes ficam no PR vinculado à issue #54; resultados do CI são verificados no próprio GitHub.

## 22/11/2026 - Painel de busca ativa por faltas na escola (issue #57)

Criado painel de leitura restrito à gestão escolar, com contador na ficha da escola, filtro por turma e motivo, três critérios de triagem (três faltas não justificadas consecutivas, frequência mensal e anual inferior a 75%) e priorização pelo número de ausências consecutivas. Exibe nome e turma com acesso à ficha, percentuais, última presença e o contato principal com telefone e WhatsApp quando celular.

Os números vêm de uma consulta PostgreSQL agregada para todas as matrículas ativas da unidade, evitando N+1; faltas justificadas são mostradas em coluna própria, não reduzem o percentual de triagem e interrompem a sequência de ausências não justificadas. O painel não expõe observações de chamadas, justificativas médicas ou documentos e não substitui a frequência oficial. A autorização é verificada antes da consulta e usuários de outra escola ou professores não acessam os dados. Sem migração. Os testes usam exclusivamente dados fictícios e cobrem cálculos, filtros, desempenho e isolamento. Validação do CI e referência do PR constam na issue #57.

## 22 e 23/11/2026 - Sessão do Claude como programador-chefe (PRs #59 a #86)

**Coordenação.** O Claude distribuiu as tarefas ao ChatGPT por issue com rótulo `chatgpt`, revisou e mesclou. Como o ambiente do ChatGPT não roda PHP, os PRs dele passam a ser conferidos aqui com a suíte e a conferência de margens antes do merge; quando precisam de ajuste, entram por um PR de integração (`integracao/...`) e o PR original é fechado com comentário.

**Entregas no main.**
- Fotos: redução no navegador antes do envio e mensagem com o limite real do servidor (#59).
- Busca ativa por faltas (#58, ChatGPT); desfazer saída registrada por engano e última saída na ficha, migração 000028 (#61).
- Fuso: aplicação e sessão PostgreSQL em America/Sao_Paulo (#63).
- Relatório mensal de frequência para impressão (#64, a partir do #62 do ChatGPT).
- Ficha de saúde mínima com alertas graves para o professor, migração 000030 (#66). Decisão de Jefferson em 23/11: secretaria e direção veem e editam a ficha completa (#84).
- Padrão visual dos cartões (`.folha-corpo`, `--recuo-folha`), `tests/Feature/Layout/AllSchoolPagesOpenTest.php` e `scripts/qualidade/checar-margens.py` (#68; campo e botão colados, #86).
- Servidores e professores nas turmas, migração 000029 (#69, a partir do #67 do ChatGPT); busca de servidores no padrão `.busca` (#86).
- Calendário escolar, migração 000031 (#71, ChatGPT), e feriados nacionais em um clique (#75).
- Pendências do cadastro por aluno, com atalhos (#73).
- Declaração de matrícula com código e QR Code, verificação pública mínima e tela `/verificar`, migração 000032 (#77, a partir do #76 do ChatGPT); verificação usa a única Prefeitura configurada quando falta `ASTUTI_MUNICIPALITY_DB_KEY` (#80).
- Ficha do aluno em duas colunas independentes, `.ficha-principal` e `.ficha-lateral` (#81).
- Transporte por rota para o motorista (#82, a partir do #79 do ChatGPT).

**Repositório.** A cota gratuita de GitHub Actions acabou em 22/11; por decisão de Jefferson o repositório ficou público, com LICENSE proprietária (todos os direitos reservados) mesclada antes da abertura (#72), e aprovação obrigatória para workflows de PRs de colaboradores externos.

**Em andamento.** Issue #83 (painel da rede para a Secretaria Municipal), PR #85 do ChatGPT, ainda sem revisão.

**Próximo passo de infraestrutura.** Jefferson contratou uma VPS Locaweb (Debian 13, 4 GB, 2 vCPU) para desenvolvimento e homologação, substituindo o notebook. Dados do servidor ficam fora do repositório. Na VPS, instalar do zero (PHP 8.4, PostgreSQL 17, Nginx, Composer, Node, OCR) e recriar a base fictícia pelas migrações e pelo kit de Monte Sereno; não copiar bancos do notebook. Definir `APP_TIMEZONE`, `ASTUTI_MUNICIPALITY_DB_KEY` e os limites de envio (`upload_max_filesize` 12M, `post_max_size` 16M, `client_max_body_size` 16m), e não expor o sistema pelo IP sem HTTPS.


## 23/11/2026 - Histórico escolar do Ensino Fundamental (issue #106, proposta para revisão)

**Base técnica:** branch `feature/historico-escolar-106` partindo do main `f2869d7`; migration municipal `2026_11_23_000037_create_student_transcripts.php`. Escopo exclusivamente histórico escolar, sem alterações no banco platform ou na migration 000036.

**Comportamento:** a prévia combina anos fechados da rede (dados de notas, frequência e resultado confirmado, nunca sugestão) com anos anteriores digitados uma vez por aluno e ano. Ano em curso não recebe resultado final. Sobreposição com a rede é avisada e prevalece o ano oficial. Edição e arquivamento de anos anteriores exigem segundo fator recente e auditoria.

**Documento e LGPD:** emissão registra kind `school_transcript` em `issued_documents`, código aleatório e QR locais, snapshot JSONB protegido contra alteração por trigger e SHA-256 canônico. A página pública apresenta apenas tipo, escola, data e validade, sem nome ou dados acadêmicos. Impressão da via emitida lê exclusivamente o snapshot; revogação preserva a via. Professor não acessa, gestão municipal apenas consulta, direção e secretaria da unidade podem editar e emitir.

**Conferências:** testes fictícios e três telas adicionadas ao `AllSchoolPagesOpenTest`. Não há Laravel/PostgreSQL descartável no ambiente de autoria; o CI e a checagem de margens de 1440 e 390 px ficam pendentes do Claude. Não houve merge, deploy nem alteração de bases reais.


## 23/11/2026 - Painel de equidade e VAAR (issue #108, proposta)

**Base:** feature/equidade-vaar-108, main e2ad8787 com Censo e histórico escolar; migration municipal 2026_11_23_000038_create_vaar_conditions.php. Não modifica migrations anteriores.

**Indicadores:** leitura em lote de matrículas do ano, notas e frequência, retidos só após confirmação; seleção por escola e turma. Cor/raça, sexo e zona são apenas agregados, com supressão de grupos menores que cinco e supressão complementar. Deficiência indisponível sem cadastro específico; não inferir pelos alertas de saúde. A lista nominal da condicionalidade II é exclusivamente de acompanhamento de faltas, sem dados sensíveis.

**Condicionalidades:** cadastro dos incisos I-V, responsável, status e PDF institucional armazenado privadamente, edição exclusiva da gestão municipal com 2FA recente e auditoria sem conteúdo da evidência. Direção vê apenas a escola vinculada. O painel não substitui a comprovação pelo MEC e Inep.

**Qualidade:** testes fictícios e telas de rede/escola/turma/direção incluídos na conferência de margens, pendentes do CI e revisão do Claude antes de merge. Sem deploy.


## 23/11/2026 - Tempo integral, metas e Fundeb (issue #115, proposta)

**Base e migration:** feature/tempo-integral-115 a partir do main d9487a9; tenant 2026_11_23_000040_create_full_time_education.php. Jornada decimal em school_classes; metas por etapa/ano, receita anual e aplicações em centavos com PDF privado.

**Regra:** integral quando jornada informada >= 7,0 horas; sem jornada mantém inferência pelo turno. A lista alerta divergências entre turno e jornada. Conferência do Censo passa a usar as horas informadas. O painel conta matrículas integrais atuais e compara com o cadastro do ano anterior, com aviso explícito de que não constitui comprovação de novas vagas do programa.

**Financeiro:** gestão municipal registra receita do ano, mínimo de 4%, aplicações e documentos PDF privados; auditoria por whitelist e 2FA recente. Direção e secretaria só consultam escola própria; professor sem acesso ao painel. Nova migração não modifica as anteriores.

**Qualidade:** testes fictícios para limites de jornada, metas, 4%, Censo, auditoria, permissões e comprovantes. Novas telas integradas ao AllSchoolPagesOpenTest. Pint/CI e margens aguardam revisão antes de merge, sem deploy.
