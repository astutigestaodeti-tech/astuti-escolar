# Endereço manual do aluno - Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Persistir e apresentar cinco dados opcionais de endereço do aluno com validação, auditoria e escopo escolar existente.

**Architecture:** Colunas adicionadas por migração municipal reversível; modelo Student e lista segura AuditTrail ampliados; controlador normaliza e valida apenas campos enviados; formulário e ficha exibem os valores sem tocar importadores.

**Tech Stack:** Laravel/PHP 8.4, PostgreSQL 17, Blade, Pint e PHPUnit.

**Spec:** `docs/superpowers/specs/2026-11-21-endereco-manual-aluno-design.md`

## Global Constraints
- Base exclusiva: PR #18, head `d05afe5`; PR novo e sem merge.
- Testes e implementação no mesmo commit. Nada de commits isolados vermelhos.
- Proibido alterar importação, 2FA, permissões ou bancos originais.

## Review Focus
- Oito dígitos de CEP devem virar `NNNNN-NNN`, mas letras não podem ser silenciosamente removidas.
- Ao atualizar só o nome, os dados já cadastrados de endereço não devem desaparecer.
- Campo enviado vazio deve limpar o valor de endereço sem invalidar o formulário.
- `NULL` precisa ser válido no banco; zona fora de `urban/rural` deve falhar inclusive fora do controlador.
- Um usuário vinculado a outra escola não pode abrir nem alterar o endereço do aluno.

## Task 1 - Persistência e auditoria

**Files:**
- Create: `database/migrations/tenant/2026_11_21_000014_add_student_address_fields.php`
- Modify: `app/Models/Tenant/Student.php` e `app/Audit/AuditTrail.php`
- Test: `tests/Feature/Students/StudentRecordWriteTest.php`

**Interfaces:** `Student` expõe `address_street`, `address_number`, `address_district`, `postal_code`, `residence_zone`. Auditoria `created/updated` contém os mesmos nomes.

- [ ] Criar testes de persistência, auditoria e `CHECK` que falham antes da migration.
- [ ] Verificar falha isolada por falta de coluna/constraint.
- [ ] Criar migration aditiva, atualizar fillable e allowlist, com down reversível.
- [ ] Verificar teste em PostgreSQL 17.

## Task 2 - Controlador e interface

**Files:**
- Modify: `app/Http/Controllers/StudentRecordController.php`
- Modify: `resources/views/students/form.blade.php`, `resources/views/students/show.blade.php`
- Modify: `public/css/astuti.css`
- Test: `tests/Feature/Students/StudentRecordWriteTest.php`

**Interfaces:** `store/update` validam cinco campos opcionalmente; `normalizePostalCode` aceita oito dígitos ou CEP pontuado; edição preserva campos ausentes.

- [ ] Testar formulário, ficha, CEP inválido, zona inválida, edição e acesso negado por escola.
- [ ] Verificar que os testes falham no código original.
- [ ] Implementar normalização, validação, campos Blade e grupo visual.
- [ ] Reexecutar os testes, corrigindo falhas da implementação.

## Task 3 - Verificação e entrega

**Files:** Incluir as alterações das tarefas 1 e 2, especificação, plano e uma entrada em `HISTORICO.md` no mesmo commit.

- [ ] Rodar `vendor/bin/pint --test` e `php artisan test` no ambiente disponível; se faltar PHP local, usar CI do PR e declarar limite.
- [ ] Criar branch filha de PR #18 e PR novo com teste e implementação juntos no mesmo commit.
- [ ] Aguardar CI verde (Pint, testes, interface, migração MariaDB/PostgreSQL).
- [ ] Conferir diff, base exata e isolamento; entregar número do PR e hash sem merge.
