# Importação de alunos por planilha

Objetivo: importar os arquivos que as escolas mandam (pasta 02 do kit Monte Sereno) e chegar ao gabarito (pasta 01) só com conferências humanas, sem redigitação e sem inventar dado que o arquivo não traz.

## Etapas

1. **Motor de leitura e prévia (este PR).** Lê a planilha de uma escola, limpa e confere, sem gravar nada. Comando `astuti:importar-alunos-previa`.
2. **Gravação com conferência (novo PR).** Prévia cifrada no formulário por uma hora, confirmação humana de linhas duplicadas, revalidação de turmas/alunos existentes e gravação transacional com auditoria e segundo fator. Só o recibo sem dados pessoais é guardado. Veja `docs/arquitetura/importacao-assistida-alunos.md`.
3. **Transporte escolar (PR seguinte).** CSV UTF-8/Windows-1252 por escola e ano, conferência por RA, gravação auditada só da situação do transporte na matrícula ativa, sem criar alunos nem reativar CANCELADO. Veja `docs/arquitetura/importacao-transporte.md`. O arquivo real da Secretaria ainda precisa ser confrontado com o leitor; os testes usam apenas dados fictícios.
4. **Demais fontes.** Responsáveis, quadro do RH e controle do AEE, com análises de LGPD e conferência específicas.

## Regras do motor

- **A escola é escolhida por quem importa**, nunca deduzida do nome do arquivo. As turmas vêm do cadastro da escola no ano letivo.
- **Cabeçalho localizado automaticamente** nas 20 primeiras linhas, por sinônimos ("NOME DA CRIANÇA", "Aluno", "Nome"); títulos mesclados acima são ignorados.
- **Turma:** pela coluna da linha ("1º A", "1A", "1° ano A" e "1º ANO - A" são a mesma turma); sem coluna, pelo nome da aba ("MII-A") ou pelo turno da aba ("MANHÃ"), se houver uma turma só nesse turno. Sem correspondência, o aluno fica em **conferir**.
- **Nomes:** CAIXA ALTA volta com capitalização de cadastro e acentos ("JOÃO PEDRO" vira "João Pedro"; "da", "dos" e "e" ficam minúsculos). Nomes já escritos normalmente não são alterados.
- **Nascimento:** data do Excel, número de série do Excel, dd/mm/aaaa, dd-mm-aaaa, d/m/aa e aaaa-mm-dd. "8 anos" **não vira data**: fica pendente, com a idade guardada como pista. Datas futuras ou impossíveis ficam pendentes com o motivo.
- **Duplicidade:** mesmo nome sem acento e mesma data viram **um aluno em conferir**, com as duas origens. O dado que falta numa linha (como o RA) é aproveitado da outra; valores diferentes entre as linhas são apontados.
- **Linhas de total e linhas em branco** não viram aluno; o total aparece na lista de linhas ignoradas.
- **Pendências** (o arquivo não traz): RA, transporte, endereço, CEP, observação de inclusão. Pendência não impede a gravação; conferir impede.
- **Pistas** (responsável, telefone, CPF do responsável, observação, idade) ficam guardadas para as próximas etapas, sem virar cadastro sozinhas.

## Segurança do leitor .xlsx

Leitor próprio, só com as extensões `zip` e `xmlreader` do PHP: sem rede (`LIBXML_NONET`), limite de 20 MB por parte interna, de linhas, colunas e abas, e sem execução de fórmulas (usa o último valor salvo).

## Verificação

`tests/Unit/Import/KitMonteSerenoStudentImportTest.php` importa as três planilhas do kit e compara com `alunos.csv`: 115 alunos, todos na turma e na escola certas, datas iguais ao gabarito em todos os formatos, 6 alunos só com idade (pendentes), RA, transporte, endereço e série conferidos, e exatamente 2 duplicidades em conferir (Laura e Heitor).

## Base de persistência para a etapa 2

- A matrícula passou a aceitar `school_class_id` opcional, com chave estrangeira composta: turma, escola e ano letivo devem corresponder, inclusive em inserção SQL direta. Não há exclusão em cascata.
- O aluno passou a aceitar `ra` opcional com índice único sobre a forma normalizada (maiúsculas e sem hífen), inclusive para SQL direto. O formulário normaliza caixa e hífen, verifica duplicidade e mantém a autoria e a auditoria das mudanças.
- A edição de turma identifica a matrícula por UUID e usa tokens PostgreSQL `xmin` próprios da matrícula e do aluno, evitando conflitos até quando duas gravações ocorrem no mesmo segundo. Em caso de divergência, nenhuma das duas alterações é gravada.
- Registros antigos permanecem sem RA ou turma até preenchimento legítimo. A cópia fictícia MariaDB para PostgreSQL compara só colunas existentes na origem e deixa novos campos nulos, sem reescrever dados originais.
- **A gravação assistida de planilhas está implementada neste bloco** após a conferência humana e segundo fator. O arquivo original nunca é armazenado; ficam pendentes apenas os dados que a fonte não informou e que não integram ainda o cadastro de alunos.

## Leitura de PDF e foto (OCR)

Prioridade definida pelo Jefferson em 21/11/2026: a escola pode enviar a lista impressa escaneada (PDF), uma foto da lista (JPG/PNG) ou um PDF digital, e a leitura entra na mesma conferência das planilhas.

- **Tudo no servidor**: Poppler (`pdfinfo`, `pdftotext`, `pdftoppm`) e Tesseract com português. Nenhum documento vai para serviço externo (dados de crianças, LGPD). As cópias temporárias são apagadas ao fim de cada leitura.
- **PDF digital** é lido pelo texto do arquivo, sem OCR e sem dúvida de leitura. **PDF escaneado** vira imagem a 300 dpi; **foto** vai direto ao Tesseract (`--psm 4`, que mantém cada linha da tabela inteira).
- **Tabela**: a linha de cabeçalho é achada pelos mesmos sinônimos das planilhas; as colunas são definidas pela posição de cada título, e as palavras das linhas seguintes são encaixadas pela posição.
- **Confiança**: cada linha leva a menor confiança das palavras do **nome** (o único campo sem formato que denuncie erro). Abaixo de 85%, o aluno fica em **conferir** ("Leitura automática com baixa confiança"). Data e RA fora do formato ficam pendentes; turma que não bate com uma turma existente fica em conferir.
- **Limites**: 10 MB, 10 páginas por PDF, 60 megapixels por foto, 90 segundos por etapa; o tipo é reconhecido pelo conteúdo do arquivo, nunca pelo nome.
- **Regra dos testes** (`OcrDocumentImportTest`): nenhum erro de leitura passa calado; todo aluno sem conferência bate com o gabarito em nome, data, RA e turma. Documentos de teste em `tests/Fixtures/kit-monte-sereno/ocr`, gerados pelo `gerar_documentos.py` a partir do gabarito.

Resultado medido nos documentos fictícios: PDF digital da EMEI, 40 de 40 sem conferência; lista escaneada da EMEF (com ruído forte), 58 de 58 com todos os campos corretos e 25 nomes marcados para conferir; foto da escola rural, 9 de 9 com uma data ilegível deixada pendente.
