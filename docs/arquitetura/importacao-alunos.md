# Importação de alunos por planilha

Objetivo: importar os arquivos que as escolas mandam (pasta 02 do kit Monte Sereno) e chegar ao gabarito (pasta 01) só com conferências humanas, sem redigitação e sem inventar dado que o arquivo não traz.

## Etapas

1. **Motor de leitura e prévia (este PR).** Lê a planilha de uma escola, limpa e confere, sem gravar nada. Comando `astuti:importar-alunos-previa`.
2. **Gravação com conferência.** Lote de importação guardado, tela de conferência por escola, confirmação das duplicidades e gravação de aluno e matrícula na turma, com auditoria e segundo fator. Exige vínculo da matrícula com a turma e o campo RA no aluno.
3. **Demais fontes.** Lista de transporte da Secretaria (CSV Windows-1252, só atualiza transporte e não cria aluno; CANCELADO não volta a ficar ativo), responsáveis, quadro do RH e controle do AEE.

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
