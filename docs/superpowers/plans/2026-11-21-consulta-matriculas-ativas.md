# Consulta de matrículas ativas - Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:test-driven-development and superpowers:verification-before-completion.

**Goal:** listar matrículas ativas por unidade autorizada, com busca e paginação, sem escrita.

**Spec:** docs/superpowers/specs/2026-11-21-consulta-matriculas-ativas-design.md

## Task 1 - Consulta segura de matrículas
1. Criar `tests/Feature/Students/SchoolEnrollmentIndexTest.php`: usuário da A1 vê somente matrícula ativa da A1 e dados do aluno, ano e data; não vê concluída, A2 ou município B; visitante e escola proibida não acessam; busca literal `%` e pesquisa minúscula; links corretos; paginação. **Expected:** falhar com 404 inicial pela rota ausente.
2. Verificar RED no CI PostgreSQL descartável; inspecionar logs. **Expected:** novos testes falham, anteriores passam.
3. Criar `app/Http/Controllers/SchoolEnrollmentController.php`: validar escola/Gate, consulta restrita, excluir alunos arquivados, busca literal, ordenação, paginação.
4. Adicionar GET em `routes/web.php` dentro da área municipal autenticada e protegida por TOTP.
5. Criar `resources/views/enrollments/index.blade.php` seguindo componente `folha`, tabela responsiva e paginação; vincular em `resources/views/schools/show.blade.php`.
6. Verificar GREEN CI inteiro: testes PHP duas vezes, Pint, build, testes UI e migração descartável. **Expected:** tudo aprovado.
7. Revisão independente sem escrita, registro na continuidade do `main`, PR #5 draft.

**Proibições:** nenhuma migração nova, escrita em dados reais, manipulação de banco original, merge ou atualização de `.env`.