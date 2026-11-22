# Incremento 03B Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementar transferência, encerramento e exclusão justificada de matrículas com autorização, confirmação, concorrência e auditoria.

**Architecture:** `EnrollmentMovementController` concentra HTTP e autorização; `EnrollmentMovementService` executa operações atômicas com Eloquent auditado. A Policy diferencia gestão e exclusão. Cada operação possui confirmação própria via template compartilhado.

**Tech Stack:** Laravel 12, PHP 8.4, PostgreSQL 17, PHPUnit, Blade, Pint.

**Spec:** `docs/superpowers/specs/2026-11-21-incremento-03b-movimentacao-matriculas-design.md`

## Global Constraints

- Branch nova baseada no commit `f6349d6` do PR #7; não alterar PRs #6, #7, #8, #9.
- Segundo fator em POST/DELETE, acesso escolar validado na Policy, escrita auditada na mesma transação; LGPD e dados fictícios.
- Sem testes adicionais cruzando municípios; nem merge automático.
- O ambiente de execução local não tem PHP; usar CI do PR como gate de Pint e suíte.

## Review Focus

- Matrícula de outro aluno na mesma escola retorna 404.
- Versão antiga após mudança de status não altera outra matrícula.
- Data igual à data inicial e aos limites do ano é permitida; anterior é recusada.
- Matrícula em escola de destino já ativa impede transferência sem efeitos parciais.
- Aluno com segunda matrícula histórica permanece após arquivar a matrícula errada.

---

### Task 1: Transferência interna

**Files:**
- Create: `app/Services/EnrollmentMovementService.php`
- Create: `app/Http/Controllers/EnrollmentMovementController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Students/EnrollmentMovementTest.php`

**Interfaces:** `transfer(StudentEnrollment $enrollment, string $version, string $destinationId, string $date): void`.

- [ ] Escrever teste de transferência bem-sucedida com duas escolas e snapshots auditados; teste de destino inválido e duplicidade.
- [ ] Executar teste para verificar falha por rota não implementada (quando houver PHP local).
- [ ] Implementar GET de confirmação, POST com 2FA, serviço transacional sob lock de aluno/matrícula e validação de data.
- [ ] Executar teste com sucesso, verificar desaparecimento da origem e surgimento no destino.

### Task 2: Encerramento por motivo fixo

**Files:**
- Modify: `app/Services/EnrollmentMovementService.php`, `app/Http/Controllers/EnrollmentMovementController.php`, `routes/web.php`
- Create: `database/migrations/tenant/2026_11_21_000010_create_student_enrollment_destinations.php`
- Modify: `app/Audit/AuditTrail.php`
- Test: `tests/Feature/Students/EnrollmentMovementTest.php`

**Interfaces:** `end(StudentEnrollment $enrollment, string $version, string $status, string $date, ?string $destinationNote): void`.

- [ ] Testar os três status, a data recusada e os snapshots incluindo nota opcional somente quando for saída da rede.
- [ ] Executar os testes para falha esperada (quando houver PHP local).
- [ ] Implementar tabela auxiliar para destino externo, evento auditado e rota POST com validação de motivos, sem mudar a tabela histórica de matrícula.
- [ ] Executar testes e confirmar resultado.

### Task 3: Exclusão justificada, interface e regras de autorização

**Files:**
- Modify: `app/Auth/SchoolAccess.php`, `app/Policies/SchoolPolicy.php`, `app/Http/Controllers/EnrollmentMovementController.php`, `app/Services/EnrollmentMovementService.php`, `routes/web.php`, `resources/views/students/show.blade.php`, `app/Http/Controllers/SchoolStudentController.php`
- Create: `resources/views/enrollments/confirm-movement.blade.php`
- Test: `tests/Feature/Students/EnrollmentMovementTest.php`

**Interfaces:** `deleteRegistration(StudentEnrollment $enrollment, string $version, string $reason, string $actorId): void`.

- [ ] Testar exclusão auditada de matrícula e aluno sem outros vínculos, preservação de aluno com outros, matriz 403 e 404, concorrência e botões por perfil.
- [ ] Executar teste para falha esperada (quando houver PHP local).
- [ ] Implementar `deleteWithReason` dentro da transação, Policy restrita a gestor e diretor, confirmações Blade.
- [ ] Executar testes completos e Pint.

### Task 4: Documentação e CI do PR

**Files:**
- Modify: `.github/workflows/testes.yml`, `HISTORICO.md`

- [ ] Incluir a branch do PR #7 no filtro de bases do CI, mantendo somente `pull_request`.
- [ ] Registrar uma única entrada de histórico da sessão.
- [ ] Criar um único commit lógico com código, testes, docs e estilo; abrir PR próprio contra a branch do PR #7.
- [ ] Aguardar jobs concluídos com sucesso; corrigir caso haja falha; não mesclar.
