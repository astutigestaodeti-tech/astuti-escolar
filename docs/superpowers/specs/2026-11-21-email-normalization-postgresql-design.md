# Correção de e-mails no PostgreSQL

Data: 21/11/2026. PR #5. Bloco funcional 1 da revisão externa.

## Problema e objetivo

O PostgreSQL compara o campo `users.email` com distinção de maiúsculas. Uma conta cadastrada como `Maria.Silva@...` não autentica com `maria.silva@...`; valores com caixa diferente contornam a unicidade anterior. O ensaio de cópia de Monte Sereno precisa receber a correção antes da operação no notebook.

## Solução escolhida

1. Normalizador único para gravação de `User`, autenticação e transferência fictícia do MariaDB; aplica `trim` e minúsculas. Nenhuma senha ou hash é modificado.
2. Nova migration no banco central PostgreSQL `platform`: detectar antecipadamente grupos duplicados após `lower(btrim(email))`; interromper com quantidade e mensagem sem divulgar e-mails; converter e-mails existentes e criar índice único `lower(email)` dentro da mesma transação. Falha não altera linhas.
3. A transferência continua preservando a origem MariaDB, os IDs, senhas e demais campos; apenas o e-mail copiado passa pela normalização exigida no destino.
4. Testes de login com caixa mista, cadastro duplicado via SQL sem Eloquent, normalização retroativa, interrupção com duplicatas e cópia da origem MariaDB com e-mail misto.

## Limites

Não há cadastro público. Não criar telas, modificar esquema municipal, executar migrations no notebook ou usar dados reais. Uma VPS por Prefeitura e dois bancos privados permanecem. O rollback remove apenas o índice, porque a caixa original dos e-mails não pode ser reconstituída automaticamente. A revisão e execução de migração no notebook dependem de backup e autorização separados.

## Alternativas descartadas

- Usar apenas `ILIKE` no login: não impede duplicidades por SQL ou outros caminhos.
- Usar apenas `citext`: introduz extensão e mudança de tipo sem necessidade para este defeito. O índice funcional atende à exigência explícita.
