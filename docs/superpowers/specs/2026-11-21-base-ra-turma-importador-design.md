# Base de RA e turma para importação de alunos

Data: 21/11/2026. Objetivo: permitir que o cadastro escolar armazene RA e turma vinculada à matrícula, requisito de gravação do importador, sem antecipar a confirmação humana de planilhas.

## Decisões

1. `students.ra` será opcional e único em cada banco municipal. Valor não fornecido fica `NULL`; jamais inferir RA.
2. `student_enrollments.school_class_id` será opcional e validado por chave estrangeira composta contra `school_classes(id, school_id, school_year_id)`. Escola e ano da turma serão idênticos aos da matrícula, inclusive fora do formulário. Restrições de exclusão são `RESTRICT`, sem cascatas.
3. Formulários de criação/edição aceitarão RA e turma, exigindo gestão escolar e segundo fator existente. Validação em português. Alterações em aluno e matrícula serão uma transação; ambas as versões serão conferidas quando houver alteração da turma.
4. Auditoria registrará antes/depois de RA e vínculo da turma, como os demais atributos escolares, sem acrescentar pistas da planilha, dados de responsáveis ou documentos.
5. A transferência de dados fictícios de esquema legado pode trazer menos colunas. Verificação compara somente as colunas da origem; as novas permanecem nulas, sem modificar a origem.
6. A tela de ficha mostrará RA e turma quando informados. Nenhuma importação automática é habilitada neste bloco.

## Verificação

- Cadastro e edição auditados com RA e turma; duplicidade de RA impedida no formulário e no banco.
- Escola ou ano letivo divergentes recusados tanto no formulário quanto pela chave composta.
- Tentativa sem perfil ou segundo fator negada; versões concorrentes recusadas.
- Regressão do histórico e dos testes de transferência, seguida de Pint e CI completo.
