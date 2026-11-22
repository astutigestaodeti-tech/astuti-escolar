# Histórico de vínculos na ficha do aluno - design

## Objetivo
Na ficha individual consultada por uma escola autorizada, informar o histórico de vínculos do aluno **na própria escola**, inclusive concluídos, seus anos letivos, situação e datas cadastradas.

## Alternativas consideradas
1. Manter só anos de vínculos ativos: menor mudança, mas não ajuda a conferir histórico.
2. Exibir vínculos de todas as escolas do município: expõe informações além da escola autorizada.
3. **Selecionada:** exibir vínculos históricos somente da escola da rota, após confirmar vínculo ativo atual nessa mesma escola.

## Comportamento
- Manter autorização da escola e critério de existência de vínculo ativo para abrir a ficha.
- Depois da autorização, consultar vínculos de qualquer situação **somente** com `school_id` da escola acessada, ordenados por ano letivo decrescente.
- Exibir ano, situação legível e datas de início e encerramento quando informadas, sem inferir turma ou outra escola.
- Manter consulta somente leitura, trilha de auditoria `student.viewed`, isolamento municipal e segundo fator já existentes.
- Testar histórico de anos anteriores e impedir exposição de vínculo em outra escola.

## Fora de escopo
Criação/edição de matrículas, turma, alterações de banco, dados reais, exposição de vínculos de escolas não autorizadas.