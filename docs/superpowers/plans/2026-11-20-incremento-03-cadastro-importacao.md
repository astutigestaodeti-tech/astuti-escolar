# Incremento 03: Cadastro e Importação Inteligente - Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Entregar cadastro auditado de alunos e importação CSV/XLSX por etapas, com prévia, conferência humana e confirmação explícita.

**Architecture:** Manter a identidade do aluno separada dos vínculos escolares no banco `tenant`. Preparar cada linha importada em tabelas transitórias municipais; validar, conferir e só então aplicar mudanças numa transação auditada. A autenticação 2FA na plataforma é pré-requisito das novas permissões de escrita.

**Tech Stack:** PHP 8.4, Laravel 13, MariaDB, Blade, CSS Astuti, PHPUnit, Pint, Vite; biblioteca PHP de leitura XLSX mantida e auditada; mecanismo TOTP mantido de autenticação, preferencialmente Laravel Fortify após verificação de compatibilidade com as rotas atuais.

**Spec:** `docs/superpowers/specs/2026-11-20-incremento-03-cadastro-importacao-design.md`

## Global Constraints

- PR #3 precisa de revisão e aprovação antes de abrir a branch de implementação baseada nele. Não fazer merge de código não revisado.
- Apenas dados fictícios do kit Monte Sereno v2; obter os arquivos originais para o aceite de 115 alunos, três escolas e ano letivo 2027.
- Identidade municipal fisicamente isolada e contexto A/B/A restaurado ao final de cada requisição.
- Nenhuma escrita escolar autorizada antes de segundo fator validado, permissão de escola e registro de auditoria disponível.
- Upload somente CSV/XLSX, máximo de 10 MiB e 2.000 linhas; nada de fórmulas executadas, nem armazenamento público.
- Sem fusão automática por semelhança de nomes. Histórico acadêmico nunca é substituído ou apagado fisicamente.
- Interface e textos em português, padrão `docs/design/identidade-visual.md`, responsiva a partir de 320 px.
- Cada alteração de domínio tem teste de sucesso, acesso negado, erro e isolamento, incluindo duas execuções integrais sobre os mesmos bancos descartáveis.

## Review Focus

1. CSV com BOM, ponto-e-vírgula, acentos e datas brasileiras: teste em Task 3 deve conservar texto exato e converter datas sem trocar mês e dia.
2. XLSX com fórmula, aba extra ou arquivo ZIP adulterado: teste em Task 3 não deve avaliar expressão nem executar código e deve sinalizar formato inválido.
3. Mesmo nome e nascimento em escolas distintas: teste em Task 5 deve sinalizar duplicidade possível sem fundir alunos.
4. Sessão expirada, revogação de permissão ou falha da auditoria entre prévia e confirmação: teste em Task 6 deve bloquear ou reverter qualquer gravação.
5. Reenvio do mesmo lote e duas solicitações simultâneas: teste em Task 6 deve impedir cadastro ou matrícula duplicada.

---

### Task 1: Segundo fator obrigatório para operações de escrita

**Files:**
- Modify: `app/Models/User.php`, `app/Http/Controllers/Auth/LoginController.php`, `routes/web.php`, `bootstrap/app.php`, `composer.json`
- Create: `database/migrations/platform/2026_11_20_000009_add_two_factor_to_platform_users.php`
- Create: `app/Http/Middleware/RequireConfirmedTwoFactor.php`
- Create: `tests/Feature/Auth/TwoFactorSchoolWriteTest.php`
- Create: `resources/views/auth/two-factor-challenge.blade.php` (ou view equivalente do provedor escolhido)

**Interfaces:**
- Consumes: autenticação e auditoria de plataforma existentes.
- Produces: middleware `RequireConfirmedTwoFactor::handle(Request $request, Closure $next): Response`, aplicado **somente** às rotas de escrita escolar; sessão marcada como verificada após desafio TOTP válido. Desafio expirado/revogado limpa o marcador.

