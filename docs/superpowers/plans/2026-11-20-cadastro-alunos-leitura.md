# Cadastro de alunos, leitura inicial - Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Entregar um módulo real de consulta de alunos por escola, preparado para futura matrícula e importação, sem habilitar operações de escrita.

**Architecture:** Duas tabelas por banco tenant: `students` (identidade) e `student_enrollments` (vínculos escolares anuais). Um controlador de leitura exige autorização da escola via Gate antes de consultar alunos, e uma view Blade reaproveita o padrão Astuti.

**Tech Stack:** Laravel 13, PHP 8.4.1+, MariaDB 11, Blade/CSS Astuti, PHPUnit, GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-11-20-cadastro-alunos-leitura-design.md`

## Global Constraints

- Somente bancos `_test` na integração e nomes fictícios; nenhuma inserção em base real.
- Tenancy e autorização existentes devem continuar bloqueando município e escolas sem acesso.
- Nenhuma rota POST/PATCH/DELETE; formulários de cadastro dependem de 2FA, auditoria e revisão humana.
- Não colocar CPF, laudos, família ou documentos no esquema atual.
- Proteger a integridade histórica por FKs com `restrictOnDelete`, sem cascata de exclusão.

## Review Focus

1. Mesmo nome em A e B deve continuar fisicamente isolado: teste HTTP A versus B e bloqueio 403.
2. Usuário com acesso à escola A1 não consegue consultar A2: teste de acesso direto 403.
3. Vínculos concluídos ou transferidos não contam como alunos atuais: teste com status não ativo.
4. Busca com `%`, `_` ou HTML não deve vazar registros nem executar marcação: teste de busca literal e escape.
5. Dois vínculos ativos do mesmo aluno na mesma escola não produzem duplicação: teste com `whereHas` e paginação.

---

### Task 1: Modelo municipal de aluno e vínculo de matrícula

**Files:**
- Create: `database/migrations/tenant/2026_11_20_000004_create_students_table.php`
- Create: `database/migrations/tenant/2026_11_20_000005_create_student_enrollments_table.php`
- Create: `app/Models/Tenant/Student.php`
- Create: `app/Models/Tenant/StudentEnrollment.php`
- Test: `tests/Feature/Students/StudentStorageTest.php`
- Modify: `tests/Support/TwoMunicipalityTestCase.php` (limpeza dos vínculos antes de escolas)
- Modify: `tests/Feature/Tenancy/MunicipalityIsolationTest.php` (limpeza antes de preparar escolas)

**Interfaces:** `Student::enrollments(): HasMany`, `StudentEnrollment::student(): BelongsTo`, `StudentEnrollment::school(): BelongsTo`, `StudentEnrollment::schoolYear(): BelongsTo`. UUIDs gerados pelas fixtures. Status inicial `active`.

- [ ] Escrever testes para persistência isolada em A/B e FKs restritivas, inclusive vínculo ao ano letivo.
- [ ] Rodar tests no CI com bancos efêmeros e confirmar falha por schema ausente (RED).
- [ ] Criar migrações/modelos e ampliar limpeza segura dos fixtures compartilhados.
- [ ] Rodar novamente os testes de integração, duas vezes no mesmo banco (GREEN).
- [ ] Revisar mudança de schema e registrar commit pequeno.

### Task 2: Consulta de alunos autorizada por escola

**Files:**
- Create: `app/Http/Controllers/SchoolStudentController.php`
- Modify: `routes/web.php`
- Create: `resources/views/students/index.blade.php`
- Modify: `resources/views/schools/show.blade.php`
- Test: `tests/Feature/Students/SchoolStudentIndexTest.php`

**Interfaces:** `SchoolStudentController::index(Request $request, string $municipio, string $escola): View`; usa `Gate::authorize('view', [$school,$municipality])` e `Student::query()->whereHas('enrollments', ... school_id + status active)`; busca literal `busca`, paginação 25.

- [ ] Escrever testes de acesso A1/A2/B, busca parcial e literal, aluno encerrado, escape de HTML, duplicatas, página vazia e rota de escrita inexistente.
- [ ] Executar no CI para comprovar a falha sem rota/controller/view (RED).
- [ ] Adicionar rota GET, controller, Blade e acesso pela ficha da escola.
- [ ] Rodar PHP duas vezes no banco efêmero, Pint, build e `npm run test:ui` (GREEN).
- [ ] Atualizar README e registrar commit.

## Verificação final

- [ ] Verificar CI em commit exato antes de afirmar que o incremento está pronto.
- [ ] Inspecionar a diferença com a branch anterior, checar segredos e alterações destrutivas.
- [ ] Manter PR de `main` em rascunho e informar a Jefferson os comandos de atualização e migração **apenas no ambiente de desenvolvimento**, com conferência do banco configurado.
