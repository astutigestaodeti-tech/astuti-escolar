# Notas por período e boletim do Ensino Fundamental

Implementação inicial da issue #97. Não há recuperação, parecer descritivo, aprovação final, histórico escolar nem integração com SED.

## Modelo de avaliação

A migration municipal `2026_11_23_000034_create_grading_periods_and_grades.php` cria:

- `academic_periods`: período avaliativo do ano letivo, inicialmente quatro bimestres proporcionais ao calendário cadastrado, com início e fim. Novos anos letivos também recebem os quatro bimestres automaticamente por trigger municipal. A gestão pode configurar três trimestres ou ajustar datas sem sobreposição, respeitando as datas do ano.
- `curricular_components`: lista única da rede com os sete componentes iniciais obrigatórios e Língua Inglesa opcional. A gestão municipal pode acrescentar ou ativar/desativar componentes; desativar não remove notas antigas.
- `grade_entries`: nota decimal de 0,0 a 10,0 com uma casa, faltas opcionais, matrícula escolar de origem, turma, período e componente. Unicidade por matrícula, período e componente. Não há cadastro duplicado de criança.

A lista da turma só mostra matrículas ativas da unidade e o Ensino Fundamental. A gravação é transacional, validada para a turma, período e componente selecionados; valores idênticos não disparam atualização nem auditoria. Vírgula e ponto são normalizados. Um período fechado só aceita alteração pela gestão municipal, direção ou secretaria escolar, via rota de correção com segundo fator recente e motivo. O professor não reabre nem corrige períodos fechados.

## Segurança e LGPD

A consulta de notas por turma exige acesso à escola. A escrita permite gestão municipal, direção e secretaria escolar da unidade; professor somente se tiver vínculo ativo `teacher` na escola e um servidor ativo atribuído à turma, cujo e-mail institucional coincida com o e-mail da conta autenticada. Vínculo com outra turma ou escola não concede permissão de gravação.

O boletim segue exatamente a autorização da ficha do aluno: vínculo de matrícula ativa na escola consultada. O relatório não usa nomes de outras unidades. Tabelas de notas e frequência são carregadas do banco municipal isolado, sem endpoints públicos, e páginas contêm `Cache-Control: private, no-store`.

Eventos de criação ou alteração de nota, fechamento de período e configuração de componentes/períodos registram autoria e o valor anterior/novo no `AuditTrail`. Somente as novas entidades recebem campos auditáveis próprios. O mascaramento global não foi modificado; motivos de exclusões de alunos e servidores permanecem como antes.

## Boletim

A média anual exibida é aritmética simples das notas já lançadas em cada componente. Períodos vazios não contam como zero nem entram no divisor. O limite de referência visual vem de `ASTUTI_MEDIA_MINIMA` (padrão 6,0) e **não** gera aprovação ou reprovação final.

A frequência anual é obtida das marcações reais de chamada na escola e no ano letivo. Presenças contam no numerador, enquanto presenças, faltas e faltas justificadas contam no denominador, seguindo o critério já usado no relatório mensal. Sem chamadas o boletim mostra "não há chamadas". O documento evidencia quais períodos estão abertos e tem CSS A4 com JavaScript externo para impressão.

## Revisão

Os testes usam somente dados fictícios e cobrem permissão escolar, vínculo do professor por e-mail, abertura e fechamento de períodos, correção recente, notas em lote, auditoria, média, frequência e impedimento de notas na Educação Infantil. `AllSchoolPagesOpenTest` inclui lançamento, boletim, componentes e configuração de períodos para inspeção de margens em 1440 e 390 px.

Antes de mesclar: executar Pint, testes PHP e inspeção geométrica de margens. Não migrar nem testar em bancos reais de Prefeitura ou do notebook.
