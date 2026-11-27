# Fase 2 / 01 - Cadastros de alunos e responsáveis

Status: planejamento em rascunho; nenhuma implementação ou auditoria concluída por este documento. Prioridade aprovada em 26/11/2026. Refs #209, #198, #194, #206, #210, #218.

## Escopo da auditoria e implementação

Inventariar modelos, migrations, regras de validação, formulários, importadores, ficha e rotas; matriz campo a campo de necessidade, obrigatoriedade, origem, acesso e integração. Conferir RA/CPF (inclusive ausência legítima e zeros iniciais), endereço, fotos, vínculos, guarda, contatos principais, autorização de retirada e histórico de desvinculação. Identificar duplicidades sem unir registros automaticamente.

## Critérios de aceite

Testar cadastro/edição/consulta, busca de existentes, responsáveis múltiplos, acesso indevido entre escolas, auditoria de alteração, validação de documento, ficha A4 e telas 1440/390. Registrar cada lacuna com evidência; corrigir apenas a lacuna reproduzida. Não alterar base real nem exigir dados desnecessários.

## Condições comuns

Inventariar main e PRs antes de programar; registrar caminhos e evidências existentes, evitar duplicidade. Preservar LGPD, isolamento por escola/ano/perfil, auditoria, 2FA em ações críticas, acessibilidade e padrão A4. Usar apenas dados fictícios de Monte Sereno. Testes de regressão, CI completo, revisão do SHA e homologação antes de promover o PR de rascunho. Se houver migration, dependência ou mudança de infraestrutura, exigir procedimento seguro específico; não alterar bancos reais. Não habilitar auto-merge enquanto for rascunho. Sincronizar com main apenas quando começar a unidade, respeitar dependências funcionais e dividir implementação em PRs menores se necessário.