- [ ] **Step 1: Write failing tests**: validar que uma sessão apenas com senha não grava; desafio TOTP válido habilita gravação; código inválido/expirado, segundo fator desativado e logout não habilitam gravação; nenhuma rota pública de registro; login/logout/auditoria existentes preservados.
- [ ] **Step 2: Verify tests fail**: `php artisan test --filter=TwoFactorSchoolWriteTest` deve falhar por ausência do middleware/desafio.
- [ ] **Step 3: Implement** TOTP via pacote mantido compatível com Laravel 13, sem registrar automaticamente rotas conflitantes com o login existente; migração de credenciais somente no `platform`, segredo cifrado e códigos de recuperação protegidos; desafios limitados por tentativas.
- [ ] **Step 4: Verify passing**: `php artisan test --filter=TwoFactorSchoolWriteTest` e `php artisan test --filter=LoginSessionTest` passam.
- [ ] **Step 5: Commit**: `git add ...` e `git commit -m "feat(auth): exigir 2fa para escrita escolar"`.

### Task 2: Domínio de cadastro e matrícula com proteção por escola

**Files:**
- Create: `database/migrations/tenant/2026_11_20_000008_extend_student_identity_and_enrollments.php`
- Modify: `app/Models/Tenant/Student.php`, `app/Models/Tenant/StudentEnrollment.php`, `app/Policies/SchoolPolicy.php`
- Create: `app/Policies/StudentPolicy.php`, `app/Students/StudentRegistration.php`
- Create: `tests/Feature/Students/StudentRegistrationTest.php`

**Interfaces:**
- Consumes: `App\Auth\SchoolAccess`, `App\Audit\AuditTrail`, `Student`, `StudentEnrollment`, `SchoolClass` e middleware da Task 1.
- Produces: `StudentRegistration::create(array $student, array $enrollment, User $actor): Student`; `StudentRegistration::update(Student $student, array $changes, User $actor): Student`; `StudentRegistration::enroll(Student $student, array $enrollment, User $actor): StudentEnrollment`. Todo método exige contexto municipal, permissão e transação auditada.

- [ ] **Step 1: Write failing tests**: incluir aluno com nome obrigatório e nascimento opcional; matrícula associada a escola/ano; turma opcional pertence à mesma escola e ano; vínculo anterior não é modificado; professor/escola fora de escopo/município B não escrevem; falha de auditoria aborta a transação.
- [ ] **Step 2: Verify failures**: `php artisan test --filter=StudentRegistrationTest` falha com serviços e schema ausentes.
- [ ] **Step 3: Implement** referência escolar externa opcional e `school_class_id` anulável com FKs `restrictOnDelete`, índices, validação cruzada e políticas de negação padrão; impedir update de atributos protegidos por mass assignment.
- [ ] **Step 4: Verify**: `php artisan test --filter=StudentRegistrationTest` e testes antigos de `StudentStorageTest` passam.
- [ ] **Step 5: Commit**: `git add ...` e `git commit -m "feat(alunos): cadastrar e vincular com auditoria"`.

### Task 3: Leitores seguros CSV e XLSX com mapeamento explícito

**Files:**
- Modify: `composer.json`, `composer.lock`
- Create: `app/Imports/SpreadsheetReader.php`, `app/Imports/ColumnMapping.php`, `app/Imports/ImportRowValidator.php`
- Create: `tests/Unit/Imports/SpreadsheetReaderTest.php`, `tests/Unit/Imports/ColumnMappingTest.php`
- Create: `tests/Fixtures/Imports/` com arquivos pequenos e inteiramente fictícios.

**Interfaces:**
- Produces: `SpreadsheetReader::read(string $path, string $extension): iterable` devolve cabeçalhos e linhas como valores literais; `ColumnMapping::normalize(array $headers, array $mapping, array $row): array` produz campos conhecidos; `ImportRowValidator::validate(array $row, array $defaults): array` produz dados aceitos ou erros estruturados.

