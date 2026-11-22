# Cadastro manual de turmas - plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox syntax for tracking.

**Goal:** Criar e editar turmas auditadas por escola e ano, com controle de acesso e 2FA.

**Architecture:** Extender SchoolClassController e routes/web.php; reutilizar SchoolPolicy::manageStudents, HasSchoolHistory e as colunas existentes. Adicionar índice PostgreSQL parcial para nomes ativos normalizados e formulário Blade próprio.

**Tech Stack:** Laravel/PHP 8.4, Blade, PostgreSQL 17, PHPUnit/Pest, Laravel Pint.

**Spec:** docs/superpowers/specs/2026-11-21-cadastro-manual-turmas-design.md

## Global Constraints
- Não tocar nos middlewares e controladores de segundo fator, importações ou ramos anteriores.
- Testes, produção, formatação e documentação no mesmo commit.
- Usar apenas bancos de testes descartáveis; nenhum deploy, merge ou migração real.

## Review Focus
- Criar duas turmas simultâneas com nome igual: índice impede a segunda.
- Usuário de outra escola: não consegue acessar formulário nem escrever.
- POST antigo sem token: middleware bloqueia.
- Ano já encerrado: não pode receber turma nova.
- Edição com versão antiga: não sobrescreve dados.
- Ano com matrículas: não pode ser trocado.

## Tarefa 1: integridade e testes
- [ ] Escrever tests/Feature/Schools/SchoolClassWriteTest.php para todos os cenários.
- [ ] Conferir que a rota e o fluxo não existem no topo #24.
- [ ] Criar migração municipal aditiva com índice único parcial em school_classes.
- [ ] Verificar cenários negativos no CI sem commit vermelho isolado.

## Tarefa 2: gravação e auditoria
- [ ] Implementar create, store, edit, update no SchoolClassController.
- [ ] Validar campos, ano, nome duplicado, escopo da escola e versão xmin.
- [ ] Usar transação e eventos Eloquent para antes/depois, converter violação 23505 em erro português.
- [ ] Proteger rotas novas com RequireConfirmedTwoFactor.
- [ ] Verificar testes de criação/edição, autorização e 2FA no CI.

## Tarefa 3: interface e retorno
- [ ] Criar resources/views/classes/form.blade.php com mensagens e campos completos.
- [ ] Adicionar Nova turma, Editar, status e permissões a classes/index.blade.php.
- [ ] Mapear novas rotas para school.classes.index no layout municipal.
- [ ] Conferir lista existente e layout no CI.

## Tarefa 4: entrega
- [ ] Atualizar HISTORICO.md uma vez.
- [ ] Criar um commit com implementação, testes, migração, formato e plano.
- [ ] Abrir PR na branch derivada de #24.
- [ ] Esperar Pint, testes e integração MariaDB para PostgreSQL verdes.
- [ ] Informar número do PR e hash, sem mesclar.
