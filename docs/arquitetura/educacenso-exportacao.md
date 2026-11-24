# Exportação para o Educacenso (Matrícula Inicial)

Planejamento aprovado para implementação em fases. Base: **leiaute oficial de importação e exportação da Matrícula Inicial 2026 (versão V5)**, **tabelas auxiliares 2026** e **"Etapas e Instruções Gerais para a Migração no Sistema Educacenso, 1ª Etapa da Coleta do Censo Escolar 2026"** (PDF de 21 páginas), todos do Inep. Nenhum campo, código ou regra deste documento vem de leiaute de outro ano.

## 1. Calendário e para que serve agora

- A 1ª etapa do Censo 2026 (Matrícula Inicial) teve data de referência em 27/05/2026 e coleta até 31/07/2026, com período de retificação depois. **Esse prazo já passou.** Nenhuma Prefeitura cliente vai enviar este arquivo de 2026.
- O uso real será:
  1. **Matrícula Inicial 2027**, com o leiaute 2027 que o Inep publicar (normalmente em maio). O motor descrito aqui lê o leiaute de cada ano de um arquivo gerado a partir da planilha oficial. Trocar de ano é extrair a planilha nova e revisar a aba "atualizações da versão", sem reescrever o sistema.
  2. **Situação do Aluno 2026** (2ª etapa, prevista de 01/02/2027 a 12/03/2027), que usa outro leiaute, ainda não publicado. Ela depende dos códigos Inep de aluno, turma e matrícula. Por isso a fase de **retorno dos códigos** (seção 7, fase 6) entra no plano.
- O leiaute 2026 é a referência para construir e testar o motor com dados fictícios. O Educacenso é o validador final: um arquivo só é dado como aceito depois de importado lá, numa janela de coleta.

## 2. Autenticidade e integridade dos arquivos recebidos

Os arquivos foram baixados pela VPS de desenvolvimento, contornando um erro de certificado do servidor do Inep. A conferência foi feita assim:

| Conferência | Resultado |
|---|---|
| Nova cópia pela internet | **Confirmada em 24/11/2026.** O servidor do Inep não envia o certificado intermediário, por isso a conexão falha com verificação ligada. A nova cópia foi baixada **com a verificação de certificado ativa**: o intermediário indicado no próprio certificado do servidor (RNP ICPEdu GR46 OV TLS CA 2025, emitido pela raiz GlobalSign Root R46, já confiável no sistema) foi buscado e acrescentado à cadeia. O leiaute e o RAR baixados têm exatamente as somas SHA-256 abaixo; o servidor informa última modificação em 09/07/2026 para os dois. |
| Integridade do RAR | Os 22 arquivos extraídos têm o CRC-32 igual ao registrado no próprio RAR. O tamanho físico confere, sem truncamento. Uma das ferramentas (unar) falhou em 3 arquivos por limitação dela; o bsdtar extraiu os 22 sem erro. |
| Conteúdo ativo | Nenhuma macro, nenhum vínculo externo e nenhum objeto embutido na planilha do leiaute (só configurações de impressão). |
| Metadados internos | Criador "inep"; última edição em 01/07/2026 por servidor da equipe do Censo, o mesmo editor final das 21 tabelas, todas datadas de maio a junho de 2026. |
| Coerência com fontes públicas independentes | As datas de referência do leiaute (início do ano letivo até 27/05/2026; término a partir de 28/05/2026) conferem com a data de referência oficial de 27/05/2026 (Portaria Inep nº 217/2026). A tabela de municípios tem 5.571 municípios com os códigos do IBGE (por exemplo, São Paulo 3550308 e Santa Isabel/SP 3546801). O código 76 para o Brasil segue o padrão ISO usado pelo Inep. |
| Coerência interna | O histórico de versões V1 a V5 da própria planilha bate com os campos. A contagem de campos de cada registro bate com as regras gerais. |
| PDF de instruções | Sem formulário nem JavaScript. Gerado pelo Word em 03/08/2026; o próprio texto diz "publicada on-line em junho de 2026", o que indica revisão posterior sem mudar o ano. A equipe técnica listada inclui os mesmos servidores que aparecem como editores da planilha e das tabelas. Os contatos são do domínio inep.gov.br. Os parágrafos centrais têm o mesmo texto da versão de 2025 publicada pelo Inep. |
| Implementação independente | O i-Educar, sistema de gestão escolar de código aberto usado por redes municipais, foi atualizado entre maio e junho de 2026 para o Censo 2026. Ele usa a data de referência 27/05/2026 e implementa exatamente as novidades desta planilha: o campo 56 do registro 10 (laboratório de robótica, V3), o registro 30 com 110 campos e o campo 9 do registro 60 (carga horária integralizada, V5). Foi só consultado: nenhum código dele foi copiado, porque a licença é GPL. |

