# Regras do projeto Astuti Gestão Escolar

## Contexto e segurança

- Jefferson Astuti conduz o projeto comercial. Responda em português brasileiro, com linguagem natural, direta e sem travessão longo.
- Leia o ponto de retomada em `docs/continuidade/README.md`, o registro recente em `docs/continuidade/HISTORICO.md` e confira branch, PR, commits e CI antes de alterar código.
- Produção: uma VPS, uma aplicação Laravel e dois bancos PostgreSQL locais privados (`platform` e `tenant`) exclusivos por Prefeitura. A instalação fica travada no município configurado. Não há VPS de produção compartilhada.
- Preserve isolamento entre escolas e perfis, autenticação reforçada, auditoria obrigatória, LGPD e dados de menores. Use somente dados fictícios em testes.
- Não altere dados, PostgreSQL original do notebook na porta 5432, MariaDB, backups ou `.env` sem aprovação específica. Não execute `DROP DATABASE`, `migrate:fresh`, `db:wipe` ou limpeza destrutiva em bases existentes.
- Nunca publique segredos, senhas, dumps, documentos de estudantes ou dados reais no Git. Não faça merge nem deploy automaticamente. O usuário mescla os PRs.

## Modo de trabalho aprovado em 21/11/2026

1. **Processo proporcional ao risco.** Correções pequenas de consulta, filtro, visual e texto dispensam novas especificações e planos em `docs/superpowers`. Entregue código e teste juntos em uma unidade lógica. Use o ciclo completo de análise, desenho, testes e revisão para importadores, integração SED, auditoria, permissões, segundo fator, exclusão, restauração e migrações de banco.
2. **Commits por unidade lógica.** Teste, implementação e formatação do mesmo objetivo vão no mesmo commit. Não grave commits isolados de teste vermelho, estilo ou espaçamento. Execute Pint e testes antes de cada commit quando houver ambiente local; se isso não for possível, declare o limite e não afirme aprovação até o CI.
3. **Somente código aprovado.** CI em `pull_request`, não em `push` para branches com PR aberto. Não anuncie conclusão com testes pendentes ou falhando. Corrija falhas antes de apresentar o bloco como pronto.
4. **Histórico uma vez por sessão.** Faça uma entrada consolidada em `docs/continuidade/HISTORICO.md` no encerramento da sessão, em um único commit. Não atualize `main` a cada pequena etapa.
5. **PR por objetivo.** Prefira PRs pequenos e revisáveis no mesmo dia. O PR #5 recebe apenas correções da migração PostgreSQL; telas e funções novas seguem em PRs próprios. Preserve a sequência de PRs existentes.
6. **Isolamento de Prefeitura.** Mantenha a estrutura municipal e a trava no município configurado, sem ampliar artificialmente proteções nem criar novos testes cruzados entre Prefeituras. Continuam obrigatórios os testes de isolamento entre escolas e perfis.
7. **Ordem funcional.** Após os ajustes críticos de migração: Incremento 03 com inclusão, edição, matrícula, transferência e exclusão justificada, tudo auditado e com segundo fator; em seguida importador até a pasta 02 do kit fictício Monte Sereno corresponder ao gabarito. Não priorize refinamentos de consultas.
8. **Entregas em blocos.** Conclua um bloco funcional, valide-o e envie um único resumo com o que Jefferson deve testar no notebook.

## Regras de implantação

- Homologação e produção exigem configuração e contas próprias, verificação de permissões e backup/restauração testados. Não confunda o cluster fictício `55436` com o PostgreSQL original `5432`.
- O PR #5 permanece rascunho até decisão expressa do usuário. A aprovação de CI não autoriza migração no Acer nem operação com dados reais.
