# Aba Agente: contrato inicial de leitura (#244)

Primeiro bloco: parser PHP, testes unitários e componente visual de leitura. Não há rota, menu, polling ou concessão de acesso nesta etapa. O componente não deve ser publicado por uma rota até a implementação da autorização local explícita e do segundo fator sem bypass de desenvolvimento, com auditoria e no-store. Nenhuma alteração no banco.

## Snapshot local

O futuro publicador restrito lê o histórico events.json do agente e exporta somente os campos aprovados para /var/lib/astuti-agent-view/snapshot.json. O diretório de origem permanece privado. O arquivo de destino não fica em public, não pode ser escrito pelo PHP-FPM e deve ser publicado por troca atômica de arquivo regular, em diretório com acesso de escrita exclusivo do publicador. Definir permissões mínimas do diretório e grupo de leitura web na instalação. Não dar acesso web ao estado do agente, logs brutos, .git ou tokens.

Formato JSON schema 1: collected_at (epoch UTC inteiro), installed, target, blocked (SHA completo ou null), events (lista de até 1000 registros reais, na ordem original). Cada evento: etapa, epoch numérico UTC, anterior, alvo e ci (inteiro positivo ou null). O parser reconstrói os campos permitidos, ignora eventos desconhecidos ou fora de 90 dias, ordena por horário decrescente e posição original em empates, formata horário em America/Sao_Paulo e não confia no texto horário do agente. O snapshot tem limite de 512 KiB. Ausência, schema inválido, futuro ou leitura impossível significam indisponível. Com a coleta a cada 120 segundos em homologação, mais de 150 segundos desde a coleta significa desatualizado; não declarar saúde online com base nesses dados.

O publicador deve registrar o horário da coleta efetiva, sem simular nova implantação. A detecção de versões no GitHub continua na cadência do agente. O PHP não consulta GitHub nem executa comandos. Os links de SHA e CI são construídos de campos estritamente validados.

## Controle de acesso em preparação

O método TwoFactorSession::isVerifiedWithoutBypass exige conta ativa, segredo não vazio e verificação real vinculada à conta e ao segredo na sessão. Não consulta DevelopmentAdminAccess. O método sozinho não autoriza acesso ao console: ainda é obrigatório validar UUID local explícito, auditoria, throttle e no-store em cada endpoint. Nenhuma rota é exposta por esta etapa.

## Próxima entrega

Autorizar UUID local, conta ativa e confirmação real de segundo fator, sem a exceção DevelopmentAdminAccess; aplicar autorização a HTML e JSON, auditoria proporcional, throttle e no-store. Depois conectar menu/rotas e atualização automática, filtros/paginação, publicador local, teste de acesso negado, responsividade, rollback e homologação real. Sem acesso oculto em produção; autorização contratual do controlador. Não integrar endpoints antes das proteções e do caminho de implantação validado.
