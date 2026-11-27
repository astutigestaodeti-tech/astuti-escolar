# Astuti Watchdog - política de autorrecuperação (#249)

Regra autorizada pelo proprietário em 26/11/2026: 30 minutos sem progresso comprovado disparam diagnóstico. Não é prazo máximo de reparo. A automação ChatGPT roda a cada hora; a reação no limite exige futuro temporizador local. Não confundir este componente offline com agente ativo.

## Decisão determinística

A política recebe estado classificado, timestamp do último progresso real (não o horário da última consulta), timestamp da observação, relógio confiável e contador persistente de tentativas do mesmo incidente. Coleta com mais de 150 segundos, relógio incoerente e estado desconhecido não permitem correção. Segurança ou backup ausente exigem escalonamento imediato. CI ativo há mais de 30 minutos exige diagnóstico, nunca cancelamento por duração isolada. Falha transitória comprovada admite proposta de uma repetição do CI. Falha de código exige PR de correção. Implantação parada exige diagnóstico e verificação da política de recuperação local. Máximo duas tentativas corretivas por incidente; depois revisão de causa raiz, sem repetição infinita.

## Próxima unidade

Adaptadores de leitura confiáveis de CI e telemetria, identificação persistente do incidente (instalação, SHA, causa), registro de ações/resultados, testes de concorrência e reinício, e publicação de eventos sanitizados. Integrar o temporizador local somente após validação de isolamento e rollback. O código não tem acesso a rede, filesystem, comandos, serviço, assinatura, credenciais, configurações ou dados escolares. As decisões são propostas, não autorização de execução. Nenhuma ferramenta da plataforma ChatGPT é modificada; mudanças nas ferramentas do projeto seguem PR, revisão, CI, autorização local e verificação pós-implantação. Não criar executor genérico, não alterar testes para esconder falhas, não ampliar privilégios nem restaurar dados automaticamente. Testes de limite, tentativas, estados, observações velhas e segurança em CI; sem execução local completa.

