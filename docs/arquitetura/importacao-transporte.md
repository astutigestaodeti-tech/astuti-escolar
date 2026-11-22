# Importação da situação do transporte escolar

Este bloco aceita uma lista CSV por escola e ano letivo e atualiza a situação e, quando informados, os dados operacionais do transporte (linha, ponto de embarque e veículo adaptado) em matrículas já existentes. Nenhum aluno ou matrícula é criado, excluído ou transferido.

## Formato aceito

- `.csv` até 2 MB, UTF-8 ou Windows-1252, com separador `;` ou `,`.
- Coluna **RA** (também `RA SED` ou `Registro do aluno`) e coluna **Situação** (também `Status`, `Transporte`, `Utiliza transporte escolar`) obrigatórias.
- Coluna **Nome** opcional, conferida quando presente. Campos opcionais: **Linha/Rota** (até 60 caracteres), **Ponto de embarque/Ponto** (até 160) e **Adaptado/Veículo adaptado** (`SIM`, `NÃO`, `S`, `N` ou `X`, sendo `X` sim). Os demais campos são desconsiderados.
- `SIM`/`ATIVO` vira `active`; `NÃO` vira `not_requested`; `CANCELADO` vira `cancelled`. Situações desconhecidas impedem todo o lote.
- No máximo 500 linhas não vazias por lote. RA inválido, repetido, de outra escola/ano ou sem matrícula ativa recusa o lote inteiro.
- Cada registro deve estar em uma linha física. Aspas podem proteger separadores dentro de uma coluna, mas aspas abertas que atravessam linhas são recusadas para impedir a omissão silenciosa de alunos.
- Colunas alternativas duplicadas (por exemplo, `Status` e `Situação do Transporte` no mesmo cabeçalho) são recusadas como ambíguas. Duas matrículas ativas para o mesmo aluno, escola e ano também interrompem a conferência.

## Passos na interface

1. Acesse uma escola com permissão de gestão e 2FA confirmado. Clique em **Importar lista de transporte**.
2. Selecione o ano e o CSV fictício. A prévia mostra aluno, RA, situação atual e proposta, sem realizar mudanças.
3. Confira a lista e confirme. A prévia cifrada expira em uma hora, pertence à mesma conta/escola e nunca substitui uma conferência nova após mudança concorrente.
4. A transação atualiza `transport_status` e os três campos opcionais de `student_enrollments`, escreve auditoria antes/depois e recibo municipal sem dados pessoais. A ausência de uma coluna ou o conteúdo vazio não apaga dados anteriores; `NÃO` explícito altera a informação de adaptação para falso. Qualquer falha desfaz todo o lote.

Um transporte já `cancelled` não pode voltar para `active` ou `not_requested` por importação, nem quando um novo CSV indica SIM. A reversão, se necessária, exige um procedimento específico com autorização e auditoria futura.

## Proteção de dados

O arquivo não é guardado no servidor. O token de confirmação guarda IDs, RA, situação, detalhes operacionais e versão necessários para revalidar a operação, cifrados e autenticados, com validade de uma hora. O banco guarda apenas os campos de transporte e recibo mínimo; não incorpora responsável, CPF, diagnóstico, laudo ou AEE. Ponto de embarque e indicador de adaptação são omitidos dos valores da auditoria como `[REDACTED]`, mantendo registro da alteração. Os detalhes são visíveis apenas a pessoas com permissão de consulta da escola; a edição segue reservada à gestão com segundo fator confirmado.

## Limitação assumida

O CSV original da lista de transporte da pasta 02 de Monte Sereno **não está no repositório**. Os testes demonstram o formato documentado usando alunos sintéticos. Antes de afirmar compatibilidade com o arquivo real, é obrigatório confrontar os cabeçalhos e valores da fonte recebida e ajustar o leitor, sem importar dados reais em desenvolvimento.

## Lista da Secretaria sem RA (21/11/2026)

A lista real do kit (`SME_lista_transporte_escolar.csv`) vem da Secretaria, sem RA, em Windows-1252, com nomes em caixa alta e sem acento, e cobre as três escolas.

- Identificação: RA quando houver; senão, nome sem acento mais data de nascimento, só entre as matrículas ativas da escola e do ano escolhidos. Sem data, a identificação é só pelo nome.
- Alunos achados pelo nome exigem a marcação "Conferi..." antes de gravar (`conferido_nomes`).
- Linhas que não correspondem a uma matrícula ativa da escola (outras escolas, transferidos), homônimos e transporte cancelado que o arquivo tenta reativar não travam o lote: aparecem em "Linhas que ficam de fora" e não são gravadas. A mensagem não revela se o aluno existe em outra escola.
- Nome que contradiz o RA e o mesmo aluno em duas linhas continuam recusando o arquivo inteiro. Se nenhuma linha puder ser aplicada, o arquivo é recusado com explicação.
- Linha, ponto de embarque e "adaptado" são agora lidos, conferidos e gravados somente quando houver valor; o vazio não apaga informação anterior. A lista de alunos mostra linha e oferece filtro por veículo adaptado, e a ficha detalha ponto e adaptação sem revelar diagnóstico.
