# Astuti Gestao Escolar - Testes reais da migracao MariaDB para PostgreSQL

**Data:** 21/11/2026  
**Estado:** desenho tecnico para aprovacao de Jefferson; nenhuma implementacao iniciada.  
**Base:** `docs/superpowers/specs/2026-11-20-postgresql-instalacoes-isoladas-design.md`, Task 4 do plano correspondente, PR #5 em rascunho.

## 1. Intencao

Comprovar automaticamente que o codigo copia o cenario **inteiramente ficticio** Monte Sereno v2 do MariaDB para PostgreSQL, preservando identidades, relacionamentos, hashes de senha e auditoria, sem escrever na origem. Os testes devem detectar regressao e falha parcial **antes** de se considerar a transferencia para os destinos principais do notebook.

Em 21/11, Jefferson comprovou dois dumps MariaDB, restauracoes com contagens iguais nas 12 tabelas, dry-run aprovado em `a27be7b1`, duas copias `pg_dump -Fc` dos destinos PostgreSQL e migracao `jobs` executada no banco central. **Nenhuma copia efetiva de dados ao PG foi realizada**. O CI atual testa esquema, codigo e interface, mas nao a transferencia entre os dois SGBDs.

## 2. Abordagens

- **A. Escolhida por Jefferson:** testes automatizados no GitHub Actions, usando servicos efemeros MariaDB e PostgreSQL, sem qualquer acesso ao notebook. E reproduzivel em cada commit e economiza operacoes manuais locais, com maior trabalho inicial de fixtures.
- **B. Alternativa posterior:** ensaio isolado no Debian com instancias e bases descartaveis, incluindo restauracao supervisionada. E valioso para validar o ambiente real, mas exige mais configuracao e nao oferece por si so regressao automatica.
- **C. Nao suficiente isoladamente:** testes apenas com mocks/SQLite. Sao baratos, mas nao exercitam tipos, constraints, transacoes e diferencas entre MariaDB e PostgreSQL.

## 3. Limites de seguranca obrigatorios

1. **Toda base usada pelos testes termina em `_test`** e pertence exclusivamente aos containers efemeros da execucao CI. Nunca apontar testes para `*_dev` do notebook ou qualquer host remoto.
2. O comando existente `astuti:migrar-dados-postgresql` permanece restrito a `APP_ENV=local`, nomes `*_dev` e loopback, com confirmacao de escrita. **Nao relaxar esse bloqueio para permitir os testes**.
3. Extrair, se necessario, a logica copiadora/validadora para uma unidade reutilizavel que receba conexoes explicitas. O runner de teste usa somente conexoes `*_test` e nao oferece flags publicas que contornem o bloqueio da operacao normal.
4. Popular a origem MariaDB do CI com fixtures criadas em codigo: municipio `monte-sereno`, um usuario com email `.invalid`, tres escolas, ano 2027, 115 alunos ficticios, tres turmas, 115 matriculas e registros sinteticos de auditoria. IDs, chaves e valores consistentes e deterministas onde necessario.
5. Os dados sinteticos sao recriados apenas em bancos descartaveis. Nenhum dump real, hash real, senha do notebook, `.env` ou informacao escolar pessoal entra no GitHub.
6. A origem fica com transacoes somente leitura durante a transferencia. Falha nao deve alterar a origem. Os destinos exigem validacao de vazio.
7. Nenhum teste faz `DROP`, `TRUNCATE`, `migrate:fresh` ou limpeza fora dos bancos CI explicitamente terminados em `_test`. Preferir descarte automatico dos containers no fim do job.

## 4. Implementacao proposta

**CI separado:** adicionar um job de integracao MariaDB->PostgreSQL ao workflow atual, com PostgreSQL 17, MariaDB compativel com o notebook, PHP 8.4 com `pdo_pgsql` e `pdo_mysql`, Composer e bancos distintos `*_test`. O job existente de testes/estilo/interface permanece independente e nao perde suas verificacoes.

**Fixtures de origem:** criar schema minimo fiel as colunas das 12 tabelas migradas, usando migracoes ou suporte de teste dedicado e sem executar gatilhos PostgreSQL no MariaDB. Gerar registros sinteticos com FKs e hashes de senha de teste. Preparar os dois esquemas PostgreSQL com as migrations ja presentes, incluindo `jobs`, sem semear dados nos destinos.

**Testes de comportamento reais:**
- simulacao sem escrita nos dois destinos e na origem;
- copia integral com comparacao de todas as linhas, UUIDs, FKs locais, identificadores, hashes e contagens 3/115/2027;
- recusa de destino preenchido, nome fora de `*_test` ou erro de integridade;
- falha induzida **antes** do primeiro commit: rollback das duas transacoes PG, origem intacta;
- falha induzida **entre** os commits: reconhecer que pode haver estado parcial, impedir retry cego e demonstrar procedimento supervisionado de recuperacao no CI;
- referencias logicas entre bancos (escopos escolares e usuarios de auditoria), conforme fixture;
- duas execucoes consecutivas de verificacao no mesmo job, com preparacao estritamente limitada aos bancos `*_test`.

**Falha controlada:** preferir uma fronteira interna de teste/injecao de dependencia no mecanismo de transferencia para simular erro entre commits. Nao disponibilizar uma opcao perigosa no comando publico nem inserir um atalho que funcione em producao.

**Diagnostico:** falhas devem informar etapa e base afetada sem expor SQL com valores de estudantes nem credenciais. Resultado de teste deve distinguir sucesso, rollback anterior ao commit e recuperacao manual necessaria depois de commit parcial.

## 5. Critérios de aceite

1. Job de integracao executa MariaDB e PostgreSQL reais e usa apenas `*_test` no ambiente efemero do GitHub.
2. Testes de sucesso e falha exercitam o mesmo mecanismo usado pelo comando de transferencia real, sem mudar os bloqueios de seguranca existentes.
3. Dados da origem permanecem iguais antes/depois; copia integra preserva UUIDs, hashes, referencias e contagens previstas.
4. Falha anterior ao commit reverte ambos os destinos; falha entre commits e identificada sem falso relato de sucesso e tem recuperacao demonstrada somente em ambiente descartavel.
5. Job passa duas vezes e o CI atual continua verde (PHPUnit, Pint, build, interface). PR #5 permanece em rascunho e sem merge.
6. Documentacao operacional e continuidade descrevem o que o CI comprova e o que **ainda depende de ensaio local**. Nenhuma transferencia real do notebook e autorizada por CI verde isoladamente.

## 6. Fora de escopo e sequencia

Nao criar funcionalidades escolares novas, nao contratar VPS, nao migrar dados reais, nao alterar os dumps ou bancos existentes no notebook e nao mesclar PRs nesta etapa.

Depois da aprovacao deste desenho, elaborar tarefas pequenas TDD conforme plano Superpowers, implementar em commits revisaveis no PR #5, conferir os jobs CI e solicitar revisao independente. Somente depois propor ensaio local descartavel e, posteriormente, nova autorizacao explicita para qualquer `--executar` nos dois destinos PostgreSQL principais.
