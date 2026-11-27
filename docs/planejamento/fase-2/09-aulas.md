# Fase 2 / 09 - Aulas previstas e ministradas

Status: planejamento em rascunho; nenhuma implementação ou auditoria concluída por este documento. Prioridade aprovada em 26/11/2026. Refs #209, #219, PR #186. Dependência: F2-03 e F2-07.

## Escopo da auditoria e implementação

Inventariar calendário, horários, componentes, carga horária prevista, aulas efetivamente ministradas, reposições, cancelamentos e registros do diário. Separar quantidade de aulas de horas de duração, respeitar regras municipais, períodos e mudanças de turma/professor.

## Critérios de aceite

Testar feriado, aula cancelada e reposta, duração variável, alteração de horário, substituição e contagem por período/componente. Reconciliar totais de planejamento e diário sem dupla contagem. Exibir origem e pendências dos dados.

## Condições comuns

Inventariar main e PRs antes de programar; registrar caminhos e evidências existentes, evitar duplicidade. Preservar LGPD, isolamento por escola/ano/perfil, auditoria, 2FA em ações críticas, acessibilidade e padrão A4. Usar apenas dados fictícios de Monte Sereno. Testes de regressão, CI completo, revisão do SHA e homologação antes de promover o PR de rascunho. Se houver migration, dependência ou mudança de infraestrutura, exigir procedimento seguro específico; não alterar bancos reais. Não habilitar auto-merge enquanto for rascunho. Sincronizar com main apenas quando começar a unidade, respeitar dependências funcionais e dividir implementação em PRs menores se necessário.
