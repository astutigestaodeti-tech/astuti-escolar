# Identificação de versão no rodapé

O rodapé não consulta Git e não exibe bancos de dados, inclusive em APP_ENV=local. Informações de conexão ficam reservadas ao futuro console técnico autorizado (#239/#244).

O publicador local deve gravar /var/lib/astuti-release-view/version.json, arquivo regular fora de public, em diretório gravável exclusivamente pelo publicador autorizado e legível pelo PHP-FPM. Formato: {"schema":1,"commit":"SHA completo de 40 caracteres","deployed_at":null}. deployed_at pode ser epoch UTC inteiro quando o horário real da implantação estiver comprovado; null quando não conhecido. Máximo de 4096 bytes. Nunca divulgar a configuração, caminhos internos, segredos ou logs.

O agente deve invalidar o manifesto antes de alterar a versão em execução, publicar atomicamente após a verificação da instalação e republicar o commit restaurado em rollback após a validação. Em implantação interrompida ou versão não verificada, deixar indisponível até recuperação. Inicializar somente depois de verificar HEAD, estado registrado e saúde do ambiente. Não derivar versão instalada do GitHub main ou horário de coleta. Não alterar permissões de .git.

Este PR implementa o leitor e remove detalhes do banco do rodapé. O produtor ainda depende de instalação local controlada. Até essa etapa o rodapé pode continuar informando versão não identificada. Não afirmar correção completa antes de validar o manifesto na VPS e novo deploy/rollback.