- [ ] **Step 1: Write failing tests**: CSV UTF-8/BOM, separadores vírgula/ponto-e-vírgula, acentos, linhas vazias, datas `dd/mm/aaaa` e ISO, XLSX com célula de fórmula literal, arquivo inválido, 2.001 linhas e tamanho acima de 10 MiB, nomes de coluna inesperados.
- [ ] **Step 2: Verify failures**: `php artisan test --filter=SpreadsheetReaderTest` e `--filter=ColumnMappingTest` falham.
- [ ] **Step 3: Implement** parser em leitura progressiva, biblioteca XLSX sem avaliação de fórmulas, validação MIME mais extensão, sem busca de referências externas e sem abrir arquivos em diretório público.
- [ ] **Step 4: Verify**: os dois grupos de testes passam.
- [ ] **Step 5: Commit**: `git add ...` e `git commit -m "feat(importacao): ler planilhas com seguranca"`.

### Task 4: Lotes de importação no banco municipal e prévia

**Files:**
- Create: `database/migrations/tenant/2026_11_20_000009_create_student_import_batches.php`
- Create: `app/Models/Tenant/StudentImportBatch.php`, `app/Models/Tenant/StudentImportRow.php`
- Create: `app/Imports/StudentImportDraft.php`
- Create: `app/Http/Controllers/StudentImportController.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/Imports/StudentImportDraftTest.php`

**Interfaces:**
- Consumes: readers da Task 3, escola/ano da Task 2.
- Produces: `StudentImportDraft::create(User $actor, School $school, UploadedFile $file): StudentImportBatch`; `StudentImportDraft::map(StudentImportBatch $batch, array $mapping, array $defaults): StudentImportBatch`; `StudentImportDraft::review(StudentImportRow $row, string $decision, array $corrections): StudentImportRow`; 
- Routes: `GET/POST /m/{municipio}/escolas/{escola}/importacoes`, `GET/PATCH /m/{municipio}/escolas/{escola}/importacoes/{lote}`; uploads exigem auth, escopo de escola e 2FA nas mudanças.

- [ ] **Step 1: Write failing tests**: upload gera lote sem criar aluno; mapeamento e correções são armazenados apenas em tabelas de preparação; erros por linha visíveis; usuário de A2/B não acessa lote de A1; arquivos temporários não expostos; cancelamento/expiração de sete dias descarta pendências.
- [ ] **Step 2: Verify failures**: `php artisan test --filter=StudentImportDraftTest` falha.
- [ ] **Step 3: Implement** batches/rows com UUID, dono, escola, status, fingerprint e decisões; dados privados no tenant, arquivo temporário privado removido após extração; limpeza de lotes expirados por comando agendado e testado.
- [ ] **Step 4: Verify**: grupo de testes passa.
- [ ] **Step 5: Commit**: `git add ...` e `git commit -m "feat(importacao): preparar lotes para conferencia"`.

### Task 5: Duplicidades sem fusão automática

**Files:**
- Create: `app/Imports/StudentMatchCandidates.php`
- Modify: `app/Imports/StudentImportDraft.php`
- Create: `tests/Feature/Imports/StudentDuplicateReviewTest.php`

**Interfaces:**
- Produces: `StudentMatchCandidates::find(array $normalizedRow): array` devolve possíveis correspondências e razões, nunca altera entidades. Decisão explícita de criar identidade, vincular a uma identidade existente ou descartar.

- [ ] **Step 1: Write failing tests**: código externo exato sugere aluno; nome/data coincidentes apenas alertam; homônimos de escolas diferentes não são unidos; decisões tentam manter identidade e matrícula anteriores; ambiguidade bloqueia aprovação automática.
- [ ] **Step 2: Verify failure**: `php artisan test --filter=StudentDuplicateReviewTest` falha.
- [ ] **Step 3: Implement** busca restrita ao tenant e decisão humana gravada no lote, sem revelar informações de escola não autorizada.
- [ ] **Step 4: Verify**: teste passa.
- [ ] **Step 5: Commit**: `git add ...` e `git commit -m "feat(importacao): conferir possiveis duplicidades"`.

### Task 6: Confirmação transacional, idempotência e auditoria

**Files:**
- Create: `app/Imports/StudentImportCommitter.php`
- Modify: `app/Http/Controllers/StudentImportController.php`, `routes/web.php`
- Create: `tests/Feature/Imports/StudentImportCommitTest.php`

