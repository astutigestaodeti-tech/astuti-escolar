# Chamada diária da turma - desenho (issue #54)

## Finalidade
Professor da escola registra presença de estudantes com matrícula ativa em poucos toques, em celular e computador. A gestão pode corrigir uma chamada de data passada, com motivo e confirmação recente do segundo fator. A turma apresenta frequência do mês, sem revelar observações ou dados de saúde nas listas e na auditoria.

## Caminhos considerados
1. **Chamada por turma e dia, com marca individual (escolhido):** duas tabelas municipais, transação única, índices únicos, leitura eficiente da frequência. Evita duplicação de alunos, mantém autoria e permite correções.
2. Um campo de presença em matrículas: mistura estado atual e histórico, não preserva dias e auditoria. Descartado.
3. Uma linha de sessão para cada aluno: repete data, autoria e turma, dificulta a unicidade da chamada. Descartado.

## Dados e limites
- Migração tenant 000025; attendance_sessions (turma, data, autoria, horário e última correção administrativa) e attendance_marks (sessão, aluno, situação, observação opcional até 160). Índices únicos por turma/data e sessão/aluno. CHECK para present, absent, justified.
- Observação e motivo podem conter informações sensíveis: observação nunca entra na auditoria, motivo da correção fica no registro operacional e aparece mascarado na auditoria, que mantém o autor, horários e as mudanças de status.
- Nenhuma tabela de saúde, biometria, consentimento para rotina escolar ou divulgação de fotos. A foto pequena usa exclusivamente o componente seguro de identificação, com alteração desabilitada.

## Regras de uso
- Acesso de consulta à escola (inclusive professor) pode consultar e gravar a chamada de hoje; sem permissão à escola recebe 403.
- Hoje: todos inicialmente presentes; após gravada, a mesma tela permite corrigir e gera auditoria. A gravação compara integralmente os alunos com matrícula ativa da turma sob bloqueio transacional e recusa IDs de fora. Data futura recusada.
- Data anterior: só gestão de alunos, apenas chamada já registrada, motivo entre 10 e 500 e middleware de segundo fator RECENT no POST separado. A navegação para correção histórica fica disponível à gestão na tela da turma.
- A gravação é atômica e protegida pela unicidade turma/data. Não se criam marcas para alunos transferidos ou de outra turma.
- Frequência mensal = presentes / marcas de chamada registradas no mês para o aluno. Faltas justificadas contam como ausência no percentual. Exibir abaixo de 75% quando houver chamadas e, prioritariamente, alerta de três ausências consecutivas nas últimas três chamadas em que o aluno tem marca. Não classificar quem não tem registros suficientes; avisos orientam conferência humana.
- Consultas de frequência usam número fixo de consultas por turma, não por estudante, com índices por sessão e aluno.

## Fora do escopo
- Envio a responsáveis, comunicação automática de faltas, atestados, notas, justificativas médicas e alteração de fotos; nenhuma alteração nos arquivos de importação, saída ou ficha individual.
