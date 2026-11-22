# Importação assistida de alunos: conferência e gravação

Data: 21/11/2026. Continuação do PR #12, sobre a prévia do PR #9.

## Intenção

Permitir que uma secretaria escolar importe uma planilha `.xlsx` da própria escola, revise os nomes e turmas, confirme eventuais linhas repetidas e grave aluno e matrícula com autoria, segundo fator e auditoria. O objetivo inicial são as três planilhas fictícias da pasta 02 do kit Monte Sereno, totalizando 115 alunos, sem inferir datas ausentes.

## Opções consideradas

1. **Salvar arquivo e criar fila de importação:** suporta revisão em dias diferentes, porém mantém dados de menores em arquivos privados por mais tempo, exige expurgo e nova modelagem de acesso.
2. **Prévia temporária cifrada no próprio formulário (escolhida):** o servidor não armazena o arquivo nem os nomes entre requisições. A prévia é autenticada pela criptografia da aplicação, restrita à conta, escola e validade de uma hora. Um recibo no banco municipal impede reaplicação; apenas ele permanece após a importação.

## Comportamento

- GET de envio só para gestor municipal, diretor ou secretário autorizado à escola e com segundo fator confirmado. POSTs protegidos também por middleware de segundo fator. Professor ou conta de outra escola recebe 403.
- Escola é parâmetro autenticado da URL; ano letivo é explicitamente escolhido entre ativos/planejados. As turmas vêm do cadastro e são conferidas novamente no momento da gravação.
- O leitor existente produz a prévia. O formulário mostra cada aluno, nome, nascimento, RA, turma, origens e pendências. Duplicidade de linhas da mesma pessoa exige checkbox específico. Conflito de turma, RA ou outro atributo deve ser corrigido no arquivo e impede toda a importação.
- O formulário envia somente dados mínimos cifrados: nome, nascimento, RA, turma, origem e mensagens de conferência. Não envia CPF, telefone, responsável ou arquivo original. Não registra dados pessoais no banco administrativo.
- Antes de gravar, consultar aluno preexistente por RA ou nome com acento/capitalização ignorados e data de nascimento. Se encontrar, recusar o lote inteiro. Serializar importações e impedir replay pelo UUID do recibo.
- Numa única transação municipal criar o recibo, cada aluno, a matrícula ativa e as auditorias. O dia de início da matrícula é NULL quando não consta na planilha, sem inventar data. Após confirmação, não há arquivo para reter ou excluir.
- A trilha de auditoria registra a autoria de cada aluno e matrícula e um evento de conclusão do lote. O evento do lote contém somente IDs, escola, ano e contagem.

## Fora deste bloco

- Atualizar aluno existente ou fundir cadastros automaticamente.
- Cadastrar dados de responsáveis, endereço, transporte, saúde, laudos ou data de matrícula não informada.
- Permitir importação sem revisão ou segundo fator; disponibilizar importação em massa de documentos diferentes da pasta 02.

## Aceitação

- Antes da confirmação, nenhuma linha escolar criada; duas duplicidades da E02 requerem confirmação humana.
- E01, E02 e E03, após confirmação, totalizam 115 alunos e matrículas com vínculo na turma correta; as seis datas só com idade ficam nulas.
- Reenvio do token, adulteração, escola fora do escopo, segundo fator ausente, aluno/RA já existente ou turma divergente não geram gravação parcial.
- CI PostgreSQL 17, Pint e interface aprovados antes da entrega. Sem merge e sem dados reais.
