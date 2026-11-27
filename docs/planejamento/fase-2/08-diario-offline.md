# Fase 2 / 08 - Diário de classe offline (PWA)

Status: planejamento em rascunho; nenhuma implementação ou auditoria concluída por este documento. Prioridade aprovada em 26/11/2026. Refs #209, PR #186 (desenho já integrado; conferir implementação). Dependência: F2-07.

## Escopo da auditoria e implementação

Reaproveitar desenho #186: habilitação de aparelho online com 2FA, dados mínimos, autorização offline padrão 72h e máximo 7 dias, IndexedDB cifrado com chave derivada do PIN, service worker sem cache autenticado, UUID idempotente, tratamento de conflitos e revogação. Dividir em unidades: aparelho/chamada online, chamada offline, conteúdo, painel de aparelhos.

## Critérios de aceite

Testar instalação, perda de conexão, reinício, reenvio, conflito sem sobrescrita, validade/revogação, acesso cruzado, ausência de dados sensíveis no cache e sincronização auditada. Relatório de impacto e política de aparelho antes de dados reais. Não prometer revogação instantânea de aparelho desconectado.

## Condições comuns

Inventariar main e PRs antes de programar; registrar caminhos e evidências existentes, evitar duplicidade. Preservar LGPD, isolamento por escola/ano/perfil, auditoria, 2FA em ações críticas, acessibilidade e padrão A4. Usar apenas dados fictícios de Monte Sereno. Testes de regressão, CI completo, revisão do SHA e homologação antes de promover o PR de rascunho. Se houver migration, dependência ou mudança de infraestrutura, exigir procedimento seguro específico; não alterar bancos reais. Não habilitar auto-merge enquanto for rascunho. Sincronizar com main apenas quando começar a unidade, respeitar dependências funcionais e dividir implementação em PRs menores se necessário.
