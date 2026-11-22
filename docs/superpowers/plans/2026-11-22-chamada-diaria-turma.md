# Chamada diária da turma - plano de implementação

> **Para quem executar:** aplicar as mudanças em um PR independente com base no main atualizado, em um commit contendo testes, implementação e Pint.

1. **Persistência e LGPD**: testar unicidade turma/data e sessão/aluno e CHECK de situações; criar migração tenant 000025 e modelos AttendanceSession e AttendanceMark; adicionar allowlists de auditoria que excluem observação e ocultam motivo de correção; ajustar limpeza de testes descartáveis.
2. **Registro transacional**: testes de criação por professor, aluno de outra turma/sem matrícula e data futura; criar AttendanceController com GET de hoje/histórico, POST de hoje e POST histórico com gestão e 2FA recente, bloqueio transacional da turma e matrículas, revalidação dos IDs, autoria e auditoria sem observação.
3. **Formulário acessível**: testes da pré-seleção de presença, foto interna e resumo; criar view classes/attendance.blade.php, inputs de toque único, feedback em português e CSS exclusivamente .chamada-*; navegação para histórico na tela da turma.
4. **Frequência mensal**: testes de porcentagem, abaixo de 75%, três faltas consecutivas e número fixo de consultas; serviço AttendanceSummary e coluna na lista da turma, preservando idade e contato já existentes.
5. **Verificação**: verificar ausência de arquivos protegidos no diff, executar Pint/testes onde possível, usar CI PHP + frontend + PostgreSQL 17 + migração MariaDB/PostgreSQL, corrigir falhas em commit de código e teste, comentar na issue #54 só após CI verde. Não mesclar nem acessar bancos do notebook.

**Verificação planejada:** testes Feature da chamada, classe existente e migração; assertivas de auditoria; Pint; CI completo.
