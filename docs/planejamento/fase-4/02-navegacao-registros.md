# Fase 4 / 02 - Navegação contextual entre registros

Status: planejamento em rascunho. Execução após conclusão e homologação das Fases 2 (#256–267) e 3 (#268–277). Refs #194, #208, #209. Complementa F2-01.

## Escopo

Revisar navegação primeiro/anterior/próximo/último, retorno preservando filtros e página, localização de aluno por nome/RA e padrões reutilizáveis em turmas, servidores e escolas. Preservar ordenação e escopos, sem enviar listas de IDs ao navegador.

## Critérios de aceite

Testar limites, homônimos, paginação, filtros, contexto ausente, autorização e proteção contra perda de edição. Comparar número de interações nas tarefas frequentes; validar a primeira tela antes de replicar.

## Condições comuns

Inventariar main e entregas anteriores antes de programar. Usar i-Educar 2.12 e relatos do fórum como referência funcional, distinguir relato histórico de problema reproduzido, registrar fonte/data/versão e implementar soluções próprias sem incorporar código GPL. Não duplicar entregas das Fases 2 e 3. Preservar LGPD, escopo por escola/ano/perfil, auditoria, 2FA, acessibilidade, dados fictícios e margens A4 (esquerda 30 mm, demais 20 mm). Testar regressões, telas 1440/390 quando aplicável, CI completo, revisão do SHA e homologação. Manter draft sem auto-merge e sincronizar somente ao iniciar. Dividir em PRs menores quando necessário. Nenhuma implementação funcional concluída neste rascunho.
