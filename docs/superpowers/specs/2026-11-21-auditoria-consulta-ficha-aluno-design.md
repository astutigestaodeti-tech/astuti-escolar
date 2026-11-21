# Auditoria da consulta da ficha individual do aluno

**Data:** 21/11/2026

## Intenção
Registrar a autoria de cada consulta autorizada à ficha individual de aluno para rastreabilidade de acesso, sem salvar conteúdo pessoal no evento e sem habilitar alterações em cadastros.

## Estado atual
A ficha GET `/m/{municipio}/escolas/{escola}/alunos/{aluno}` só retorna um aluno com vínculo ativo na escola autorizada; `AuditTrail::record()` já persiste eventos imutáveis no banco municipal dentro de `TenantContext`, identificando conta, função, momento, request e entidade.

## Abordagens consideradas
1. **Evento de auditoria no controller após a autorização e leitura elegível (adotada).** Tem o ID exato do aluno e da unidade no contexto, não registra tentativas 403/404 como acessos legítimos; alteração mínima.
2. Middleware dedicado à ficha. Centralizaria a lógica, mas precisaria capturar entidades após o controller ou consultar alunos duas vezes, aumentando riscos de escopo.
3. Evento de interface/analytics. Depende do navegador e poderia omitir acessos diretos; não atende rastreabilidade.

## Comportamento definido
- Cada ficha acessada com sucesso grava um evento `student.viewed` no `audit_logs` da **mesma municipalidade**, com `auditable_type=App\\Models\\Tenant\\Student` e `auditable_id` igual ao UUID do aluno.
- A autoria vem da sessão autenticada pela trilha existente; o evento não contém nome, data de nascimento, matrícula, dados médicos nem snapshots `before`/`after`.
- 403, 404, visitante e conta TOTP ainda não confirmada não gravam `student.viewed`.
- Se a auditoria falhar, a resposta não deve confirmar a consulta como sucesso silencioso. Não adicionar captura de exceção para ignorá-la.
- A ficha permanece somente leitura para o usuário; a gravação é exclusivamente em trilha de auditoria append-only existente.

## Limites
Somente branch `feature/migracao-postgresql-instalacoes-isoladas`; PR #5 continua draft. Sem migrations, novas dependências, mudança de .env, acesso a bases originais ou teste manual com dados reais. Validar em CI com PostgreSQL descartável.

## Prova
Teste RED para consulta autorizada sem evento; GREEN com evento e ausência de snapshots, testes negativos de não gravação por acesso indevido e integrações existentes aprovadas.
