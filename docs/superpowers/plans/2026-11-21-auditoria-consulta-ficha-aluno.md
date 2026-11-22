# Auditoria de consulta da ficha do aluno Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Registrar um evento imutável e sem dados pessoais a cada consulta autorizada de ficha individual do aluno.

**Architecture:** Após as verificações de escola, matrícula ativa e leitura da ficha, `SchoolStudentController::show()` chama o serviço `AuditTrail::record('tenant', 'student.viewed', Student::class, $student->id)`. O contexto municipal e o usuário autenticado já foram estabelecidos no ciclo de requisição. Nenhum novo armazenamento será criado.

**Tech Stack:** PHP 8.4, Laravel 13, PostgreSQL 17 descartável em GitHub Actions, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-11-21-auditoria-consulta-ficha-aluno-design.md`

## Global Constraints
- Somente branch `feature/migracao-postgresql-instalacoes-isoladas`; PR #5 draft, sem merge.
- Não modificar o PostgreSQL original 5432, MariaDB, backups, .env ou dados reais.
- Sem migrations, sem novos campos de dados pessoais, sem escrita funcional em alunos/matrículas.
- Evento `student.viewed` somente após autorização e seleção de aluno ativo na escola autorizada.

## Review Focus
- Ficha de aluno em escola autorizada: um evento com aluno e conta corretos, sem snapshot.
- Ficha de aluno de outra escola: resposta 404 e nenhum evento.
- Usuário sem permissão na escola: resposta 403 e nenhum evento.
- Segundo fator habilitado ainda não confirmado: resposta 302 ao desafio e nenhum evento.
- Multi-tenant: evento deve aparecer somente no banco municipal solicitado.

---

### Task 1: Auditoria de leitura da ficha (TDD)

**Files:**
- Modify: `tests/Feature/Students/SchoolStudentShowTest.php`
- Modify: `app/Http/Controllers/SchoolStudentController.php`

**Interfaces:**
- Consumes: `AuditTrail::record(string $connection, string $action, ?string $auditableType, ?string $auditableId): void`; `TenantContext::runFor(Municipality $municipality, Closure $callback)`.
- Produces: evento municipal imutável `student.viewed`, sem `before` e `after`.

- [ ] Step 1: Criar teste de resposta 200 que verifica registro no banco municipal correto com `user_id`, `auditable_id`, `action`, `before=null`, `after=null`; adicionar testes 403/404/TOTP com ausência de eventos.
- [ ] Step 2: Executar GitHub Actions para confirmar RED no caso autorizado sem evento, sem erros de infraestrutura.
- [ ] Step 3: Após carregar vínculos no `show()`, adicionar chamada `app(AuditTrail::class)->record('tenant', 'student.viewed', Student::class, $student->id)` antes de renderizar.
- [ ] Step 4: Confirmar GREEN no CI: duas rodadas da suíte PHP, Pint, build e UI, integração MariaDB → PostgreSQL.
- [ ] Step 5: Registrar o marco e suas evidências nos dois documentos de continuidade na branch main, mantendo o PR draft e sem operação no notebook.
