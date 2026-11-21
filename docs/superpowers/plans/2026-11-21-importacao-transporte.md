# Importação controlada do transporte - Plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Atualizar transporte nas matrículas ativas após conferência humana e segundo fator, sem criar alunos nem reativar cancelamentos.

**Architecture:** Um leitor CSV puro devolve registros normalizados. O controlador municipal reconcilia RAs autorizados, gera prévia cifrada por uma hora e grava em transação com `xmin`, auditoria e recibo idempotente.

**Tech Stack:** Laravel/PHP 8.4, PostgreSQL 17, Blade, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-11-21-importacao-transporte-design.md`

## Global Constraints

- Uma VPS/Prefeitura, banco municipal isolado, escopo de escola/perfil e 2FA.
- Sem guardar CSV, sem dados reais, sem reativar CANCELADO, sem escrita parcial.
- PR separado baseado no #13; sem merge/deploy, somente CI sobre pull_request.

## Review Focus

- UTF-8 e Windows-1252 com acentos: teste unitário de nomes e `NÃO`.
- Linha com RA repetido: teste que bloqueia o lote sem gravação.
- RA de outra escola: teste 422 sem revelar dados externos.
- 2FA ausente e professor: teste 403/redirecionamento.
- Tentativa de reativar `cancelled` e alteração concorrente de `xmin`: testes sem atualização e sem recibo.

---

### Task 1: Leitura CSV e estado por matrícula

**Files:** `app/Import/Transport/TransportCsvReader.php`, `tests/Unit/Import/TransportCsvReaderTest.php`, migration `2026_11_21_000012_add_transport_status_and_receipts.php`, `app/Models/Tenant/StudentEnrollment.php`, `app/Audit/AuditTrail.php`.

**Interfaces:** `TransportCsvReader::read(string $path): array` devolve linhas `{ra,name,status,line}` em valores canônicos; `StudentEnrollment.transport_status` é auditável.

- [ ] Testar parsing, normalização, duplicidade e rejeição de formatos inválidos.
- [ ] Confirmar falha pela ausência do leitor, quando houver runtime PHP.
- [ ] Implementar leitor e migração mínima.
- [ ] Confirmar verde local ou no CI, incluindo o cenário PostgreSQL.

### Task 2: Conferência e confirmação transacional

**Files:** `app/Http/Controllers/ConfirmedTransportImportController.php`, `routes/web.php`, views `imports/transport-upload.blade.php`, `imports/transport-review.blade.php`, `resources/views/schools/show.blade.php`, `tests/Feature/Import/ConfirmedTransportImportTest.php`, fixture de teste.

**Interfaces:** GET index, POST preview, POST confirm; token cifrado por `Crypt`, conferência de RA e versão `xmin` no momento da gravação.

- [ ] Testar prévia sem escrita, resultado auditado, cancelamento, replay, escopo/2FA, concorrência e atomicidade.
- [ ] Confirmar teste vermelho se o ambiente permitir.
- [ ] Implementar interface, controlador e rotas.
- [ ] Rodar Pint e suíte; corrigir até CI verde.

### Task 3: Histórico e entrega

**Files:** `docs/arquitetura/importacao-alunos.md`, `docs/arquitetura/importacao-transporte.md`, `HISTORICO.md`.

- [ ] Documentar layout admitido e limites, com uma entrada no histórico.
- [ ] Fazer um commit lógico, criar PR sobre #13 e aguardar o CI.
- [ ] Revisar diferença e informar PR/hash/testes para o ensaio no notebook; sem merge.
