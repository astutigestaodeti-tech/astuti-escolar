# Lista da turma - plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans. Steps use checkbox syntax.

**Goal:** Tela de turma com ocupação, alunos, idade na data de corte e contato principal sem N+1.

**Architecture:** `SchoolClassController::show` consulta matrícula ativa na escola/ano/turma, carrega aluno e responsável principal em lote, ordena sem acentos e chama `Student::ageOn()` e `ClassAgeRule::allowedAges()`. Uma view própria exibe resumo, barra, aviso e linhas.

**Tech Stack:** Laravel 13, PHP 8.4, PostgreSQL 17, Blade, Pest/PHPUnit.

**Spec:** `docs/superpowers/specs/2026-11-22-lista-turma-design.md`

## Global Constraints
- Base direta `origin/main`, novo PR com CI verde, sem merge.
- Nenhuma migração, escrita de dados, importadores ou arquivos do segundo fator.
- Nomes de pessoa só fictícios em testes.
- Não criar CSS genérico `.cartao`.

## Review Focus
- Código de turma ambíguo: nenhuma classificação sem evidência.
- Data de nascimento ausente ou posterior à data de corte: não sinalizar automaticamente.
- Matrícula ativa em outra turma/escola: nunca aparecer na lista nem afetar ocupação.
- Contato principal ausente: página mantém leitura sem erro nem consulta adicional.
- Capacidade nula e acima de 100%: informação honesta e barra sem extrapolar visualmente.

## Task 1 - Regra de idade isolada
**Files:** criar `app/Support/ClassAgeRule.php`; testar `tests/Unit/ClassAgeRuleTest.php`.
- [ ] Criar os testes para Pré I/II, pré-escola genérica (4/5), 1º/9º ano, 1A, EJA e multisseriada.
- [ ] Verificar RED no ambiente de testes disponível; sem PHP local, registrar bloqueio e usar CI para GREEN.
- [ ] Implementar `public static function expectedAge(string $stage, string $name): ?int`.
- [ ] Verificar testes e Pint.

## Task 2 - Consulta e apresentação
**Files:** modificar `Student.php`, `SchoolClassController.php`, `routes/web.php`, `classes/index.blade.php`, `layouts/municipal.blade.php`, `public/css/astuti.css`; criar `classes/show.blade.php` e `SchoolClassRosterTest.php`.
**Interfaces:** `Student::primaryGuardianLink(): HasOne`; `SchoolClassController::show(Request, string, string, string): View`; rota `school.classes.show`; `ClassAgeRule::allowedAges`.
- [ ] Testar navegação, consulta e segurança; ordem sem acentos; idade em 31/03; capacidade normal, excedida e ausente; contato principal; escolas autorizadas e sem N+1.
- [ ] Verificar RED, quando o ambiente permitir.
- [ ] Implementar carregamento com `with(['student.primaryGuardianLink.guardian'])`, cálculo com `ageOn()` e view.
- [ ] Verificar PHP, Pint, testes e interface.

## Task 3 - Entrega
- [ ] Registrar uma entrada de sessão no histórico.
- [ ] Revisar mudanças e conferir ausência de arquivos de importação.
- [ ] Criar um commit contendo testes e implementação e PR novo sobre main.
- [ ] Aguardar o CI integral verde e informar número, hash e limites de verificação.
