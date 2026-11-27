# Fase 2 / 04 - Frequência escolar

Status: planejamento em rascunho; nenhuma implementação ou auditoria concluída por este documento. Prioridade aprovada em 26/11/2026. Refs #209, #219. Dependência: F2-02 e F2-03.

## Escopo da auditoria e implementação

Inventariar chamada diária/por aula, justificativas, correções, faltas, alertas e relatórios, integração com Sistema Presença e busca ativa. Verificar alunos transferidos, calendário, dias não letivos, cálculo de frequência e regras por modalidade.

## Critérios de aceite

Testar presença/falta, aula não realizada, transferência no período, justificativa e correção auditada, denominadores e relatórios por período. Impedir lançamento fora do escopo e alteração indevida de período fechado; reconciliar totais com registros de origem.

## Condições comuns

Inventariar main e PRs antes de programar; registrar caminhos e evidências existentes, evitar duplicidade. Preservar LGPD, isolamento por escola/ano/perfil, auditoria, 2FA em ações críticas, acessibilidade e padrão A4. Usar apenas dados fictícios de Monte Sereno. Testes de regressão, CI completo, revisão do SHA e homologação antes de promover o PR de rascunho. Se houver migration, dependência ou mudança de infraestrutura, exigir procedimento seguro específico; não alterar bancos reais. Não habilitar auto-merge enquanto for rascunho. Sincronizar com main apenas quando começar a unidade, respeitar dependências funcionais e dividir implementação em PRs menores se necessário.
