# Segundo fator obrigatório para consultas protegidas Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bloquear consultas escolares e rede de municípios para contas com TOTP ativado até confirmação válida do segundo fator.

**Architecture:** Middleware dedicado `RequireLoginTwoFactor` consulta `TwoFactorSession::isVerified()` com `User::fresh()`; retorna `redirect()->guest(route('two-factor.challenge'))` e preserva `url.intended`. Aplica-se à rota pública `/` de modo condicional e às rotas autenticadas `/m/{municipio}` antes de `ResolveMunicipality`. Fluxo TOTP existente sem mudanças.

**Tech Stack:** Laravel 13, PHP 8.4, PostgreSQL 17 em CI, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-11-21-segundo-fator-consulta-protegida-design.md`

## Global Constraints
- Somente branch `feature/migracao-postgresql-instalacoes-isoladas`; PR #5 draft, sem merge.
- Sem migration, escrita escolar, dependência externa, edição de `.env` ou acesso a bancos originais do notebook.
- Não bloquear login, logout, desafio nem configuração de TOTP.
- Preservar comportamento de contas sem 2FA e visitantes.

## Review Focus
- GET a página inicial com 2FA habilitado ainda não confirmado: não expor redes.
- GET direto às escolas/alunos antes do desafio: redirecionar antes de abrir conexão tenant.
- Sessão TOTP com mais de 15 minutos: exigir novo desafio.
- Confirmação correta: encaminhar à URL pretendida e preservar permissões.
- Conta sem segundo fator e visitante: não introduzir barreira indevida.

---

### Task 1: Proteção condicional de consulta (TDD)

**Files:**
- Create: `tests/Feature/Auth/TwoFactorReadAccessTest.php`
- Create: `app/Http/Middleware/RequireLoginTwoFactor.php`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: `TwoFactorSession::isVerified(Request $request, User $user): bool` e `Totp::at()`.
- Produces: `RequireLoginTwoFactor::handle(Request $request, Closure $next): Response`.

- [ ] Step 1: Criar testes de visitante e conta sem 2FA, tentativa direta com 2FA não confirmado, retorno à rota original após desafio e sessão expirada.
- [ ] Step 2: Executar o workflow `Testes Laravel` com apenas novos testes, verificar RED pela falha dos casos de 2FA ainda não protegido.
- [ ] Step 3: Criar middleware mínimo e adicionar nas rotas `/` e grupo `/m/{municipio}`, preservando as rotas do desafio.
- [ ] Step 4: Executar CI de novo, conferir testes PHP duas vezes, Pint, build, UI e integração.
- [ ] Step 5: Atualizar `docs/continuidade/HISTORICO.md` e `README.md` na branch main com provas verificadas; pedir teste manual em conta fictícia.
