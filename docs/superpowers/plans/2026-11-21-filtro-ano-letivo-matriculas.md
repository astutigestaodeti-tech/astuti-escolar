# Filtro de ano letivo na consulta de matrículas - Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:test-driven-development and superpowers:verification-before-completion. Steps use checkbox (- [ ]) and must run RED before implementation.

**Goal:** permitir filtrar matrículas ativas por ano letivo e nome sem expor anos de escolas não autorizadas.

**Architecture:** reutilizar o controlador e a página existentes, buscando opções de anos pelo mesmo escopo restrito da escola e aplicando filtro opcional validado. Preservar o acesso somente leitura e a arquitetura de VPS exclusivo por Prefeitura.

**Tech Stack:** Laravel PHP 8.4, PostgreSQL, Blade, CSS estático, PHPUnit/Pest pelo fluxo CI atual.

**Spec:** docs/superpowers/specs/2026-11-21-filtro-ano-letivo-matriculas-design.md

## Global Constraints
- Uma aplicação e dois PostgreSQL locais próprios por Prefeitura em VPS exclusivo; nenhuma aplicação compartilhada em produção.
- Testar apenas em bases PostgreSQL descartáveis terminadas em `_test`, nunca no cluster do notebook.
- PR #5 draft; não migrar, escrever em registros reais, alterar `.env`, banco original 5432, MariaDB, backups, nem mesclar.

## Review Focus
1. Escolas ou municípios não autorizados não revelam opções de anos: testar A1, A2 e B1 separados.
2. Ano inexistente/malformado não amplia consulta: testar validação 2025 ausente e string não numérica.
3. Busca e ano juntos respeitam ambos: testar nomes semelhantes em 2026/2027.
4. Página 2 não perde filtros: navegar pela URL gerada, verificar `busca`, `ano_letivo` e `page`.
5. Anos de matrículas concluídas ou arquivadas não aparecem no seletor: inserir fixture de 2025 concluída.

---

### Task 1: Filtragem e seletor por ano

**Files:**
- Modify: `tests/Feature/Students/SchoolEnrollmentIndexTest.php`
- Modify: `app/Http/Controllers/SchoolEnrollmentController.php`
- Modify: `resources/views/enrollments/index.blade.php`
- Modify: `public/css/astuti.css`

**Interfaces:**
- Consumes: `SchoolEnrollmentController@index(Request $request, string $municipio, string $escola)` e parâmetro `busca` existentes.
- Produces: query opcional `ano_letivo`, view data `availableYears: array<int>`, `selectedYear: ?int`, seletor `name="ano_letivo"`.

- [ ] **Step 1: escrever testes de integração.** Conferir `availableYears = [2027, 2026]` para A1 sem 2025 concluído/A2/B1, GET ano 2026 retorna apenas o vínculo 2026; GET combinando `busca` e `ano_letivo` retorna somente combinação; 26 resultados mantêm ambos filtros em `nextPageUrl`; ano ausente/malformado causa erro validado; sem parâmetro mantém lista completa.
- [ ] **Step 2: RED.** Rodar a suíte no GitHub Actions com bases descartáveis; falha esperada por ausência da opção e filtragem, testes anteriores preservados.
- [ ] **Step 3: produção mínima.** Após Gate, construir opções distintas por ano de matrículas ativas da escola e alunos não arquivados; validar `ano_letivo` contra essas opções; aplicar filtro no join `school_years`; passar `availableYears` e `selectedYear` à view.
- [ ] **Step 4: seletor e responsividade.** No GET form, adicionar `label` acessível e `select` com "Todos os anos"; manter valor após busca; estilo CSS limitado à página e layout móvel.
- [ ] **Step 5: GREEN.** CI: testes Laravel em duas rodadas, Pint, build, 4 verificações UI, integração MariaDB → PostgreSQL em duas rodadas. Corrigir qualquer regressão.
- [ ] **Step 6: revisão/continuidade.** Revisão somente leitura, registrar no `main` de continuidade o resultado verificado. Confirmar PR #5 aberto draft e sem merge.
