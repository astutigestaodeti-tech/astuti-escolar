# Conteúdo da aula online (#188)

Fase 3, parte online, do [desenho do diário offline](diario-offline.md). Não habilita dispositivo, cache, API ou sincronização.

## Modelo

Migration tenant 000061: lesson_records por turma, data e componente curricular. PostgreSQL 17 garante unicidade inclusive quando o componente é nulo (NULLS NOT DISTINCT). Componente nulo representa polivalente na educação infantil e anos iniciais, conforme etapa Inep confirmada, sugestão existente ou descrição explícita da etapa na falta de código. Na dúvida, exige componente. Componentes ativos são selecionáveis; um componente desativado permanece consultável e corrigível quando já houver registro na data. Conteúdo obrigatório até 1.000 caracteres; atividade opcional até 500; BNCC opcional como lista JSON de até 10 códigos validados por formato (EI, EF e EM). Não há catálogo nem validação de existência curricular da habilidade.

Cada registro tem UUID, autoria inicial, última autoria, motivo de correção, versão e operation_id UUID único (nulo em gravações online). A versão começa em 1; versão 0 no formulário significa registro ainda inexistente. Cada mudança real incrementa a versão, sem incremento em envio idêntico. Versão antiga é recusada, sem sobrescrever. A transação bloqueia a linha permanente de ClassYearClosure, serializando também criações da mesma turma e protegendo o fechamento; o trigger de fechamento existente é reutilizado na nova tabela.

O UUID de operação fica reservado para a integração offline. A rota online não aceita UUID de operação fornecido pelo cliente. Uma alteração online efetiva deixa operation_id nulo. O campo único sozinho não é um histórico idempotente: a integração offline precisará registrar resultados de operações anteriores separadamente, inclusive quando uma versão posterior substituir a operação, e aplicar as mesmas travas, verificação de versão e auditoria. Não há promessa de idempotência offline nesta fase.

## Permissões e interface

- Professor com atribuição vigente na turma registra e edita hoje; para data anterior dentro do ano letivo, exige motivo de 10 a 500 caracteres e segundo fator recente (mesmo padrão de correção da chamada).
- Direção e coordenação com vínculo explícito na unidade exigem motivo e segundo fator recente em qualquer data. O perfil school_coordinator ainda não é provisionável no catálogo da plataforma; não se concede acesso por texto de cargo. A disponibilização desse perfil exige decisão e implementação próprias.
- Secretaria escolar e gestão municipal consultam, não editam; professor de outra turma não acessa o conteúdo nem o relatório.
- Data futura, fora do ano letivo ou turma encerrada recusam gravação. O relatório permanece disponível para consulta conforme escopo. A chamada e o conteúdo têm formulários independentes: não é preciso criar uma sessão de frequência para registrar conteúdo.
- Tela da chamada: escolher data e componente, consultar, salvar. Relatório Conteúdos ministrados por período acadêmico, com componente, data, conteúdo, atividade e códigos; CSS de impressão e botão externo, sem PDF gerado pelo servidor.
- Painel de gestão pedagógica: com conteúdo registrado conta datas distintas no período, até hoje, independentemente de chamada ou quantidade de componentes. Não altera previstas, dadas ou situação. Não seleciona o texto no painel da rede.

## Privacidade

A aula pertence à turma, sem campos de aluno. Aviso: "Não registre nomes nem situações de alunos aqui". Não é possível garantir automaticamente que texto livre não mencione aluno; a orientação é obrigatória. Relatório e chamada com private, no-store. Auditoria usa identificador, versão e nomes de atributos alterados, sem conteúdo, atividade, códigos ou motivo. Validação retorna somente mensagens, sem repopular textos livres na sessão; confirmação recente é exigida sem copiar o formulário no desafio de 2FA.

## Testes

Usar bimestres do gatilho e componentes iniciais da rede, sem criá-los novamente. Testes de permissão, versão, motivo, data, fechamento, BNCC, unicidade, relatório em lote e indicador. Adicionar relatório à conferência de telas e limpeza da tabela aos bancos descartáveis de teste. A homologação offline e o RIPD continuam pendentes.
