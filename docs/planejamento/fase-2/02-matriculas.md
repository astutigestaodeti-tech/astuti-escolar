# Fase 2 / 02 - Matrículas, rematrículas e transferências

Status: planejamento em rascunho; nenhuma implementação ou auditoria concluída por este documento. Prioridade aprovada em 26/11/2026. Refs #209, #219, #218. Dependência funcional: F2-01 e F2-03.

## Escopo da auditoria e implementação

Inventariar fluxos de ingresso, rematrícula, transferência interna/externa, encerramento, reativação e histórico; regras de vaga, turma, etapas, datas e ano letivo. Conferir transações, lista de espera e prevenção de matrículas simultâneas incompatíveis; manter situações legítimas previstas nas regras educacionais.

## Critérios de aceite

Testar ciclo de matrícula com aluno existente, turma lotada, transferências com histórico preservado, concorrência, repetição da rematrícula e acesso por escola/ano. Emitir documentos consistentes, com auditoria e motivo quando exigidos. Não criar nova regra de negócio sem validar norma ou configuração municipal.

## Condições comuns

Inventariar main e PRs antes de programar; registrar caminhos e evidências existentes, evitar duplicidade. Preservar LGPD, isolamento por escola/ano/perfil, auditoria, 2FA em ações críticas, acessibilidade e padrão A4. Usar apenas dados fictícios de Monte Sereno. Testes de regressão, CI completo, revisão do SHA e homologação antes de promover o PR de rascunho. Se houver migration, dependência ou mudança de infraestrutura, exigir procedimento seguro específico; não alterar bancos reais. Não habilitar auto-merge enquanto for rascunho. Sincronizar com main apenas quando começar a unidade, respeitar dependências funcionais e dividir implementação em PRs menores se necessário.
