# Fase 2 / 05 - Notas e boletins

Status: planejamento em rascunho; nenhuma implementação ou auditoria concluída por este documento. Prioridade aprovada em 26/11/2026. Refs #209, #219. Dependência: F2-02 a F2-04.

## Escopo da auditoria e implementação

Inventariar avaliações por período/componente, notas, conceitos, pareceres, recuperação, médias, arredondamento e boletins. Conferir regras configuráveis por ano/etapa, publicação e correções após fechamento, sem presumir regra municipal única.

## Critérios de aceite

Testar avaliações numéricas e conceituais, nota zero versus ausência de lançamento, recuperação, arredondamento, transferência, boletim A4, proteção de período fechado e acesso de professor à turma autorizada. Reconciliar resultados com dados de origem.

## Condições comuns

Inventariar main e PRs antes de programar; registrar caminhos e evidências existentes, evitar duplicidade. Preservar LGPD, isolamento por escola/ano/perfil, auditoria, 2FA em ações críticas, acessibilidade e padrão A4. Usar apenas dados fictícios de Monte Sereno. Testes de regressão, CI completo, revisão do SHA e homologação antes de promover o PR de rascunho. Se houver migration, dependência ou mudança de infraestrutura, exigir procedimento seguro específico; não alterar bancos reais. Não habilitar auto-merge enquanto for rascunho. Sincronizar com main apenas quando começar a unidade, respeitar dependências funcionais e dividir implementação em PRs menores se necessário.
