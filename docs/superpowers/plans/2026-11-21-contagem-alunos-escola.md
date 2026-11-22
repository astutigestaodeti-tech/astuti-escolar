# Contagem de alunos por escola Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mostrar contagem correta de alunos ativos nos cartões das escolas autorizadas.

**Architecture:** Na `SchoolController::index()`, selecionar escolas com uma subconsulta `Student::query()->selectRaw('count(*)')->whereHas('enrollments', ...)` correlacionada pelo ID da escola, para contar alunos distintos naturalmente. Exibir `active_students_count` no Blade.

**Tech Stack:** Laravel 13, PostgreSQL 17, PHP 8.4, Blade e PHPUnit.

**Spec:** `docs/superpowers/specs/2026-11-21-contagem-alunos-escola-design.md`

## Global Constraints

- Não alterar bancos do notebook nem .env, manter PR #5 draft.
- Respeitar acesso escolar existente e não registrar dados reais.
- Contagem de pessoas, não número de vínculos; ignorar matrícula inativa.

## Review Focus

- Aluno com dois vínculos ativos na mesma escola deve contar uma vez.
- Matrícula encerrada não deve contar.
- Escola sem alunos deve mostrar zero.
- Escola não autorizada não deve aparecer na página.
- Contagem não deve fazer consulta N+1.

---

### Task 1: Contagem consistente na lista de escolas

**Files:**
- Create: `tests/Feature/Schools/SchoolActiveStudentCountTest.php`
- Modify: `app/Http/Controllers/SchoolController.php`
- Modify: `resources/views/schools/index.blade.php`

**Interfaces:**
- Consumes: `Student::enrollments()`, `SchoolAccess::visibleSchoolIds()`.
- Produces: `active_students_count` em cada objeto `School` devolvido à view.

- [ ] **Step 1: Red test**

Testar escola A1 com dois alunos ativos, uma matrícula adicional ativa de um dos alunos, uma inativa e aluno ativo da A2; número exibido em A1 deve ser `2 alunos ativos`, A2 não aparece para usuário restrito. Em página de gestor, A2 sem alunos exibe `0 alunos ativos`.

- [ ] **Step 2: Verify RED**

Run: GitHub Actions `Testes Laravel` para commit somente com teste.
Expected: falha ao não encontrar `2 alunos ativos`.

- [ ] **Step 3: Implement**

Adicionar subconsulta agregada no `SchoolController` e marcador de quantidade no cartão do Blade, sem alterar a autorização.

- [ ] **Step 4: Verify GREEN**

Run: workflow GitHub Actions, verificar duas rodadas de `php artisan test`, Pint, build, testes UI e integração.
Expected: ambos os jobs success.

- [ ] **Step 5: Document**

Registrar SHAs, execução CI, escopo e comando seguro de atualização no histórico.
