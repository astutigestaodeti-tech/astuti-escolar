# Plano de implementação da issue #2

**Origem:** `feature/incremento-01-saude`  
**Destino:** `feature/incremento-02-fundacoes`  
**Issue:** https://github.com/astutigestaodeti-tech/astuti-escolar/issues/2

## Tarefa 1: Auditoria

1. Criar testes de criação/atualização, isolamento A/B/A, imutabilidade, autenticação sem senha e provisionamento.
2. Criar migrações aditivas para `audit_logs` e `platform_audit_logs`, modelos imutáveis, `AuditTrail` e observação automática dos eventos nos modelos municipais.
3. Validar testes e registrar a restrição de UPDATE/DELETE nos bancos de produção.

## Tarefa 2: Histórico

1. Criar testes de exclusão lógica, atribuição, escopo padrão, restauração e trilha de exclusão/restauração.
2. Adicionar migração, `SoftDeletes` e `deleteWithReason`, bloqueando `forceDelete` no domínio municipal.
3. Documentar operação, atribuição, revisão e recuperação em `docs/arquitetura/historico-e-exclusao.md`.

## Tarefa 3: Português brasileiro

1. Criar testes da mensagem de validação do e-mail no login e das mensagens principais de autenticação, formulário e erros.
2. Adicionar traduções em `lang/pt_BR` e configurar faker brasileiro.

## Tarefa 4: Cabeçalhos de segurança

1. Criar testes para `/`, `/login`, `/demonstracao`, rota municipal autenticada, HSTS condicional e compatibilidade com assets locais.
2. Implementar middleware web e verificar opções seguras de cookie no exemplo de ambiente.

## Verificação e entrega

1. Executar testes focados, suíte PHP completa, Pint, verificações visuais e build. Quando o PHP local não estiver disponível, usar os jobs de CI do GitHub e registrar a limitação.
2. Inspecionar o diff, solicitar revisão interna e corrigir achados.
3. Publicar os commits em branch nova e abrir PR em rascunho com base `feature/incremento-01-saude`, incluindo a matriz dos critérios de aceite e os resultados dos testes.
