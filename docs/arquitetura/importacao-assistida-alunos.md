# Importação de alunos com confirmação humana

Etapa seguinte à prévia do PR #9 e à base de RA/turma do PR #12. Apenas arquivos `.xlsx` da escola escolhida. A planilha precisa ser conferida por alguém com permissão de gestão e segundo fator ativo.

## Fluxo na interface

1. Abra a escola e escolha **Importar alunos**. Selecione o ano letivo e uma planilha fictícia `.xlsx` de até 5 MB.
2. A prévia mostra nomes, datas, RAs e turmas. Linhas com RA já existente, ou nome sem acento e mesma data de nascimento, são marcadas **Já cadastrado: esta linha será ignorada** e não entram nas inclusões. Se a data estiver ausente e o nome coincidir, aparece **Possível homônimo**; é obrigatório marcar **É outra pessoa** para cadastrar uma pessoa distinta. Confirme individualmente também as repetições internas do arquivo.
3. Se houver conflito de identificação/turma, corrija a planilha e envie novamente. As pendências de informações ausentes não são completadas artificialmente.
4. Confirme. A aplicação verifica novamente turmas, RA e cadastros preexistentes dentro do advisory lock. Os alunos já cadastrados são ignorados e contabilizados no recibo sem interromper as inclusões. Se alguma linha aceita falhar, **não grava nenhum aluno aceito do lote**. Em caso de sucesso, grava alunos novos, matrículas e auditoria numa única transação; o RA já existente não é reaproveitado.

## Privacidade e segurança

- O arquivo original não é armazenado. A prévia minimizada é cifrada e autenticada no formulário, só pode ser confirmada pela mesma conta na mesma escola e expira após uma hora.
- Banco municipal guarda apenas recibo com ID aleatório, escola, ano, autoria, quantidades de alunos novos e linhas ignoradas e instante da conclusão. Nome, RA e data de nascimento ficam nas tabelas escolares sob as permissões existentes e auditoria.
- Dados de responsável, CPF, telefone, transporte, endereço, observações e sinais de AEE não são cadastrados por esta etapa. Para avançar nesses cadastros será necessário um bloco específico de importação com conferência humana.
- O campo `started_at` fica `NULL` se a planilha não informar o início da matrícula. Não copiar a data do gabarito para preencher informação ausente.
- O token não pode ser reutilizado depois de importar. O POST exige segundo fator; escola e ano são revalidados, inclusive no banco PostgreSQL.

## Conferência no notebook

Somente após revisão e merge das dependências, no PostgreSQL isolado do ensaio, nunca no banco original 5432: executar as migrations municipais; abrir Monte Sereno com as três escolas e dez turmas fictícias; subir E01, E02, E03; confirmar Laura e Heitor na E02; conferir 115 alunos e 115 matrículas nas turmas do gabarito, seis nascimentos pendentes e trilha de auditoria. Tentar confirmar o mesmo token uma segunda vez deve ser recusado; reenviar o mesmo arquivo gera uma nova conferência com 58 linhas ignoradas e recibo de zero inclusões.