**Interfaces:**
- Consumes: `StudentRegistration` da Task 2, lotes da Task 4 e decisões da Task 5.
- Produces: `StudentImportCommitter::commit(StudentImportBatch $batch, User $actor): array` com quantidade criada/vinculada/ignorada; rota `POST /m/{municipio}/escolas/{escola}/importacoes/{lote}/confirmar` exige 2FA, autorização renovada e confirmação explícita.

- [ ] **Step 1: Write failing tests**: sem aprovação não grava; somente linhas aprovadas; confirmação repetida não duplica; lote concorrente não passa duas vezes; falha em uma linha ou auditoria faz rollback; 2FA expirado e vínculo revogado bloqueiam; A/B/A continua correto.
- [ ] **Step 2: Verify failure**: `php artisan test --filter=StudentImportCommitTest` falha.
- [ ] **Step 3: Implement** bloqueio de lote `lockForUpdate`, status final único, chaves de idempotência e transaction na conexão `tenant`; auditoria na mesma transação, mantendo histórico e sem expor planilha nos logs.
- [ ] **Step 4: Verify**: grupo passa duas vezes no mesmo banco descartável.
- [ ] **Step 5: Commit**: `git add ...` e `git commit -m "feat(importacao): confirmar lotes auditados e idempotentes"`.

### Task 7: Telas Astuti, gabarito e verificação integral

**Files:**
- Create: `resources/views/students/create.blade.php`, `resources/views/students/edit.blade.php`
- Create: `resources/views/imports/index.blade.php`, `resources/views/imports/show.blade.php`
- Modify: `resources/views/students/index.blade.php`, `resources/views/schools/show.blade.php`, `public/css/astuti.css`, `routes/web.php`, `.github/workflows/testes.yml`
- Create: `tests/Feature/Imports/MonteSerenoImportAcceptanceTest.php`
- Create: `tests/Feature/Imports/StudentImportVisualTest.php`
- Modify: `docs/arquitetura/autorizacao-escolar.md`, `README.md`

**Interfaces:**
- Consumes: serviços/controllers das Tasks 1-6; não alterar a API de importação para atender exclusivamente à view.

- [ ] **Step 1: Write failing UI/acceptance tests**: formulário só para papéis com escrita e 2FA; passos upload/mapeamento/conferência/confirmar acessíveis por teclado e 320 px; nenhum conteúdo cru não escapado; estados vazios, erros e alertas traduzidos.
- [ ] **Step 2: Verify failure**: `php artisan test --filter=StudentImportVisualTest` falha antes das views.
- [ ] **Step 3: Implement** Blade usando os componentes Astuti, sem fontes remotas e com feedback claro de pendências e totais.
- [ ] **Step 4: Add and run Monte Sereno v2 acceptance** **somente após obter arquivos originais**: os documentos da pasta fonte devem produzir o mesmo conjunto escolar do gabarito (115 alunos fictícios, três escolas, 2027) após decisões humanas documentadas. Se faltarem arquivos, marcar o teste como dependente de fixture ausente, nunca como aprovado.
- [ ] **Step 5: Verify full suite**: `php artisan test` duas vezes sobre bancos MariaDB temporários, `vendor/bin/pint --test`, `npm ci`, `npm run build`, testes de identidade visual e `git diff --check`; todos devem passar.
- [ ] **Step 6: Review and commit**: inspeção independente, correções verificadas, `git commit -m "feat(importacao): entregar fluxo escolar de ponta a ponta"`, abrir PR em rascunho para revisão independente do Claude.

## Self-review do plano

- Escopo coberto: 2FA, escrita auditada, vínculo acadêmico, CSV/XLSX, mapeamento, conferência, duplicidades, confirmação transacional, telas, isolamento, privacidade e qualidade.
- Não promete importação dos 115 alunos sem arquivos oficiais: esse aceite fica explicitamente pendente até receber o kit v2.
- Ordem intencional: 2FA → domínio → parser → preparação → duplicidades → confirmação → interface e gabarito.
- Executar somente após aprovação do PR #3, respeitando o pedido de revisão prévia do proprietário.
