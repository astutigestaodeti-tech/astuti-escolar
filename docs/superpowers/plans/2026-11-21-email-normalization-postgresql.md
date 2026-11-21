# Implementação: e-mails sem distinção de maiúsculas

Data: 21/11/2026. Bloco 1 do PR #5.

## Etapas e verificações

1. Registrar testes de comportamento real: login com e-mail de caixa mista e unicidade do banco mesmo em gravações diretas; teste da migration em tabelas temporárias PostgreSQL para conversão e duplicidade sem tocar `users` real; teste de transferência MariaDB de e-mail misto para PostgreSQL preservando hash e fonte.
2. Implementar `EmailNormalizer` usado pelo modelo `User`, login e rotina de transferência.
3. Criar migration PostgreSQL central transacional: diagnóstico sem PII, atualização de e-mails e índice único funcional. Falhar sem mutação diante de duplicatas.
4. Retirar gatilho `push` do workflow, mantendo `pull_request`. Criar `AGENTS.md` com o fluxo aprovado.
5. Commit atômico da unidade lógica, com testes e código juntos; validar pelo workflow do PR #5, com Pint, build, verificações UI, duas rodadas Laravel e transferência MariaDB/PostgreSQL. Sem merge.
6. Registrar uma única entrada de histórico no fechamento da sessão. Encaminhar teste seguro no cluster isolado `55436` somente depois da aprovação; não executar a migration na porta `5432` automaticamente.

## Restrições de verificação

O ambiente executor conectado ao GitHub não tem PHP/Composer nem checkout completo do repositório. Não há execução local de RED/Pint neste ambiente. O workflow do PR é a verificação executável disponível antes de orientar o notebook; relatar resultados reais, sem afirmar TDD local.
