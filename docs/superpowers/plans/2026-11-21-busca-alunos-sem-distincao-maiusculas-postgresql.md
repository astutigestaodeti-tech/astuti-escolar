# Busca de alunos sem distinção de maiúsculas no PostgreSQL Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A pesquisa parcial de alunos no PostgreSQL encontra nomes independentemente de maiúsculas/minúsculas, sem ampliar o acesso autorizado.

**Architecture:** Manter a filtragem por matrícula ativa e autorização existentes. Apenas o operador de busca textual muda para `ILIKE` no PostgreSQL; em outros drivers, preservar `LIKE`. O escape literal dos curingas é mantido.

**Tech Stack:** PHP 8.4, Laravel 13, PostgreSQL 17, PHPUnit e GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-11-20-cadastro-alunos-leitura-design.md`

## Global Constraints

- Apenas dados fictícios; nenhuma alteração nos bancos originais do notebook.
- Continuar read-only; escopos de escola, município e status ativo preservados.
- Busca parcial com `%` e `_` literais, limite 120 caracteres e paginação de 25 com parâmetro `busca`.
- Modificações apenas na branch `feature/migracao-postgresql-instalacoes-isoladas`; PR #5 continua draft.

## Review Focus

- Nome em maiúsculas com consulta minúscula: o novo teste deve encontrar o registro.
- Uso de `%` e `_`: testes existentes devem impedir busca por padrão SQL.
- Outro município/escola: testes existentes devem impedir vazamento de alunos.
- Vínculo inativo: teste existente deve mantê-lo fora da lista.
- Paginação: teste existente deve conservar 25 por página.

---

### Task 1: Pesquisa parcial com caixa indiferente no PostgreSQL

**Files:**
- Modify: `tests/Feature/Students/SchoolStudentIndexTest.php`
- Modify: `app/Http/Controllers/SchoolStudentController.php`

**Interfaces:**
- Consumes: `GET /m/{municipio}/escolas/{escola}/alunos?busca=...` e `Student::query()`.
- Produces: mesma rota, com operador `ilike` somente no driver `pgsql`.

- [ ] **Step 1: Write the failing test**

Adicionar `test_busca_por_nome_parcial_nao_diferencia_maiusculas_de_minusculas_no_postgresql`: registrar `Maria do Campo`, `MARINA dos Lagos`, `Bruno da Unidade` na escola A1 e `Marcos de Outra Escola` na A2; buscar `?busca=mari` autenticado com acesso apenas à A1; exibir apenas Maria/MARINA.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_busca_por_nome_parcial_nao_diferencia_maiusculas_de_minusculas_no_postgresql` no CI PostgreSQL 17.
Expected: FAIL ao não encontrar `Maria do Campo`.

- [ ] **Step 3: Minimal implementation**

No controller, usar `$query->getModel()->getConnection()->getDriverName()` para escolher `ilike` em `pgsql` ou `like` em outros drivers. Manter `addcslashes($search, '\\%_')`.

- [ ] **Step 4: Run tests**

Run: workflow `Testes Laravel` no GitHub Actions; `php artisan test` duas vezes, Pint, build, testes UI e integração devem ficar verdes.
Expected: todos os jobs `success`.

- [ ] **Step 5: Commit**

Registrar o teste RED, a implementação GREEN e evidências de CI nos commits da branch de ensaio; documentar na continuidade do projeto.
