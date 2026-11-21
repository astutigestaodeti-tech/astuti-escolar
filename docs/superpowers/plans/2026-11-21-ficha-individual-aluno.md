# Ficha individual do aluno Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ficha individual navegável, somente leitura, isolada por escola e município.

**Architecture:** Reutilizar o middleware `ResolveMunicipality`, `SchoolPolicy` e modelos atuais. Filtrar `Student` por `whereHas('enrollments')` com `school_id` e `status=active`, assim como a lista. Carregar somente vínculos ativos da escola autorizada e ano letivo.

**Tech Stack:** Laravel 13, PHP 8.4, Blade, PostgreSQL 17, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-11-21-ficha-individual-aluno-design.md`

## Global Constraints

- Alterar somente a branch isolada; PR #5 continua draft.
- Desenvolvimento com dados fictícios; banco PostgreSQL principal 5432, MariaDB, backups e .env intocados.
- Somente consulta, sem novas rotas HTTP de gravação.
- Sem vazamento de dados por URL direta ou HTML não escapado.

## Review Focus

- Aluno de outra escola na mesma rede: 404 mesmo que escola da URL seja autorizada.
- Matrícula inativa: 404.
- Escola fora da permissão: 403.
- Município fora da permissão: 403, sem conexão residual.
- Nome com HTML: exibição escapada.
---

### Task 1: Ficha read-only com escopo de acesso

**Files:**
- Create: `tests/Feature/Students/SchoolStudentShowTest.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/SchoolStudentController.php`
- Create: `resources/views/students/show.blade.php`
- Modify: `resources/views/students/index.blade.php`

**Interfaces:**
- Consumes: `SchoolStudentController::index()`, `Student::enrollments()`, `StudentEnrollment::schoolYear()`, `SchoolPolicy`.
- Produces: `SchoolStudentController::show(Request $request, string $municipio, string $escola, string $aluno): View` e rota `school.students.show`.

- [ ] **Step 1: Failing feature tests**

Testar GET autenticado do aluno A1 com vínculo ativo, ano letivo e nome; GET em A1 com aluno de A2 ou inativo deve ser 404; GET em A2 sem permissão e no município B deve ser 403; visitante redireciona ao login; nome HTML escapa; GET listagem contém link com UUID.

- [ ] **Step 2: Verify RED on CI PostgreSQL**

Run: workflow `Testes Laravel` disparado pelo commit somente com testes.
Expected: testes da ficha falham porque a rota ainda não existe; testes anteriores continuam verdes.

- [ ] **Step 3: Minimal implementation**

Adicionar GET `/escolas/{escola}/alunos/{aluno}` com UUID; implementar `show()` usando Gate existente e vínculos ativos apenas da escola; view Blade read-only e link na lista.

- [ ] **Step 4: Verify GREEN**

Run: workflow `Testes Laravel`; conferir duas rodadas PHP, Pint, build, testes UI e integração.
Expected: todos os jobs success.

- [ ] **Step 5: Commit and document**

Registrar commits da branch de ensaio e evidências reais no histórico de continuidade do `main` após testes verdes.