**Conclusão:** autenticidade confirmada. A cópia em uso é idêntica, byte a byte, à publicada hoje em download.inep.gov.br, obtida por conexão com certificado verificado. O PDF de instruções não foi baixado de novo; para ele valem as conferências da tabela.

**Versão do leiaute:** é a V5. A aba "atualizações da versão" registra de v1 a V5, e o arquivo publicado hoje é o mesmo. Um fornecedor (Qualidata, notas de 05/10/2026) cita uma "v6", mas o Inep não publicou nenhum arquivo com essa versão no endereço oficial até 24/11/2026. Se aparecer, comparar campo a campo antes de usar.

| Arquivo | SHA-256 |
|---|---|
| `layout_de_importacao_e_exportacao_2026.xlsx` | `68abca006f808d8c7d0ae405e350de7b77e3c70385f329cf4b8e7ff597296a09` |
| `tabelas_auxiliares_2026.rar` | `ca7d1c033dfdebf47f1dc61cc25c3c9fdf4ee227189dd3257d72ae8118e46ac0` |
| `instrucoes_da_migracao_censo_escolar_1_etapa_2026.pdf` | `efdf88b968da214b79cc7396ebcd91703e1c10efaaab995f0e3f16d181529e22` |

As somas de cada tabela estão em `resources/educacenso/2026/ORIGEM.md`.

## 3. Como o leiaute 2026 está organizado

Extraído integralmente para `resources/educacenso/2026/leiaute.json` pelo script `scripts/educacenso/extrair-leiaute.py`, que recusa a planilha se as contagens não baterem. O teste `LeiauteOficialTest` protege o arquivo.

| Registro | Conteúdo | Campos | Regras | Quantos por arquivo |
|---|---|---|---|---|
| 00 | Identificação da escola | 53 | 108 | um por escola |
| 10 | Caracterização e infraestrutura | 187 | 230 | um por escola |
| 20 | Turma | 66 | 155 | até 1.500 por escola |
| 30 | Pessoa física (aluno, profissional, gestor) | 110 | 150 | um por pessoa na escola |
| 40 | Vínculo de gestor | 7 | 10 | até 3 por escola |
| 50 | Vínculo de profissional em turma | 38 | 51 | um por pessoa e turma |
| 60 | Vínculo de aluno (matrícula em turma) | 33 | 41 | um por aluno e turma |

São 745 regras de campo (677 conferidas na validação do arquivo e 63 no processamento contra a base do Inep) e 42 regras gerais.

**Regras gerais que o gerador tem de cumprir (todas recusam a escola ou o arquivo):**

- **Arquivo:**
  - codificação ISO-8859-1;
  - extensão `.txt`, até 20 MB;
  - nome com até 20 caracteres, só letras, números e sublinhado;
  - até 100 escolas por arquivo.
- **Texto:** sem letras minúsculas e sem acentos.
- **Linhas:**
  - cada linha começa com 00, 10, 20, 30, 40, 50, 60 ou 99;
  - o arquivo termina com a linha `99|`.
- **Ordem dos registros:**
  - 00 no início, ou depois de um 40 ou de um 60;
  - 10 depois do 00;
  - 20 depois do 10 ou de outro 20;
  - 30 depois do 00, de um 20 ou de outro 30;
  - 40 depois de 30/40;
  - 50 depois de 40/50;
  - 60 depois de 50/60.
- **Por escola:**
  - um 00 e um 10;
  - toda turma com pelo menos um aluno (60) e um profissional (50);
  - escola paralisada ou extinta só envia 00, 30 e 40.
- **Vínculos (importação parcial se violadas):**
  - sem horários coincidentes, conforme a Tabela de Regras de Horários Coincidentes;
  - no máximo 4 vínculos de escolarização;
  - no máximo 2 vínculos de escolarização fora das etapas 39, 40, 64 e 68;
  - no máximo 4 vínculos de AEE e atividade complementar;
  - uma pessoa só uma vez por turma.
- **Identificação da pessoa no Inep:**
  1. pelo ID Inep;
  2. sem ele, pelo CPF;
  3. sem CPF, pela certidão nova;
  4. sem nenhum dos três, por nome, data de nascimento e filiação. Se houver mais de um resultado ou nenhum, cria um novo ID.

  Por isso **guardar o ID Inep de cada aluno e servidor é essencial** para não duplicar pessoas na base nacional.
