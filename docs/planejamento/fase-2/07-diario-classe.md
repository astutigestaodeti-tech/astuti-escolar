# Fase 2 / 07 - Diário de classe

Status: planejamento em rascunho; nenhuma implementação ou auditoria concluída por este documento. Prioridade aprovada em 26/11/2026. Refs #209 e desenho aprovado PR #186. Dependência: F2-03 a F2-06.

## Escopo da auditoria e implementação

Inventariar diário online, atribuição de professor, turma/componente, calendário, frequência, conteúdo de aula e avaliações. Conferir visão diária/período, pendências, substituições, edição e fechamento. Preparar contrato funcional para offline sem criar dependência circular.

## Critérios de aceite

Testar lançamento e consulta por professor autorizado, substituição e revogação, calendário, edição auditada, consistência da frequência e notas, relatório A4 e experiência móvel. Preservar trilha do registro e impedir operações fora da turma.

## Condições comuns

Inventariar main e PRs antes de programar; registrar caminhos e evidências existentes, evitar duplicidade. Preservar LGPD, isolamento por escola/ano/perfil, auditoria, 2FA em ações críticas, acessibilidade e padrão A4. Usar apenas dados fictícios de Monte Sereno. Testes de regressão, CI completo, revisão do SHA e homologação antes de promover o PR de rascunho. Se houver migration, dependência ou mudança de infraestrutura, exigir procedimento seguro específico; não alterar bancos reais. Não habilitar auto-merge enquanto for rascunho. Sincronizar com main apenas quando começar a unidade, respeitar dependências funcionais e dividir implementação em PRs menores se necessário.
