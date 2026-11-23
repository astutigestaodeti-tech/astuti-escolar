# Declaração de matrícula - Plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Emitir declarações impressas com QR Code e verificação pública mínima, auditadas e revogáveis.

**Architecture:** Registros no banco tenant com snapshot, emissão/revogação na gestão com 2FA, consulta pública vinculada apenas ao município implantado. SVG e impressão locais, sem serviços externos.

**Tech Stack:** Laravel/PHP 8.4, PostgreSQL 17, Blade, CSS, JavaScript sem inline, GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-11-23-declaracao-matricula-74-design.md`

## Global Constraints

- Iniciar do `origin/main` em `79a2272`, migração `000032`.
- Não alterar `HISTORICO.md` ou `LICENSE`; usar somente dados fictícios; não fazer merge ou deploy.
- `manageStudents` para operações; 2FA para emissão/revogação; `throttle:20,1` e `noindex` públicos.
- Código de 10 caracteres sem `0/O/1/I`, com `random_int`; mascarado na auditoria.
- Novas telas em `AllSchoolPagesOpenTest`; margens 1440/390 com 0 problemas.

## Review Focus

- Concorrência entre duas emissões: índice único protege mesmo quando códigos colidem.
- Acesso de professor e de escola diferente: nenhuma lista, emissão, impressão ou revogação.
- Documento já revogado: não aceitar segunda revogação nem apagar prova da emissão.
- Aluno transferido depois de emitir: documento mantém dados originais, consulta continua mostrando situação de revogação.
- Configuração pública ausente ou com duas prefeituras: não escolher arbitrariamente uma delas.

### Task 1: Esquema, código e isolamento da consulta pública

**Files:** `database/migrations/tenant/2026_11_23_000032_create_issued_documents.php`, `app/Models/Tenant/IssuedDocument.php`, `app/Documents/VerificationCode.php`, `app/Http/Controllers/PublicDocumentVerificationController.php`, `routes/web.php`, `tests/Feature/Documents/EnrollmentDeclarationTest.php`.

**Interfaces:** `VerificationCode::generate(): string`, `VerificationCode::abbreviate(string): string`; `IssuedDocument` com conexão `tenant` e snapshots; `PublicDocumentVerificationController::__invoke(Request, string): View`.

- [ ] Escrever testes que exigem código de 10 caracteres, abreviação e consulta pública sem autenticação limitada ao município da instalação.
- [ ] Rodar teste e conferir falha esperada (ou registrar ausência de PHP local, verificando no CI).
- [ ] Implementar migração, modelo, código e rota pública protegida por limite de IP.
- [ ] Verificar por testes e inspeção estática; commit lógico e envio ao ramo.

### Task 2: Emissão, listagem, revogação e auditoria

**Files:** `app/Http/Controllers/EnrollmentDeclarationController.php`, `app/Audit/AuditTrail.php`, `app/Http/Controllers/SchoolStudentController.php`, `resources/views/students/show.blade.php`, `routes/web.php`, `tests/Feature/Documents/EnrollmentDeclarationTest.php`.

**Interfaces:** formulário em `GET .../declaracoes/nova`, `POST .../declaracoes`, `GET .../declaracoes/{documento}`, `POST .../declaracoes/{documento}/revogar`.

- [ ] Escrever testes de gestão, 2FA, escola, matrícula ativa, snapshots, revogação, conflito e auditoria mascarada.
- [ ] Confirmar RED via CI ou registrar bloqueio local.
- [ ] Implementar transações e autorização restrita, listagem na ficha e proteção de auditoria.
- [ ] Verificar e registrar commit lógico no ramo remoto.

### Task 3: A4, QR Code e tela pública minimizada

**Files:** `app/Support/Qr/VerificationQrCode.php`, `resources/views/documents/*.blade.php`, `public/css/astuti.css`, `public/js/declaracao-imprimir.js`, `tests/Feature/Documents/EnrollmentDeclarationTest.php`, `tests/Visual/EnrollmentDeclarationPrint.test.mjs`.

- [ ] Escrever testes de renderização, dados proibidos, `noindex`, QR SVG e botão de impressão.
- [ ] Confirmar RED quando possível, implementar a impressão via CSS/JS local e as telas responsivas.
- [ ] Rodar testes JavaScript, build, checagens de segurança e commitar.

### Task 4: Layout, PR e CI

**Files:** `tests/Feature/Layout/AllSchoolPagesOpenTest.php` e ajustes pontuais do bloco.

- [ ] Incluir formulário, impressão e consulta pública no teste de abertura de telas.
- [ ] Rodar o dump e `checar-margens.py` em 1440/390, registrar impedimento se não houver PHP.
- [ ] Enviar branch, abrir PR pronto para revisão, conferir CI/Pint; corrigir falhas e reenviar.
- [ ] Depois de CI verde, comentar PR na issue #74 e entregar resumo sem realizar merge.