- **Descaracterização:** é proibido alterar ao mesmo tempo dois campos do grupo 1 (nome, nascimento, filiação 1 e 2) e um do grupo 2, ou três do grupo 1.

## 4. Formato do arquivo: o que está documentado e o que falta

### 4.1 O que os documentos oficiais definem

| Tema | Definição oficial | Fonte |
|---|---|---|
| Estrutura | Registros 00, 10, 20, 30, 40, 50 e 60 por escola e um 99 no fim do arquivo. Cada registro ocupa uma linha. Um arquivo pode ter várias escolas, até 100. | PDF, 5º passo e Figura 5; leiaute, regras gerais 3 e 11 a 23 |
| Separador de campos | Pipe (`\|`). | PDF, 3º passo (arquivo de identificação); regra geral 22 (`99\|`); Figura 5 |
| Campo vazio, nulo ou não aplicável | Não se preenche nada: dois pipes seguidos (`\|\|`). | PDF, 3º passo |
| Tamanho | Só os campos de tamanho fixo devem ter o tamanho exato. Os demais não recebem espaços nem zeros à esquerda. | PDF, 3º passo; coluna "fixo" do leiaute |
| Codificação | ISO-8859-1. | Regra geral 1 |
| Caracteres | Sem minúsculas e sem acentos; em cada campo, só os caracteres da coluna "valores permitidos". | Regra geral 28; leiaute |
| Datas | `dd/mm/aaaa`. Horários de turma `hh:mm-hh:mm`, minutos múltiplos de 5. | Leiaute |
| Números | Só dígitos. Códigos de tamanho fixo (escola 8, município 7, distrito 2, CPF 11, ID Inep 12) com todos os dígitos. | Leiaute |
| Nome do arquivo | `.txt`, até 20 caracteres (letras sem acento, números e sublinhado), até 20 MB. | Regra geral 2; PDF |
| Envio | **Cada importação substitui todos os dados da escola no Educacenso e não pode ser desfeita.** O arquivo tem de levar a escola completa: uma turma deixada de fora é apagada lá. | PDF, 6º e 7º passos |
| Retorno | O Educacenso devolve um `.zip` com um `.txt` no mesmo leiaute, com os códigos do sistema próprio e os IDs Inep. | PDF, 1º e 2º passos |
| Resultado | "Importado parcialmente" é o resultado mais comum. Arquivos de avisos e erros indicam linha e registro. | PDF, 7º passo |

### 4.2 Divergências e omissões: precisam de confirmação antes de gerar o arquivo

1. **Pipe no fim de cada linha (omissão e divergência).**
   - O texto do PDF não diz se cada registro termina com pipe.
   - O exemplo oficial do arquivo de identificação (Figura 1) termina cada linha com `|`, e a Figura 5 mostra `00|dados...|`.
   - O i-Educar gera as linhas **sem** pipe final (`60|...|último campo`) e só o `99|` com pipe.
   
   As duas formas podem ser aceitas pelo Educacenso, mas isso não está escrito em lugar nenhum.
2. **Quebra de linha (omissão).** Nenhum documento diz se é CRLF (Windows) ou LF. A Figura 1 é um Bloco de Notas do Windows, o que sugere CRLF, sem afirmar. O i-Educar usa LF.
3. **E-mail (divergência interna).** A regra de e-mail do Inep aceita hífen no domínio (`@monte-sereno.gov.br`), mas a coluna de caracteres permitidos (`A-Z 0-9 _ . @`) não. Por enquanto vale a mais restritiva, e um e-mail com hífen vira pendência.
4. **Código da turma (divergência interna, resolvida).** O registro 20, campo 3, diz tamanho 20 **fixo**, mas a regra diz "até 20 caracteres". O Astuti vai gerar códigos com exatamente 20 caracteres, o que atende às duas leituras.
5. **Espaço em campos de texto (omissão, resolvida por evidência oficial).** A coluna de caracteres permitidos não lista o espaço, mas as regras exigem "mais de uma palavra" no nome, e o exemplo oficial usa "JOAO DA SILVA". O espaço é aceito em nomes, endereços e nomes de turma, nunca em códigos.
6. **Forma de organização em turma sem etapa (ambiguidade).** A regra 1 do campo 28 do registro 20 diz "deve ser 1 a 5 quando [Etapa] não for 1, 2 e 3, senão deve ser nulo". Lida ao pé da letra, uma turma de AEE ou de atividade complementar, que não tem etapa, também precisaria informar a forma de organização. O Anexo 6, que define as formas por etapa, não tem linha para turma sem etapa, e a regra de grupo dos campos 39 a 65 trata a etapa nula como "não informa". O Astuti envia nulo nesse caso. Vale incluir na consulta ao Inep.
7. **Outras inconsistências já registradas (resolvidas pela regra mais restritiva):**
   - registro 00, campo 11 (até 10 caracteres);
   - nome do campo 21 do registro 60;
   - numeração do Anexo 2;
   - Anexo 7 no lugar da tabela "Tipo de turma x Etapa";
   - etapas antigas 30 a 34;
   - tabela de deficiência com "2025" no nome.

