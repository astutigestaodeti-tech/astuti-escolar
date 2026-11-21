# Segundo fator obrigatório para consultas protegidas (design)

## Objetivo
Proteger dados escolares de contas com segundo fator TOTP já ativado: senha sem código não deve permitir acesso a municípios, escolas, alunos e turmas, nem revelar nomes de municípios na página inicial.

## Estado atual
O Astuti já implementa TOTP, recuperação, auditoria de login e middleware `RequireConfirmedTwoFactor` para futuras **escritas**. O login redireciona contas habilitadas ao desafio, mas as rotas de consulta ainda não impõem a confirmação. Um usuário que conhece a URL pode acessar a área de consulta com a senha apenas.

## Abordagens consideradas
1. **Middleware leve dedicado às consultas (escolhida):** condicional ao 2FA estar habilitado; bloqueia consulta e página inicial até confirmação TOTP da sessão, reutilizando `TwoFactorSession` e `TwoFactorController`. Sem novas tabelas nem alteração de contas existentes.
2. Reutilizar `RequireConfirmedTwoFactor` de escritas: inadequado, pois retorna 403 e bloquearia inclusive contas sem TOTP habilitado, alterando o modelo atual de leitura.
3. Criar nova camada de autenticação: duplicaria TOTP/auditoria existentes e exigiria migração desnecessária.

## Comportamento
- Conta com TOTP habilitado, antes do desafio: acesso direto a `/` ou qualquer `/m/{municipio}` é redirecionado ao desafio; nenhum dado municipal é consultado.
- Após confirmar código TOTP ou recuperação: acesso original é restaurado quando possível; consultas funcionam, respeitando autorização da escola.
- Confirmação expira em 15 minutos e exige novo desafio.
- Contas sem TOTP habilitado conservam o comportamento atual de consulta; visitantes conservam `/` pública e redirecionamento ao login em rotas municipais.
- Página de configuração de 2FA, verificação e logout permanecem acessíveis para evitar bloqueio circular.

## Limites e segurança
Não obriga ainda o cadastro inicial do segundo fator, não cria nem altera registros escolares, não modifica banco principal PostgreSQL 5432, MariaDB, backups ou `.env`. Testes no CI com bases PostgreSQL `*_test`, PR #5 permanece draft.

## Evidência
Testes de recurso cobrem acesso por URL direta, visitante, conta sem TOTP, validação e expiração de sessão, e preservação da rota originalmente solicitada.
