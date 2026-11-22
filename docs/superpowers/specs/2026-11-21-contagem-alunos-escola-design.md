# Contagem de alunos por escola na lista autorizada

## Intenção
Mostrar diretamente em cada cartão de escola a quantidade real de alunos com matrícula ativa naquela unidade, permitindo conferência rápida dos dados fictícios do ambiente PostgreSQL.

## Abordagens
- Usar a quantidade de matrículas: simples, mas duplicaria um aluno com dois vínculos ativos na mesma escola.
- **Contar alunos distintos mediante a mesma regra da lista de alunos:** escolha técnica para preservar consistência e evitar somas artificiais.
- Manter os cartões sem contagem: menos trabalho, porém obriga abrir escola por escola.

## Comportamento e limites
- A página `GET /m/{municipio}/escolas` continua exibindo somente escolas ativas autorizadas, conforme `SchoolAccess`.
- Cada cartão exibe `N aluno(s) ativo(s)`, baseado em alunos existentes com ao menos uma matrícula ativa na escola, sem duplicar por múltiplos vínculos do mesmo aluno.
- Escola sem aluno exibe `0 alunos ativos`. Não buscar nem exibir alunos de outras escolas ou municípios.
- Computar em uma consulta de escolas com subconsulta contadora, evitando N+1. Nenhuma gravação/migração, somente dados fictícios.
- Verificar contagem de 2 alunos ativos e um duplicado, vínculo encerrado excluído, outra escola excluída, escola sem alunos igual a zero, autorização preservada e CI PostgreSQL.
