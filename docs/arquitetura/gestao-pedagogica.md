# Gestão pedagógica (#181)

## Conselho de classe

Migration tenant 000059: `class_councils` por turma e período, e `class_council_students` por matrícula do conselho. FKs restritivas, unicidade, CHECKs para situação e encaminhamento. A ausência de linha é "Não iniciado"; a primeira gravação inicia o conselho. Conclusão exige data da reunião, reabertura exige motivo (10 a 500 caracteres) e novo segundo fator recente. O formulário não altera notas, frequência ou sondagem. Os valores consultados são vivos, não constituem uma ata histórica imutável dos indicadores; os registros do conselho ficam somente leitura após concluir.

As matrículas não arquivadas que intersectam o período compõem a lista. Parecer curto (500 caracteres), encaminhamento em lista fechada e observação (500). A direção define data, participantes, ações da turma, encaminhamento e observação. Professor com atribuição vigente, e-mail institucional correspondente e escopo na escola registra somente o parecer. A Secretaria municipal acessa resumos e, especificamente para distorção, a lista nominal autorizada; não acessa pareceres nem edita conselho. Coordenação: a autorização contempla um vínculo explícito `school_coordinator`, mas o perfil ainda não está provisionável no catálogo de papéis da plataforma; não se concede esse acesso por texto do cargo nem por perfil de secretaria. A implementação desse perfil exige decisão própria antes de habilitá-lo.

Links para equipe multiprofissional e busca ativa levam ao fluxo autorizado existente, sem criar solicitações ou casos. O link para busca ativa fica disponível à direção, conforme o controle de acesso existente.

## Distorção idade-série

Critério definido na issue: idade completa em 31/03 do ano letivo, dois anos ou mais acima da esperada. Etapas 14 a 21 e 41 do ensino fundamental de nove anos correspondem a 6 a 14 anos. A etapa confirmada do registro 20 prevalece; na falta dela, usa-se `SugestaoDeTurma::etapa`, com indicação "a confirmar". Educação infantil, EJA, AEE e etapas sem idade esperada unívoca não entram no denominador; nascimento ausente ou posterior ao corte também não. Indicador de matrículas ativas, não reproduz o universo de referência de uma divulgação oficial do Inep. Não há cálculo anterior no painel Equidade/VAAR. Não se duplicou o indicador nem se inferiu idade para turmas multisseriadas.

## Aulas previstas x dadas

Período limitado ao ano letivo. Usa calendário municipal e da escola, com interrupções prevalecendo sobre dia letivo extra, como SchoolCalendar. Dias da semana com horários no registro 20 substituem o padrão segunda a sexta; dia letivo extra explicitamente marcado entra mesmo fora do horário habitual. Sessões de frequência no período até hoje contam como aulas dadas (não equivalem a horas-aula, nem a presença de todos os alunos). Sessão fora do calendário é destacada e não cobre outro dia. Hoje ainda pode receber chamada; atraso é o número de dias previstos já transcorridos sem sessão. Concluído quando o período terminou e não há atraso. Não há criação de chamadas automáticas.

## LGPD, auditoria e acesso

Conselho somente com segundo fator, privado e no-store. Pareceres, observações, motivos de reabertura, participantes e ações não fazem parte de exportações ou da consulta da rede. Validação de formulário não repõe automaticamente os textos na sessão; transições pedem confirmação recente sem copiar o motivo para o desafio de 2FA. Auditoria contém apenas situação e nomes dos campos alterados (lista textual construída a partir de atributos do modelo, não valores), com autoria do AuditTrail. O painel seleciona somente status dos conselhos. A distorção nominal só é retornada sob autorização de direção ou gestão municipal, por escola, sem os demais registros do conselho. Não incluir CID, laudo, relatos de saúde ou familiares em texto livre. Todas as rotinas do conselho devem ser homologadas com dados fictícios antes de uso real.

## Validação

Testes de cálculo, acesso, fechamento/reabertura e consultas em lote; telas em AllSchoolPagesOpenTest. Não alterar o gerador do Educacenso. Os indicadores e pareceres não são transmitidos a sistemas externos.
