# Transporte escolar: detalhes operacionais Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ler, conferir, gravar e consultar linha, ponto e indicação de veículo adaptado no transporte escolar.

**Architecture:** Estender três colunas anuláveis da matrícula e ampliar o CSV e o token de conferência existente. Uma única atualização Eloquent por matrícula mantém transação, conflito `xmin` e auditoria automática.

**Tech Stack:** Laravel/PHP, Blade, PostgreSQL 17, PHPUnit/Pest do projeto, Pint.

**Spec:** `docs/superpowers/specs/2026-11-22-transporte-detalhes-design.md` e issue #49.

## Global Constraints

- Somente dados fictícios, alterações em `origin/main` via PR próprio.
- Não editar `students/show.blade.php`, `guardians/panel.blade.php`, `pickups/panel.blade.php`, `exit/*`, migrações `000021` e `000023`.
- Seguir `docs/arquitetura/lgpd-by-design.md`; não revelar ponto ou adaptação na auditoria.
- Testes e implementação no mesmo commit; sem merge automático.

## Review Focus

- Células vazias, colunas ausentes e `NÃO` explícito: preservar ou atualizar corretamente (testes de leitor e gravação).
- Cabeçalho alternativo duplicado e adaptação inválida: recusar com mensagem clara (testes unitários).
- Atualização concorrente entre prévia e confirmação: rejeitar sem efeito parcial (teste de integração).
- Linha de escola diferente: não revelar dados nem retornar em filtro (testes de permissão).
- Campo muito longo e codificação Windows-1252: erro legível e preservação do arquivo de origem (testes unitários e fixture).

### Task 1: Leitura, campos e limites

**Files:** `app/Import/Transport/TransportCsvReader.php`, migração tenant `000022`, `app/Models/Tenant/StudentEnrollment.php`, `tests/Unit/Import/TransportCsvReaderTest.php`.

- [ ] Testar cabeçalhos com e sem acento, Sim/Não/S/N/X, ausências, limites e CSV fictício.
- [ ] Verificar testes falhando pela ausência das novas colunas na leitura.
- [ ] Implementar normalização, limites, cast booleano e migração anulável.
- [ ] Rodar testes unitários, sem falhas.

### Task 2: Prévia, confirmação e auditoria

**Files:** `app/Http/Controllers/ConfirmedTransportImportController.php`, `resources/views/imports/transport-review.blade.php`, `app/Audit/AuditTrail.php`, `tests/Feature/Import/ConfirmedTransportImportTest.php`.

- [ ] Testar prévia legível, gravação com auditoria, vazio sem limpar, falso explícito e conflito concorrente.
- [ ] Verificar ausência dos campos como falha esperada.
- [ ] Incluir campos no token autenticado e revalidar tipos e limites no confirm, sob lock; atualizar uma vez por matrícula.
- [ ] Executar a suíte de importação e conferir atomicidade/isolamento.

### Task 3: Consulta por escola e documentação

**Files:** `app/Http/Controllers/SchoolStudentController.php`, `resources/views/students/index.blade.php`, `resources/views/students/partials/transporte.blade.php`, `tests/Feature/Students/SchoolStudentIndexTest.php`, `docs/arquitetura/importacao-transporte.md`.

- [ ] Testar filtro por adaptação, contagem, linha na lista, ponto e adaptação na ficha, professor autorizado e escola diversa 403.
- [ ] Verificar falha dos testes novos antes da interface.
- [ ] Implementar filtro agregado e partial detalhado, sem editar `show`.
- [ ] Rodar Pint, suíte completa e CI PostgreSQL, revisar diff e abrir PR para `main`.
