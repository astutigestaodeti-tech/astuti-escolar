# Plano de implementação: bloco 2

1. Testes de regressão: provar `datlocprovider=i` e `datlocale=pt-BR` em platform/tenant; provar ordem de Álvaro, Ana, Bruno, Éder, Zélia e busca `joao` retornando João apenas na escola autorizada.
2. Migration municipal: `unaccent`, `pg_trgm`, `f_unaccent` imutável e índice GIN. Busca parametrizada com normalização e escape de curingas.
3. Ambiente CI: criar explicitamente os três bancos da suíte e os destinos de integração com ICU pt-BR, validando a configuração. Restaurar `vendor/bin/pint --test` sem depuração.
4. Guias e shells: criar novos bancos do notebook e de ensaio com ICU pt-BR e registrar impossibilidade de corrigir bancos existentes in-place, backups e `REINDEX` condicionado.
5. Rodar shell, testes PHP duas vezes, Pint, build e interface no PR; corrigir antes de entregar. Fazer um commit lógico e uma atualização consolidada do histórico. Não iniciar bloco 3 sem autorização.
