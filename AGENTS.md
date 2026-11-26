# Regras do projeto Astuti Gestão Escolar

## Contexto e segurança

- Jefferson Astuti conduz o projeto comercial. Responda em português brasileiro, com linguagem natural, direta e sem travessão longo.
- Confira o registro existente em `HISTORICO.md` (ou `docs/continuidade/HISTORICO.md`, quando presente), o ponto de retomada disponível, branches, PRs, commits e CI antes de alterar código.
- Produção: uma VPS, uma aplicação Laravel e dois bancos PostgreSQL locais privados (`platform` e `tenant`) exclusivos por Prefeitura. A instalação fica travada no município configurado. Não há VPS de produção compartilhada.
- Preserve isolamento entre escolas e perfis, autenticação reforçada, auditoria obrigatória, LGPD e dados de menores. Use somente dados fictícios em testes.
- Não altere dados, PostgreSQL original do notebook na porta 5432, MariaDB, backups ou `.env` sem aprovação específica. Não execute `DROP DATABASE`, `migrate:fresh`, `db:wipe` ou limpeza destrutiva em bases existentes.
- Nunca publique segredos, senhas, dumps, documentos de estudantes ou dados reais no Git. Não faça deploy automaticamente. Merge só com CI verde e revisão (ver coordenação).
- LGPD desde o desenho é obrigatória em toda tabela, tela, importação e API: siga `docs/arquitetura/lgpd-by-design.md` e diga no PR como a regra foi aplicada.

## Coordenação de desenvolvimento

- Por decisão de Jefferson em 22/11/2026, o Claude é o programador-chefe: distribui as tarefas, revisa e mescla os PRs com CI verde. O ChatGPT executa os blocos atribuídos. Instruções ao ChatGPT podem chegar pelo chat ou por issue no GitHub com o rótulo `chatgpt`; uma tarefa por vez.
- Antes de diagnosticar problema relatado por Jefferson, confira no rodapé da tela a versão e o banco em uso.
- Antes de programar, faça um inventário dos PRs abertos e de suas bases; não altere branches de outras frentes. Use um PR pequeno por objetivo, com CI, sem merge ou deploy automáticos.

## Padrão visual dos cartões

- Todo conteúdo dentro de um cartão (`.folha`), fora o título (`.folha-cabecalho`) e tabelas de borda a borda, fica dentro de `.folha-corpo` ou de um bloco que use `var(--recuo-folha)` (22 px; 16 px no celular). Nada de campo, botão, texto ou caixa encostado na borda.
- Antes de abrir PR com tela nova ou alterada, inclua a tela em `tests/Feature/Layout/AllSchoolPagesOpenTest.php` e rode `ASTUTI_DUMP_TELAS=/tmp/telas php artisan test --filter=AllSchoolPagesOpenTest` seguido de `python3 scripts/qualidade/checar-margens.py /tmp/telas 1440 390`. O resultado precisa ser "Problemas de margem: 0" (a conferência também acusa campo e botão empilhados a menos de 6 px). Busca de lista usa o padrão `.busca` (campo com lupa e botão "Pesquisar" ao lado), como em Alunos e Servidores.

## Padrão institucional de impressão (25/11/2026)

- Todos os documentos impressos ou exportados em PDF usam papel A4, margem esquerda de 30 mm e margens superior, direita e inferior de 20 mm. Em CSS: `margin:20mm 20mm 20mm 30mm` (topo, direita, base, esquerda). A orientação retrato ou paisagem depende do documento, sem alterar as margens físicas. Esta regra substitui qualquer padrão de margens de impressão anterior. Não confundir com os recuos dos cartões da interface. Conferir regras `@page` globais e nomeadas, estilos específicos, PDFs e prévias de impressão; evitar margens duplicadas e cortes de conteúdo.

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
