# Importação de alunos com confirmação humana

Etapa seguinte à prévia do PR #9 e à base de RA/turma do PR #12. Apenas arquivos `.xlsx` da escola escolhida. A planilha precisa ser conferida por alguém com permissão de gestão e segundo fator ativo.

## Fluxo na interface

1. Abra a escola e escolha **Importar alunos**. Selecione o ano letivo e uma planilha fictícia `.xlsx` de até 5 MB.
2. A prévia mostra nomes, datas, RAs e turmas. Confira a escola, a turma e os alunos repetidos; confirme individualmente cada repetição que realmente se refere à mesma criança.
3. Se houver conflito de identificação/turma, corrija a planilha e envie novamente. As pendências de informações ausentes não são completadas artificialmente.
4. Confirme. A aplicação verifica novamente turmas, RA e possíveis alunos preexistentes. Se houver qualquer erro, **não grava nenhum aluno do lote**. Em caso de sucesso, grava alunos, matrículas e auditoria numa única transação.

## Privacidade e segurança

- O arquivo original não é armazenado. A prévia minimizada é cifrada e autenticada no formulário, só pode ser confirmada pela mesma conta na mesma escola e expira após uma hora.
- Banco municipal guarda apenas recibo com ID aleatório, escola, ano, autoria, contagem e instante da conclusão. Nome, RA e data de nascimento ficam nas tabelas escolares sob as permissões existentes e auditoria.
- Dados de responsável, CPF, telefone, transporte, endereço, observações e sinais de AEE não são cadastrados por esta etapa. Para avançar nesses cadastros será necessário um bloco específico de importação com conferência humana.
- O campo `started_at` fica `NULL` se a planilha não informar o início da matrícula. Não copiar a data do gabarito para preencher informação ausente.
- O token não pode ser reutilizado depois de importar. O POST exige segundo fator; escola e ano são revalidados, inclusive no banco PostgreSQL.

## Conferência no notebook

Somente após revisão e merge das dependências, no PostgreSQL isolado do ensaio, nunca no banco original 5432: executar as migrations municipais; abrir Monte Sereno com as três escolas e dez turmas fictícias; subir E01, E02, E03; confirmar Laura e Heitor na E02; conferir 115 alunos e 115 matrículas nas turmas do gabarito, seis nascimentos pendentes e trilha de auditoria. Tentar repetir o mesmo lote deve ser recusado.
