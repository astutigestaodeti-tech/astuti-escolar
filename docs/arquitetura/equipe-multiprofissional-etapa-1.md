# Equipe multiprofissional - etapa 1 (issue #136)

## Escopo e isolamento

A fila organiza pedidos escolares e datas de atendimento, sem registro técnico, relato livre, anexos ou prontuário. A única informação textual é a devolutiva prática à escola (até 500 caracteres), com alerta explícito para não incluir informações clínicas ou familiares. As leituras da fila e dos documentos e todas as operações são auditadas sem copiar categorias ou devolutivas. Gravações exigem segundo fator recente. A etapa 2 permanece bloqueada.

Perfis de rede: psicologo, assistente_social e pedagogo_equipe. A equipe acessa somente as próprias rotas, após segundo fator, sem ganhar permissão sobre fichas escolares. Contas com vínculo escolar ou gestão ativa não ganham acesso à fila. Cada profissional pode consultar pedidos abertos da sua área (ou a definir), assumir um pedido e consultar os que assumiu; ninguém assume pedido já atribuído. A direção da escola e o professor efetivamente vinculado à turma solicitam atendimento e recebem exclusivamente a indicação de acompanhamento e orientação prática. A secretaria escolar e a gestão municipal não recebem devolutivas. A gestão municipal consulta somente os indicadores da equipe.

## Operação de contas

Em terminal administrativo da instalação, para uma profissional com e-mail institucional, use o comando astuti:equipe-usuario com slug da Prefeitura, e-mail, perfil e opção --nome para conta nova. A senha de conta nova é digitada de modo oculto no terminal e não é parâmetro de linha de comando. Uma conta ativa existente pode receber vínculo sem trocar a senha, desde que não tenha perfil escolar/administrativo ativo na rede. Vínculos suspensos não são reativados implicitamente. Não use a conta admin para atendimento. A ativação do segundo fator é necessária para acessar a fila.

## Fluxo

A abertura é idempotente por matrícula e área em aberto; a busca ativa usa a categoria faltas/permanência e Serviço Social, sem copiar conteúdo. O pedido a definir recebe área ao ser assumido. A agenda mostra o próximo horário; atendimentos são confirmados por data, no máximo um por pedido/dia. Encaminhamentos externos registram somente destino e data; a contrarreferência guarda data e categoria de retorno, mesmo após o encerramento. A via impressa é administrativa, de acesso privado, com timbre oficial; não é laudo, parecer, avaliação nem documento publicamente verificável. Não há envio automático.

Os indicadores contabilizam eventos na competência por ano letivo. A espera média é medida em dias corridos até o primeiro atendimento realmente registrado no mês. Para cada célula, exigem pelo menos cinco estudantes distintos quando há eventos, com supressão complementar em cada distribuição. Não exibem nome, motivo, devolutiva ou identificadores. A área é a classificação na emissão.

## Limites da etapa

Não há prontuário psicológico/social, laudo, hipótese diagnóstica, estudo social, teste, relatório técnico, registro de sessão, anexo técnico, ou acervo técnico. Não há transferência de acervo ou reatribuição automática de casos de profissional desligada: a rede deve definir o procedimento administrativo antes de ampliar esse fluxo. Não substitui comprovação legal oficial ou avaliação técnica.
