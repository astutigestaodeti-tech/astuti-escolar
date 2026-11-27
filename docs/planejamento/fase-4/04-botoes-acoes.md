# Fase 4 / 04 - Padronização de botões e ações administrativas

Status: planejamento em rascunho. Execução após conclusão e homologação das Fases 2 (#256–267) e 3 (#268–277). Refs #209, #194, PR #243. Dependência dos padrões de navegação e cadastros.

## Escopo

Inventariar todas as páginas e ações, localizar botões redundantes, rótulos ambíguos, ações em posições inconsistentes e confirmações excessivas ou ausentes. Reutilizar componentes existentes e distinguir consulta, edição, impressão, cancelamento e operações críticas.

## Critérios de aceite

Mapa tela/ação/perfil; remover redundâncias comprovadas sem retirar funcionalidade ou proteção. Testar ações críticas com confirmação, justificativa e 2FA quando exigidos; verificar acessibilidade, margens e consistência visual.

## Condições comuns

Inventariar main e entregas anteriores antes de programar. Usar i-Educar 2.12 e relatos do fórum como referência funcional, distinguir relato histórico de problema reproduzido, registrar fonte/data/versão e implementar soluções próprias sem incorporar código GPL. Não duplicar entregas das Fases 2 e 3. Preservar LGPD, escopo por escola/ano/perfil, auditoria, 2FA, acessibilidade, dados fictícios e margens A4 (esquerda 30 mm, demais 20 mm). Testar regressões, telas 1440/390 quando aplicável, CI completo, revisão do SHA e homologação. Manter draft sem auto-merge e sincronizar somente ao iniciar. Dividir em PRs menores quando necessário. Nenhuma implementação funcional concluída neste rascunho.
