# Fase 2 / 10 - Distorção idade-série

Status: planejamento em rascunho; nenhuma implementação ou auditoria concluída por este documento. Prioridade aprovada em 26/11/2026. Refs #209, #221. Dependência: F2-01 a F2-03.

## Escopo da auditoria e implementação

Inventariar painel e indicadores existentes. Definir método e data de referência com fonte oficial e versão aplicável, etapas elegíveis, idade esperada, regras de corte e tratamento de dados incompletos. Separar indicador interno de eventual cálculo oficial; não assumir fórmula sem validação.

## Critérios de aceite

Testar datas de nascimento limítrofes, etapas não elegíveis, transferências e dois anos letivos. Reconciliar denominadores, identificar casos sem dados e preservar privacidade de grupos pequenos. Não expor diagnóstico individual em painel público nem inventar valores.

## Condições comuns

Inventariar main e PRs antes de programar; registrar caminhos e evidências existentes, evitar duplicidade. Preservar LGPD, isolamento por escola/ano/perfil, auditoria, 2FA em ações críticas, acessibilidade e padrão A4. Usar apenas dados fictícios de Monte Sereno. Testes de regressão, CI completo, revisão do SHA e homologação antes de promover o PR de rascunho. Se houver migration, dependência ou mudança de infraestrutura, exigir procedimento seguro específico; não alterar bancos reais. Não habilitar auto-merge enquanto for rascunho. Sincronizar com main apenas quando começar a unidade, respeitar dependências funcionais e dividir implementação em PRs menores se necessário.
