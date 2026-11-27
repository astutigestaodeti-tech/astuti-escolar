# Fase 2 / 03 - Separação por ano letivo

Status: planejamento em rascunho; nenhuma implementação ou auditoria concluída por este documento. Prioridade aprovada em 26/11/2026. Refs #209, #219, #221. Dependência transversal para F2-02 e F2-04 a F2-12.

## Escopo da auditoria e implementação

Mapear seleção do ano vigente e planejamento futuro, escopos de consultas, turmas, matrículas, frequência, avaliações, fechamento e relatórios. Distinguir dados permanentes do aluno de registros anuais. Revisar controles de fechamento/reabertura e transição entre anos sem apagar histórico.

## Critérios de aceite

Simular dois anos letivos fictícios, com dados diferentes para o mesmo aluno. Provar que consultas, totais, exportações e alterações respeitam ano e escola, que planejamento futuro não vira vigente por engano e que ano fechado não é editado sem autorização. Reutilizar #219 e #221, sem duplicar correções.

## Condições comuns

Inventariar main e PRs antes de programar; registrar caminhos e evidências existentes, evitar duplicidade. Preservar LGPD, isolamento por escola/ano/perfil, auditoria, 2FA em ações críticas, acessibilidade e padrão A4. Usar apenas dados fictícios de Monte Sereno. Testes de regressão, CI completo, revisão do SHA e homologação antes de promover o PR de rascunho. Se houver migration, dependência ou mudança de infraestrutura, exigir procedimento seguro específico; não alterar bancos reais. Não habilitar auto-merge enquanto for rascunho. Sincronizar com main apenas quando começar a unidade, respeitar dependências funcionais e dividir implementação em PRs menores se necessário.
