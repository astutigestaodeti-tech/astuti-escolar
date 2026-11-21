# Ensaio de cópia em cluster PostgreSQL isolado - Plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Validar a cópia de dados fictícios MariaDB para PostgreSQL no notebook, sem gravar em nenhum banco de desenvolvimento PostgreSQL original.

**Architecture:** Reaproveitar a CLI `astuti:migrar-dados-postgresql` e o serviço de cópia já testados. Criar um novo cluster PostgreSQL 17 na máquina, com identidade e porta diferentes, restaurar o par de dumps pré-cópia nele e configurar os processos Laravel temporariamente para os dois bancos `*_dev` **somente naquele cluster**. Encerrar o cluster ao final, preservando suas bases para auditoria.

**Tech Stack:** Bash no Debian 13/WSL, PostgreSQL 17, PHP 8.4, Laravel, GitHub Actions.

**Spec:** `docs/operacao/migracao-mariadb-postgresql.md`.

## Global Constraints

- Não modificar as bases MariaDB de origem.
- Não executar a cópia nos bancos PostgreSQL originais na porta 5432.
- Não reutilizar nomes/bancos de cluster de ensaio já existentes.
- Verificar SHA256 de ambos os dumps, identidade do cluster e conexão PostgreSQL efetiva do Laravel.
- Usar `APP_ENV=local`; não alterar `.env`, cache global do Laravel nem o bloqueio da CLI normal.
- Os alunos são exclusivamente fictícios (3 escolas, 115 estudantes, 2027).
- Não fazer merge do PR #5 nem interpretar o ensaio como produção pronta.

## Review Focus

1. Porta ou cluster repetidos: a transferência não pode alcançar o PostgreSQL original.
2. Cache ou `DATABASE_URL` divergentes: validar conexão PDO real antes de copiar.
3. Dump corrompido, manifesto incompleto ou tabela-alvo ocupada: encerrar sem copiar.
4. Falha da simulação, da migração ou do desligamento: retornar erro e preservar evidências.
5. Origem MariaDB: transações `READ ONLY` impostas pelo serviço; recomendar conta de leitura exclusiva antes de migrações não fictícias.

### Task 1: Roteiro de ensaio isolado

**Files:**
- Create: `scripts/operacao/ensaiar-copia-isolada-postgresql.sh`
- Test: `tests/Shell/EnsaiarCopiaIsoladaPostgresqlTest.sh`

**Interfaces:**
- Consumes: dumps completos `pgsql-pre-copia-*` e CLI existente `astuti:migrar-dados-postgresql`.
- Produces: relatório com cluster descartável, porta e contagens, sem modificar os dois bancos originais.

- [x] Escrever testes shell dos comportamentos de recusa, simulação e cópia.
- [x] Executar testes para observar falha antes de existir o roteiro.
- [x] Implementar validação dos backups, criação de cluster independente e execução protegida.
- [x] Verificar cenários shell e sintaxe Bash: 8 cenários passaram.
- [x] Revisar segurança e corrigir manifesto incompleto, isolamento, cache Laravel e desligamento.

### Task 2: Integração ao projeto e continuidade

**Files:**
- Modify: `.github/workflows/testes.yml`
- Modify: `docs/operacao/migracao-mariadb-postgresql.md`
- Update: `docs/continuidade/HISTORICO.md` (na branch padrão para futuros chats)

**Interfaces:**
- Consumes: roteiro e testes da tarefa 1.
- Produces: CI reproduzível e instrução única para teste real no notebook.

- [x] Incluir testes do roteiro no workflow.
- [ ] Conferir CI completo da branch após publicação.
- [ ] Registrar comando único e evidências sem credenciais.
- [ ] Aguardar captura do ensaio real do notebook para confirmar 115 alunos de fato copiados no cluster descartável.

## Estado de conclusão

A execução automatizada no notebook requer o usuário conectado ao Debian. Testes shell simulados e integração sintética no CI não equivalem à cópia real dos dados da máquina.