**Decisão:** o gerador do arquivo (fase 5) só será escrito depois da resposta do Inep aos itens 1 e 2. Contato oficial da migração para SP, conforme o PDF: Henrique Almeida, `henrique.almeida@inep.gov.br`, (61) 2022-3194. O resto do motor (leitura do leiaute, normalização, validação, cadastros e Conferência) não depende disso e segue em frente.

## 5. Arquitetura

O leiaute de cada ano é um **arquivo de dados**, não código.

- **Especificação (pronta neste PR).** `resources/educacenso/{ano}/leiaute.json` e `tabelas/*.csv`, gerados da planilha oficial, com a soma SHA-256 de cada origem.
- **Montagem dos registros.** Uma classe por registro (`Registro00` a `Registro60`) lê os dados do Astuti e devolve a lista de valores na ordem dos campos. Nenhum valor é inventado: campo sem dado vira pendência, nunca um padrão silencioso.
- **Validação em duas camadas.**
  1. **Genérica**, a partir do `leiaute.json`: obrigatoriedade, tamanho, tamanho fixo, tipo (`num`, `alfa`, `selec`, `data`, `hora`, `email`), opções permitidas e caracteres permitidos de cada campo.
  2. **Condicional**, uma regra de código por regra do Inep que dependa de outro campo ou registro (por exemplo "deve ser nulo quando…", Anexos 3 a 8, horários coincidentes). Cada regra implementada leva o número do campo e o número da regra do Inep, para rastrear e atualizar no ano seguinte.

  As regras de processamento, que dependem da base do Inep (CPF na Receita, CEP no DNE, escola existente), ficam como aviso. Só o Educacenso decide sobre elas.
- **Núcleo já implementado** (`app/Educacenso`):
  - `Leiaute` lê o leiaute do ano;
  - `Campo` descreve cada campo como a planilha;
  - `Texto` normaliza (maiúsculas, sem acento, ISO-8859-1);
  - `ValidadorDeCampo` confere obrigatoriedade, tamanho, tipo, opções, data, horário, e-mail e caracteres permitidos.
  
  Testes em `tests/Unit/Educacenso`.
- **Normalização de texto.** Maiúsculas, sem acento e só os caracteres permitidos em cada campo (nomes: só A-Z e espaço). A Conferência mostra o valor exato que vai no arquivo quando ele difere do cadastro, por exemplo "D'ÁVILA" vira "DAVILA". Nada é cortado sem aviso: texto maior que o permitido vira pendência.
- **Gravação.** Codificação ISO-8859-1, lotes de até 100 escolas, cada escola completa. Pipe final e quebra de linha só depois da confirmação do Inep (seção 4.2).
- **Escola completa ou nada.** Como cada envio substitui todos os dados da escola, o Astuti nunca omite uma turma, um aluno ou um profissional com pendência. A escola inteira fica fora do arquivo até a pendência ser resolvida, e a Conferência explica o motivo.
- **Códigos estáveis do sistema próprio.** O Inep devolve, no ano seguinte, o "código da pessoa física no sistema próprio" e o "código da turma na escola". Por isso esses códigos não podem mudar de um ano para outro. Cada pessoa e turma recebe um código próprio do Censo, de 20 caracteres (A-Z e 0-9), gerado uma vez e nunca reaproveitado. Não pode ser o identificador interno do Astuti, que tem 36 caracteres e hífens.
- **Conferência do Censo (já existe).** Passa a ter uma aba "Educacenso" com as pendências por escola, turma, servidor e aluno, cada uma com o link para corrigir. A contagem diz quantas escolas, turmas e matrículas estão prontas. O botão de gerar só libera escolas sem pendência que recuse a escola.
- **Retorno dos códigos.** Importa o arquivo de exportação que o Educacenso devolve (`.zip` com `.txt` no mesmo leiaute) e grava:
  - o ID Inep da pessoa (12 dígitos);
  - o código Inep da turma;
  - o código da matrícula.
  
  A gravação é auditada, confirmando nome e nascimento antes de vincular.

  **Entregue (fase 6):** tela "Retorno do Educacenso" na Conferência do Censo, só para a gestão municipal, com segundo fator recente.
  - Lê o `.txt` (ou o `.zip` com um `.txt`) no leiaute do ano, com ou sem pipe final, em ISO-8859-1 ou UTF-8. Linha com número de campos diferente do leiaute anula o arquivo inteiro.
  - Casa as escolas pelo código Inep, as turmas pelo código próprio (`census_code`) e as pessoas pelo código próprio do campo 3 (`CodigoCenso::dePessoa`), entre os alunos com matrícula na escola e os servidores da escola.
  - Pessoa só é vinculada quando nome e data de nascimento do arquivo conferem com o cadastro. Matrícula só quando o aluno conferiu.
  - Primeiro mostra o que será gravado: novo, já gravado, conflito, divergente ou não encontrado. Grava só depois da confirmação.
  - Código já gravado nunca é trocado: diferente vira conflito. Aluno, turma e matrícula não repetem código na rede. O mesmo ID de servidor pode aparecer em duas escolas, porque o cadastro do servidor é por escola.
  - Tabela `inep_identifiers` (migration 000058). O arquivo não é guardado: entre a conferência e a confirmação, a sessão guarda só os códigos e os identificadores internos.
  - Auditoria `educacenso_return.imported`: soma SHA-256 do arquivo, ano e quantidades, sem nomes nem códigos.
  - Próximo passo: o campo 4 dos registros 30, 40, 50 e 60 passa a sair desta tabela na montagem do arquivo.

