# Endereço assistido na importação de alunos - Plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Aproveitar o endereço informado pela escola na importação, inclusive completando somente lacunas de alunos já cadastrados após confirmação individual.

**Architecture:** O leitor estende o objeto `ImportedStudent` com CEP/zona, sem gravar. O controlador coloca os cinco campos na prévia cifrada e só aplica alterações a alunos reconhecidos, vinculados à escola e ano, revalidados sob advisory lock. O Eloquent registra os dados anteriores e posteriores; nenhuma nova tabela.

**Tech Stack:** Laravel 12, PHP 8.4, Blade, PostgreSQL 17, PHPUnit/Pest, Pint.

**Spec:** `docs/superpowers/specs/2026-11-22-importacao-endereco-alunos-design.md`

## Global Constraints

- Um PR novo baseado no #31; não alterar PRs anteriores nem mesclar.
- Teste, implementação e formatação em um mesmo commit lógico.
- Sem tocar em banco original 5432, MariaDB, backups ou `.env`.
- Prévia cifrada e expiração mantidas, 2FA e isolamento escolar preservados.
- Dados só vêm da fonte enviada; campos preenchidos não são sobrescritos.

## Review Focus

- RA existente com nome divergente não pode oferecer atualização de endereço.
- Nome e nascimento com mais de uma pessoa compatível não podem atualizar alguém arbitrariamente.
- Importação com duas escolas não pode completar endereço de aluno fora do vínculo da escola selecionada.
- Alteração de endereço entre prévia e confirmação não pode ser sobrescrita.
- CEP/zona inválidos não podem entrar em `students` nem no token como dados persistíveis.

### Task 1: Leitura dos campos opcionais

**Files:** `app/Import/Students/StudentSpreadsheetPreview.php`, `app/Import/Students/ImportedStudent.php`, `app/Import/Students/ImportText.php`, `tests/Unit/Import/KitMonteSerenoStudentImportTest.php`.

**Interfaces:** `ImportedStudent` expõe `postalCode` e `residenceZone` anuláveis; o leitor produz rua/número/bairro/CEP/zona sem inferir outros dados.

- [ ] Adicionar testes para leitura de colunas separadas, CEP com oito dígitos, zona Urbana/Rural, e valores fora do formato como pendência.
- [ ] Executar os testes para observar falha antes da implementação (se houver PHP local; caso contrário, registrar a limitação).
- [ ] Implementar sinônimos e normalização estrita sem alterar a leitura do kit E02.
- [ ] Verificar testes específicos e suíte completa; salvar testes e código no mesmo commit lógico.

### Task 2: Prévia e confirmação da complementação segura

**Files:** `app/Http/Controllers/ConfirmedStudentImportController.php`, `resources/views/imports/review.blade.php`, `tests/Feature/Import/ConfirmedStudentImportTest.php`.

**Interfaces:** `rows[].address` contém os cinco campos; `completar_enderecos[]` referencia índices da prévia que correspondem a um aluno elegível e vinculado à escola.

- [ ] Adicionar testes de cadastro novo, nenhuma atualização sem seleção, complemento individual só em campos vazios, auditoria, isolamento e rechecagem sob lock.
- [ ] Executar os testes para observar falha antes da implementação quando houver ambiente PHP.
- [ ] Adicionar apresentação do endereço e marcação individual sem alterar homônimos/duplicidades.
- [ ] Aplicar as gravações na transação existente, com validação de identidade, escola/ano e preenchimento somente das lacunas.
- [ ] Verificar testes específicos, Pint, suíte completa, e registrar no histórico consolidado.

### Task 3: Verificação e entrega

**Files:** `docs/continuidade/HISTORICO.md` (ou `HISTORICO.md` se único existente).

- [ ] Conferir diff, regras do AGENTS.md e documentação.
- [ ] Criar um commit lógico e PR rascunho sobre #31, com testes e implementação juntos.
- [ ] Aguardar CI completo verde, corrigindo a própria branch se necessário, sem merge ou deploy.
- [ ] Entregar número do PR, hash, testes e passos para homologação no Acer isolado.
