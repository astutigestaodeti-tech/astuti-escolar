# Endereço assistido na importação de alunos

## Objetivo

Poupar a secretaria escolar de redigitar endereços presentes nos arquivos que ela própria enviou, sem copiar dados do gabarito fictício, criar alunos repetidos ou alterar endereços já conferidos.

## Abordagens consideradas

- Só importar endereço de alunos novos: simples, mas não atende a rematrícula nem completa lacunas de cadastros existentes.
- Atualizar endereços de todos os alunos reconhecidos automaticamente: rápido, mas arrisca substituir trabalho humano e unir homônimos.
- **Escolhida por Jefferson:** registrar campos disponíveis em alunos novos; para alunos já cadastrados na mesma escola e ano, oferecer confirmação individual para completar apenas campos vazios. Nunca sobrescrever campos preenchidos.

## Comportamento

- O leitor reconhece logradouro/endereço completo, número, bairro, CEP e zona de residência quando presentes. Também preserva a leitura antiga do endereço numa coluna só. CEP de oito dígitos é formatado; zona aceita Urbana/Rural; dados inválidos não são importados e recebem orientação de conferência.
- A prévia mostra os dados de endereço efetivamente fornecidos. Para linha já cadastrada, o aluno não é recriado; somente se houver um registro único e compatível de RA/nome/nascimento, vinculado à escola e ao ano selecionados, aparece a opção explícita de completar endereço.
- Ao confirmar, a aplicação revalida sob o advisory lock, dentro da mesma transação: vínculo escolar, identidade, dados de destino e quais campos continuam vazios. A opção marcada não substitui valores preenchidos após a prévia. Nenhum endereço de homônimo sem identificação segura é completado.
- Se uma linha aceita falhar, nem novos alunos nem alterações de endereço são gravados. O recibo mantém as contagens de novos e ignorados; a resposta informa quantos endereços foram completados, e a auditoria dos alunos registra antes/depois.
- A importação não consulta serviços externos, não deduz CEP, município, UF ou outros dados ausentes e não usa o `alunos.csv` de gabarito como fonte. O documento enviado não fica armazenado.

## Limites e segurança

- Uma Prefeitura por instalação, autorização por escola e segundo fator obrigatório como hoje; dados fictícios nos testes.
- Sem migração, sem alterações no banco real ou notebooks, sem mexer em histórico de matrícula, RA ou importação de transporte.
- Preservar o contrato do #13: todo já cadastrado continua contado como ignorado para criação, e homônimo sem data exige confirmação para ser criado como pessoa distinta.

## Verificação

Testes da leitura com colunas individuais e valores inválidos; importação E02 com endereços de origem; reimportação sem seleção não altera nada; seleção preenche apenas lacunas com auditoria; não atualiza aluno de outra escola; atualização concorrente permanece preservada; nenhuma alteração parcial em caso de conflito. CI: Pint, suíte PHP, Node e integração PostgreSQL.