## 6. O que o Astuti já tem e o que falta

Legenda: **tem**, já existe e só precisa de conversão; **novo**, precisa de cadastro.

### Escola (registros 00 e 10)

- **Tem:**
  - código Inep;
  - nome;
  - CEP e endereço (#140 e #145);
  - datas do ano letivo (`school_years`);
  - telefone e e-mail da Secretaria (perfil institucional).
- **Novo (00):**
  - situação de funcionamento;
  - código do município e do distrito (escolhido na tabela, sugerido pelo CEP);
  - zona da escola;
  - localização diferenciada;
  - DDD e telefone da própria escola;
  - órgão ao qual está vinculada (campos 22 a 25);
  - parceria ou convênio (33, 34 e, se for o caso, 35 a 46);
  - regulamentação no conselho (49) e esfera (50);
  - unidade vinculada (51 e 52).
  
  Dependência administrativa = 3 (municipal) vem da instalação.
- **Entregue (10):** questionário anual de infraestrutura com 187 campos (185 respostas editáveis; campos 1 e 2 montados pelo sistema):
  - local de funcionamento;
  - água, energia, esgoto e lixo;
  - dependências;
  - acessibilidade;
  - salas;
  - equipamentos;
  - internet;
  - profissionais por função;
  - alimentação;
  - materiais;
  - língua de ensino;
  - colegiados;
  - projeto pedagógico;
  - educação ambiental.
  
  A tela "Infraestrutura no Censo" é gerada a partir do `leiaute.json`, nos 16 grupos oficiais e nos intervalos de campos avulsos. A tabela `school_infrastructure_census` guarda um objeto de respostas por escola e ano. A cópia do ano anterior só sugere valores pelo número do campo, com aviso explícito para conferir rótulos e respostas, pois o leiaute pode mudar. Nenhum dado é gravado antes de salvar, e o histórico anterior não é alterado. Sugestões automáticas a partir do módulo de merenda e do cadastro de servidores ficam para etapa posterior. A regra geral 18 torna o registro 10 não aplicável a escola paralisada ou extinta; o histórico é mantido.

  **Registro 10: regras mantidas como aviso e verificações entre registros ainda pendentes.**

  - Campo 139, regra 2: alimentação escolar oferecida exige pelo menos uma turma (registro 20) com mediação 1 ou 2. O questionário não consulta as turmas; a conferência final da escola completa deverá cruzar os registros. Não é uma recusa local.
  - Campos 11 a 16, regra 2: existência e situação ativa das escolas com as quais há compartilhamento do prédio dependem da base do Inep (processamento). Código distinto e mesma UF são conferidos localmente; a situação da escola indicada fica como aviso.
  - Regra geral 19: a existência de todos os registros exigidos de uma escola ativa deverá ser verificada na fase de montagem integral (registros 20, 30, 40, 50 e 60), não inferida pelo questionário. As regras gerais 12 e 20, de ordem e unicidade do registro no arquivo, também pertencem à fase de geração; a unicidade anual do questionário já é garantida no banco.
  - Regras 2 a 4 do campo 21 do registro 20: local de funcionamento diferenciado deve ser compatível com os campos 3 a 8 do registro 10. São verificações entre registros, a consolidar na conferência integral, sem inventar uma recusa no questionário de infraestrutura.
  - Campo 40: o grupo 37 a 40 exige pelo menos um valor 1, mas a V5 não traz regra de exclusão para "Não faz tratamento", diferentemente de outros grupos. Não se acrescenta recusa não documentada.
  - Campos 161 a 163: a V5 exige código válido quando o campo 160 for 1 ou 3 e nulo nos demais casos, com códigos distintos. Aplica-se a redação literal, inclusive aos campos 162 e 163; se o Inep confirmar que estes são opcionais, ajustar a regra com a fonte oficial. Os códigos da tabela de línguas são formatados com cinco dígitos, conforme o tamanho fixo do leiaute.

  A coluna de prontidão da escola exige registro 00 e registro 10 prontos, respeitando a não aplicabilidade do registro 10. O gerador permanece bloqueado.

### Turma (registro 20)

- **Tem:** nome, turno, capacidade, etapa em texto livre e professores vinculados.
- **Novo:**
  - **código da etapa do Inep** (campo 24) e etapa agregada (23). A Conferência já reconhece a etapa pelo texto: vira sugestão a confirmar, nunca código gravado sem confirmação;
  - tipo de mediação (6);
  - **horário por dia da semana**, `hh:mm-hh:mm` com minutos múltiplos de 5 (7 a 13);
  - tipo de turma (14: curricular, AEE, atividade complementar ou curricular com atividade complementar);
  - atividades complementares (15 a 20, tabela);
  - local diferenciado (21);
  - classe especial (22);
  - forma de organização (28, Anexo 6);
  - alternância (29);
  - **componentes oferecidos** (39 a 65, Tabela de Regras de Áreas do Conhecimento por etapa);
  - classe bilíngue de surdos (66).
  
  Os campos de ensino médio e educação profissional (25 a 27 e 30 a 38) ficam fora do escopo municipal, sempre nulos, com bloqueio se a etapa exigir.
- **Turmas de AEE** passam a existir como turma (tipo 5), com as atividades de AEE de cada aluno (registro 60, campos 10 a 20).

### Servidores (registros 30, 40 e 50)

- **Tem:** nome, função, e-mail, telefone e turmas com disciplina em texto.
- **Novo (30):**
  - **CPF** (obrigatório para gestor e para professor brasileiro);
  - data de nascimento;
  - filiação;
  - sexo;
  - cor/raça;
  - nacionalidade;
  - município de nascimento;
  - deficiência (17 a 28);
  - escolaridade (56 e 57);
  - cursos superiores com código do curso, ano e IES (58 a 66, tabelas);
  - complementação pedagógica (67 a 69);
  - pós-graduações (70 a 88);
  - formação continuada (89 a 109);
  - e-mail do gestor (110).
- **Entregue (tela "Dados do Censo" do servidor, tabela `staff_census`, registro 30 pelo `Registro30Servidor`):**
  - CPF (válido e único na escola), nascimento com idade do Anexo 3 (gestor 18 a 95, em sala 14 a 95), filiação, sexo, cor/raça, povo indígena, nacionalidade, país e município de nascimento conferidos nas tabelas;
  - deficiência (17 a 28), com a mesma restrição de dado de saúde dos alunos: só na tela de edição, auditoria só com nomes de campos;
  - escolaridade (56, 57), até 3 cursos superiores com código do curso, ano e IES (58 a 66, Tabela de Cursos de Formação Superior e Tabela de IES; o código 9999999 "instituição não cadastrada" é aceito, como na tabela);
  - complementação pedagógica (67 a 69, sem 32 e 99), até 6 pós-graduações (70 a 87), "não tem pós-graduação" (88) e formação continuada (89 a 109, nula quando não informada);
  - e-mail do gestor (110) vem do e-mail institucional do cadastro do servidor;
  - pelo Anexo 2, transtornos, recursos de prova e certidão (29 a 50) são só de aluno e vão nulos; a residência (51 a 55) é opcional fora do registro 60 e não é coletada.
  - Leitura literal a confirmar: o campo 13 exige povo indígena com cor/raça indígena, embora o Anexo 2 não marque o campo para gestor e profissional. O Astuti segue a regra do campo.
- **Entregue (40):** bloco "Gestão escolar" na tela Dados do Censo, com cargo, critério de acesso e situação funcional em `staff_manager_census`. Direção e coordenação são elegíveis; o cargo do Inep é confirmado pelo operador.
- **Entregue (50):** tela Atribuição no Censo, tabela `class_teacher_assignment_census`:
  - **função na turma** (1 a 9, conforme a turma);
  - situação funcional;
  - **componentes que leciona** em código (9 a 33). A disciplina em texto vira sugestão, sem seleção automática. Só aparecem os componentes marcados como 1 no registro 20. Os campos 34 a 38 permanecem nulos no escopo municipal.
  - A Conferência de Servidores reúne 30, 40 e todos os 50 vigentes, em consultas por lote. Diretores e coordenadores ativos sem o 40 confirmado aparecem como pendentes, assim como atribuições vigentes ainda sem dados do 50. A referência de vigência é a data atual, como na Conferência de Turmas. Escola paralisada ou extinta não envia 50 (regra geral 18).
  - Auditoria dos vínculos registra identificadores e nomes dos campos, sem conteúdo; segundo fator para acessar e gravar os dados do Censo.

  **Avisos e verificações da montagem integral ainda pendentes:**
  - 40, campos 2 a 4: correspondência com os registros 00 e 30; identificação única do Inep, quando disponível; unicidade do gestor e máximo de três gestores por escola (regra geral 21).
  - 40, campos 6 e 7: se situação ou dependência do 00 ainda não estiverem informadas, avisar e completar o cadastro da escola antes da montagem integral; não supor esses valores.
  - 50, campos 2 a 5: correspondência com 00, 30 e 20; identificação única do Inep, quando disponível; ausência de vínculo simultâneo como aluno (regra geral 30).
  - 50, campo 7, regras 7 e 8: turma não pode ter apenas intérpretes de Libras ou tutores EAD; função 4 exige aluno ou outro profissional com surdez, deficiência auditiva ou surdocegueira. Esses cruzamentos não são recusas locais.
  - 50, campos 7 a 33: quando faltam dados do 20 ou a dependência do 00, a conferência de combinações fica pendente. Com os dados disponíveis, função, mediação, tipo de turma, regime e componentes são conferidos diretamente.
  - Os requisitos globais de existência de registros e de vínculos por turma (regras gerais 19, 26 e 27) e os itinerários fora do escopo municipal ficam para a montagem integral. O gerador segue bloqueado.

### Alunos (registros 30 e 60)

- **Tem:**
  - nome;
  - nascimento;
  - sexo, cor/raça e nacionalidade (com conversão de código);
  - CPF;
  - certidão;
  - CEP;
  - zona de residência;
  - deficiência, TEA e altas habilidades;
  - recursos de prova (parte);
  - situação no transporte e rota.
- **Entregue (tela "Dados do Censo" na ficha do aluno, tabela `student_census`):**
  - **filiação 1 e 2** como dado próprio (campos 8 a 10). O responsável cadastrado como mãe ou pai é só sugestão: filiação é dado de registro civil. Nome informado sozinho, para aluno sem CPF, segue a regra 1 dos campos 9 e 10;
  - município de nascimento em código, só para nacionalidade brasileira (campo 16), achado na Tabela de Municípios pelo nome e UF, sem distinguir acentos; a tela mostra antes de gravar se o nome está na tabela;
  - país de nacionalidade (campo 15): 76 para brasileira e naturalizada; outro país da tabela para estrangeiro;
  - país e município de residência em código (campos 51 e 53);
  - localização diferenciada da residência (campo 55), recusando assentamento com zona urbana;
  - povo indígena (campo 13), só com cor/raça indígena.
- **Novo:**
  - deficiência múltipla (26);
  - **transtornos que impactam a aprendizagem** (29 a 35, dado de saúde novo): **entregue** na tela de educação especial, com a mesma restrição de acesso e a auditoria só de nomes de campos;
  - recursos de prova faltantes (CD com áudio, prova de Língua Portuguesa como segunda língua, prova em vídeo em Libras, material em Braille): **entregues**, com a combinação deficiência ou transtorno x recurso do Anexo 4 conferida ao gravar. "Nenhum" (49) é calculado na montagem do registro;
  - atividades do AEE (60.10 a 60.20): **entregues** na mesma tela, gravadas só quando o aluno frequenta o AEE;
  - certidão nova validada (32 caracteres);
  - escolarização em hospital ou domicílio (60.21): **entregue** na tela "Dados do Censo", gravada na matrícula ativa da escola;
  - poder público do transporte e **tipo de veículo** por rota (60.23 a 33): **entregues** na tela "Transporte no Censo" (tabela `transport_route_census`), uma vez por rota, recusando nenhum veículo ou todos os dez;
  - série do aluno em turma multisseriada (60.8, ligado à #152).

### Código da pessoa no sistema próprio

Registros 30 e 60, campo 3: os 20 primeiros dígitos hexadecimais do identificador interno do aluno, em maiúsculas (`CodigoCenso::dePessoa`). É estável entre os anos sem precisar de coluna nova, e o retorno do Inep casa com o cadastro por ele.

### Leitura literal pendente de confirmação

A tabela do Anexo 4 não traz "Nenhum" para cegueira, surdocegueira e os transtornos de aprendizagem. O montador avisa (não recusa) quando um aluno com essas condições não tem recurso de prova marcado.

### Identificadores Inep

**Novo:** ID Inep de aluno e servidor (12 dígitos), código Inep da turma e código da matrícula, vindos do retorno do Educacenso (seção 5).

## 7. Fases

Cada fase é um PR com testes. Nada vai para produção sem as regras da fase cobertas.

| Fase | Entrega | Quem |
|---|---|---|
| 0 | Leiaute 2026 extraído e protegido por teste, planejamento (este PR) | Claude |
| 1 | Dados de Censo da escola (00) e questionário de infraestrutura (10) gerado do leiaute, com cópia do ano anterior | ChatGPT (tela), revisão Claude |
| 2 | Turma para o Censo: etapa Inep confirmada, horários por dia, tipo, componentes, turmas de AEE e atividade complementar (**entregue**: tela "Turmas no Censo", preenchimento em lote, registro 20 com as regras condicionais e coluna na Conferência do Censo) | Claude |
| 3 | Servidores para o Censo: dados pessoais, formação (tabelas de cursos e IES), gestor, função e componentes por turma | ChatGPT, revisão Claude |
| 4 | Alunos para o Censo: filiação, municípios em código, transtornos, recursos, transporte, hospital/domicílio (**entregue**: telas "Dados do Censo", educação especial ampliada e "Transporte no Censo"; montadores `Registro30Aluno` e `Registro60`, tela "Alunos no Censo" e coluna na Conferência do Censo) | Claude (dados sensíveis) |
| 5 | Regras condicionais, aba Educacenso na Conferência e gerador do arquivo ISO-8859-1 (o validador genérico já está pronto) | Claude; o gerador só após a resposta do Inep (seção 4.2) |
| 6 | Retorno: importação dos códigos Inep do arquivo de exportação (**entregue**: tela "Retorno do Educacenso", tabela `inep_identifiers`) | Claude |
| 7 | Situação do Aluno 2026, assim que o Inep publicar o leiaute da 2ª etapa | Claude |

As fases 1 a 4 já melhoram a Conferência do Censo, porque cada dado novo vira pendência visível, antes mesmo de o gerador existir.

## 8. LGPD

- **Base legal:** cumprimento de obrigação legal. O Censo Escolar é obrigatório (Decreto nº 6.425/2008), e as informações têm uso estatístico com proteção de dados (Portaria Inep nº 217/2026).
- **Dados sensíveis coletados para o Censo:**
  - cor/raça;
  - deficiência, TEA e altas habilidades;
  - transtornos de aprendizagem (novo);
  - povo indígena.

  Ficam com acesso restrito ao perfil que já vê educação especial. A gravação exige segundo fator recente, e a auditoria registra `[REDACTED]`. O CPF de servidor segue a mesma regra do CPF do aluno.
- **Transtornos de aprendizagem:** só o que o Censo pede (sim ou não por tipo). Sem laudo, CID ou texto livre.
- **Geração do arquivo:**
  - só para a gestão municipal com segundo fator recente;
  - o arquivo é montado na hora e entregue no download, **sem ficar gravado no servidor**;
  - a auditoria registra quem gerou, quando, quantas escolas e a soma SHA-256 do arquivo, nunca o conteúdo.
- **Minimização:** só os campos que o leiaute exige para a situação. Campo que o Inep manda deixar nulo sai nulo, mesmo que o Astuti tenha o dado.
- **Retenção:** o retorno do Educacenso grava só os códigos. O arquivo recebido é descartado depois da importação.

## 9. Testes

- **Arquivo-modelo fictício** (Monte Sereno): escola, turmas regulares, multisseriada e de AEE, servidores, gestor e alunos com e sem deficiência. O teste compara byte a byte com um arquivo de referência conferido manualmente contra o leiaute.
- **Um teste por regra condicional implementada**, com o número do campo e da regra do Inep no nome.
- **Bytes:**
  - ISO-8859-1;
  - nenhuma letra minúscula ou acentuada;
  - última linha `99|`;
  - contagem de campos por linha.
- **Desempenho:** número fixo de consultas por escola, como nos demais painéis.
- **Antes do primeiro uso real:** importar o arquivo de uma escola fictícia no Educacenso na janela de 2027, se o Inep permitir, ou começar por uma escola real com acompanhamento da Secretaria.
