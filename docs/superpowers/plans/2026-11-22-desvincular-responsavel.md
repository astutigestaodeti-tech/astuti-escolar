# Plano de implementação e verificação (22/11/2026)

1. Conferir `origin/main` após #36, #38 e #39 e o layout de cartões aprovado no commit 78fde90.
2. Criar testes para cartão e confirmação; exclusão somente do vínculo e preservação de irmãos; auditoria sem CPF/telefone; motivo inválido; professor e outra escola; segundo fator recente e restauração do motivo; ausência de contato principal sem promoção automática.
3. Implementar GET e POST no `GuardianController`, rotas com segundo fator recente, view de confirmação, aviso no parcial de cartões e retorno de navegação.
4. Acrescentar `motivo` à lista permitida de auditoria do `StudentGuardian`, sem incluir informações de contato.
5. Verificar Pint e suíte no CI, corrigir qualquer falha no mesmo PR e deixar aberto para revisão, sem mesclar.

Sem migração. Nenhuma operação no banco original do notebook e nenhuma alteração nos componentes de importação das planilhas.
