# Busca inteligente de CEP - plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox syntax for tracking.

**Goal:** Preencher endereço pelo CEP no cadastro manual com cache municipal e complementação humana.

**Architecture:** Endpoint autenticado e autorizado, serviço ViaCEP server-side,
cache tenant e JavaScript módulo sem dependência externa. Migration aditiva
e auditoria aproveitam o modelo Student existente.

**Tech Stack:** Laravel PHP 8.4, PostgreSQL 17, Blade, JavaScript ESM, Node:test,
Pint e testes PHP.

**Spec:** docs/superpowers/specs/2026-11-21-cep-inteligente-alunos-design.md

## Global Constraints
- Base PR #25, sem reescrever esse branch nem outras frentes (#26, #27).
- Nenhum código de importação, OCR, dois fatores ou banco de produção alterado.
- Testes, implementação, documentação e formato no mesmo commit.
- CI em pull_request; PHP local indisponível, portanto validação PHP no CI.

## Review Focus
- O serviço recebe apenas o CEP e nenhum dado pessoal da criança.
- CEP inválido, desconhecido ou consulta fora do ar não impedem cadastro manual.
- Mudança no campo após início da busca não é sobrescrita pela resposta tardia.
- Campos Número, Complemento e Zona nunca são sugeridos por ViaCEP.
- Dados existentes não enviados pelo PUT permanecem preservados.
- Teacher/escopo indevido/2FA ausente não consultam o serviço.
- As bases descartáveis continuam limpas entre testes; a migração MariaDB
  para PostgreSQL reconhece as novas colunas em students.

## Tarefa 1: testes de contrato
- [ ] Criar testes de HTTP fake no endpoint, cache, indisponibilidade, escopo.
- [ ] Ampliar StudentRecordWriteTest para campos novos, auditoria e ficha.
- [ ] Criar Node:test para máscara e prevenção de sobrescrita.
- [ ] Verificar ausência do endpoint atual; executar teste JS vermelho quando possível.

## Tarefa 2: persistência e validação
- [ ] Criar migração tenant anulável e tabela postal_code_cache.
- [ ] Atualizar Student fillable, AuditTrail e controller manual.
- [ ] Ajustar expectativa de colunas no teste da migração.
- [ ] Adicionar nova tabela à limpeza segura de testes descartáveis.

## Tarefa 3: serviço e segurança
- [ ] Criar lista compartilhada de UFs.
- [ ] Criar PostalCodeLookupService com ViaCEP, TTL, fallback e sanitização.
- [ ] Criar controller JSON, route com manageStudents, 2FA e throttle.
- [ ] Testar consulta somente CEP e estados de falha.

## Tarefa 4: formulário e verificação
- [ ] CEP primeiro, botão Buscar, fields complement/city/UF e região manual.
- [ ] Mostrar novos campos na ficha; CSS mínimo e JS com aria-live e debounce.
- [ ] Atualizar histórico consolidado, criar PR com commit único.
- [ ] Corrigir Pint e testes até CI verde, sem merge.
