# Instrucoes para agentes - Astuti Gestao Escolar

Este repositorio pertence ao projeto comercial Astuti Gestao Escolar, da Astuti Gestao de TI, conduzido por Jefferson Astuti.

## Retomada obrigatoria

1. Antes de planejar, diagnosticar ou alterar o projeto, leia integralmente `docs/continuidade/README.md` e a entrada mais recente de `docs/continuidade/HISTORICO.md`.
2. Confira a branch realmente em uso, os pull requests, os commits recentes, as alteracoes locais nao commitadas e a situacao dos testes. A branch `main` pode estar atras das branches de desenvolvimento.
3. Use as especificacoes e os planos do repositorio como fonte tecnica, mas diferencie o que foi apenas planejado, o que existe no codigo e o que foi comprovado no notebook do usuario.
4. Se o estado atual contradizer o registro anterior, apresente a divergencia, confirme com evidencias e atualize o historico. Nunca declare uma etapa concluida com base apenas em um plano ou em sucesso de CI.
5. Ao final de cada marco de desenvolvimento ou sessao relevante, atualize o ponto de retomada e acrescente uma entrada datada em `HISTORICO.md`: pedido, decisoes, arquivos/commits/PRs, testes com resultado, estado do banco, pendencias e proxima acao segura.

## Limites permanentes

- Escreva para Jefferson em portugues brasileiro, com clareza, naturalidade e sem travessao longo.
- Preserve dados e configuracoes existentes. Nunca execute `migrate:fresh`, `db:wipe`, `DROP DATABASE`, limpeza de arquivos ou comandos equivalentes sem identificar o alvo e obter autorizacao especifica.
- O MariaDB local e a origem preservada da migracao. Os bancos PostgreSQL de desenvolvimento e de teste nao devem ser confundidos.
- Nao grave no Git segredos, `.env`, tokens, senhas, dumps, dados escolares reais, documentos de criancas, prints com credenciais ou conversas pessoais alheias ao projeto.
- Uma instalacao de producao por Prefeitura contratante, em VPS exclusivo quando contratado. No estado registrado em 21/11/2026 nao havia VPS contratado.
- O ambiente atual e o notebook Windows 11 Pro com Debian/WSL2. Nao presumir que uma ferramenta executada pelo agente tenha acesso direto a esse notebook.
- Respeite a cadeia de PRs ainda nao integrados e nao faca merges ou deployments automaticamente.
- Priorize testes, isolamento, auditoria, LGPD, revisao humana das importacoes e uso de dados ficticios em desenvolvimento.

## Limite deste registro

O historico e um resumo tecnico verificavel, nao uma copia automatica e integral dos chats. Atualize-o de forma deliberada apos cada sessao relevante. Nao prometa sincronizacao continua de mensagens sem existir uma integracao especifica.
