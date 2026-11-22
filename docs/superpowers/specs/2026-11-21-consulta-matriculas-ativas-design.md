# Consulta de matrículas ativas por escola - design

## Objetivo
Permitir que a secretaria de uma escola autorizada visualize matrículas ativas, seus alunos, ano letivo e data de início, com busca simples por nome e paginação. Hoje há consulta de alunos, mas não uma relação de matrículas.

## Alternativas
1. Exibir informações de matrícula somente na ficha de cada aluno: evita nova rota, mas não permite conferir as matrículas da escola em uma lista.
2. Listar matrículas inclusive encerradas: oferece histórico, mas amplia exposição de alunos que já não pertencem à escola e exige regras próprias.
3. **Selecionada:** lista exclusiva de matrículas ativas da escola da URL, autenticada, paginada e somente leitura; mantém histórico individual existente.

## Regras
- Mesmos middlewares de autenticação, TOTP e resolução municipal; `Gate::authorize('view', [$school, $municipality])`.
- Escola precisa estar ativa e a consulta deve filtrar `student_enrollments.school_id` + `status = active` e excluir estudantes arquivados.
- Mostrar nome do aluno com link para ficha, ano letivo, situação "Ativa" e data de início quando informada; não inventar turma.
- Busca pelo nome (até 120 caracteres), sem diferenciar maiúsculas/minúsculas em PostgreSQL e sem interpretar %/_ como curingas.
- Ordenação estável por ano letivo decrescente, nome e UUID; 25 matrículas por página.
- Incluir acesso na página de detalhe da escola; GET apenas, sem escrita.

## Restrições
Dados exclusivamente fictícios no notebook; sem migração, modificação de banco, .env, backups ou ações nos PostgreSQL originais 5432/MariaDB. Produção: uma VPS e PostgreSQL exclusivos por Prefeitura, sem compartilhamento de dados. PR #5 permanece draft.