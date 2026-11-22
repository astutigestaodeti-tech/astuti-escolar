# Bloco 3: papeis de producao PostgreSQL

**Decisao de Jefferson (21/11/2026).** Implementar num PR proprio sobre o PR #5. Claude revisa antes de qualquer merge. Nao tocar nos PRs #6 e #7.

## Fronteiras

Cada Prefeitura possui uma VPS com PostgreSQL 17+, um banco `platform` e um banco `tenant` locais. Um comando Artisan versionado, executado somente durante deploy com credenciais administrativas efemeras, cria os papeis `astuti_migracao` e `astuti_aplicacao`. Nao cria municipios novos, nao acessa bancos remotos e nao recebe dados reais.

O papel de migracao e dono dos dois bancos, schema e objetos da aplicacao, inclusive tabelas, funcoes de bloqueio e tabelas que portam os gatilhos de auditoria. O papel web nao e membro de outros papeis, nao e superusuario, nao possui objetos, nao cria no schema e nao pode elevar o proprio acesso.

## Matriz de acesso

| Objeto | Migracao | Aplicacao |
| --- | --- | --- |
| Tabelas operacionais existentes | Dona; migrations | SELECT, INSERT, UPDATE; sem DELETE |
| Auditorias `platform_audit_logs` e `audit_logs` | Dona; administracao excepcional | SELECT, INSERT |
| Infraestrutura na base `platform`: `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `password_reset_tokens` (se existentes) | Dona; migrations | SELECT, INSERT, UPDATE, DELETE |
| Novas tabelas antes do comando ser reaplicado | Dona | SELECT, INSERT via default privileges |
| Sequencias | Dona | USAGE, SELECT |
| Schema `public`, funcoes e gatilhos | Dona | USAGE; sem CREATE, ALTER, DROP ou TRIGGER |

Nao conceder TRUNCATE em nenhuma tabela. DELETE se limita a lista fixa de infraestrutura Laravel existente na base central, nunca na base municipal, usuarios, vinculos escolares ou auditoria. Sessoes, cache e filas podem permanecer em PostgreSQL com suas conexoes centralizadas. O comando preserva senhas existentes e nao recebe senha em argumentos; psql `\\password` inicializa os dois segredos.

## Ordem de implantacao

1. Criar os dois bancos ICU pt-BR no PostgreSQL 17.
2. Rodar comando em manutencao com administrador temporario; criar papeis e transferir ownership.
3. Definir senhas interativamente; executar migrations como `astuti_migracao`, que instala `unaccent` e `pg_trgm`.
4. Reexecutar comando antes de liberar acesso web. Configurar conexoes web como `astuti_aplicacao` e descartar credenciais elevadas.
5. Em cada deploy futuro repetir migration + reconciliacao de grants com site em manutencao.

## Prova

Job proprio no CI, conectado com login e senha do papel web a bases descartaveis; recusas por falta de privilegio (`SQLSTATE 42501`) para UPDATE, DELETE e TRUNCATE nas auditorias, desativacao e remocao de gatilhos, ALTER TABLE e CREATE TABLE. Confirmar titularidade, default privileges, idempotencia, login Laravel, requisicao autenticada, logout removendo a sessao de `sessions`, Cache::forget com drivers de banco e escrita auditada em cada banco. Parar apos PR e CI, para revisao do Claude.
