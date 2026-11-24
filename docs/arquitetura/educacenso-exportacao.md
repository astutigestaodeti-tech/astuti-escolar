# Exportação para o Educacenso (Matrícula Inicial)

Planejamento aprovado para implementação em fases. Base: **leiaute oficial de importação e exportação da Matrícula Inicial 2026 (versão V5)** e **tabelas auxiliares 2026** do Inep. Nenhum campo, código ou regra deste documento vem de leiaute de outro ano.

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
| Nova cópia pela internet | **Não foi possível.** O servidor de downloads do Inep também abriu com erro no Chrome do Jefferson, e o Inep não publica somas SHA-256 desses arquivos. |
| Integridade do RAR | Os 22 arquivos extraídos têm o CRC-32 igual ao registrado no próprio RAR. O tamanho físico confere, sem truncamento. Uma das ferramentas (unar) falhou em 3 arquivos por limitação dela; o bsdtar extraiu os 22 sem erro. |
| Conteúdo ativo | Nenhuma macro, nenhum vínculo externo e nenhum objeto embutido na planilha do leiaute (só configurações de impressão). |
| Metadados internos | Criador "inep"; última edição em 01/07/2026 por servidor da equipe do Censo, o mesmo editor final das 21 tabelas, todas datadas de maio a junho de 2026. |
| Coerência com fontes públicas independentes | As datas de referência do leiaute (início do ano letivo até 27/05/2026; término a partir de 28/05/2026) conferem com a data de referência oficial de 27/05/2026 (Portaria Inep nº 217/2026). A tabela de municípios tem 5.571 municípios com os códigos do IBGE (por exemplo, São Paulo 3550308 e Santa Isabel/SP 3546801). O código 76 para o Brasil segue o padrão ISO usado pelo Inep. |
| Coerência interna | O histórico de versões V1 a V5 da própria planilha bate com os campos. A contagem de campos de cada registro bate com as regras gerais. |

**Conclusão:** não há sinal de adulteração, e o material é consistente com o publicado pelo Inep. **Risco residual baixo.** A prova definitiva continua pendente porque o Inep não publica somas e o servidor deles está com o certificado inválido. Quando o certificado for corrigido, baixar de novo e comparar com as somas abaixo:

| Arquivo | SHA-256 |
|---|---|
| `layout_de_importacao_e_exportacao_2026.xlsx` | `68abca006f808d8c7d0ae405e350de7b77e3c70385f329cf4b8e7ff597296a09` |
| `tabelas_auxiliares_2026.rar` | `ca7d1c033dfdebf47f1dc61cc25c3c9fdf4ee227189dd3257d72ae8118e46ac0` |

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

## 4. Pontos em aberto: não presumir

1. **Formato da linha.** A planilha fala do pipe só na regra do `99|`. Ela não diz se cada registro termina com pipe, nem como se representa o campo vazio, nem a quebra de linha (CRLF ou LF). Isso está no documento oficial "Etapas e Instruções Gerais para a Migração 2026" (PDF na mesma página do Inep), que ainda não temos. **O gerador não será escrito antes de obter esse PDF.** Pedido: baixar `instrucoes_da_migracao_censo_escolar_1_etapa_2026.pdf` pela VPS, como foi feito com a planilha.
2. **Inconsistências no próprio material do Inep.** Quando houver conflito, segue a regra mais restritiva do leiaute e fica registrado:
   - Registro 00, campo 11 (Número): tamanho 100 na coluna, mas a regra diz "até 10 caracteres". Seguir 10.
   - Registro 60, campo 21: a aba de versões diz que mudou de nome para "Recebe atendimento educacional em regime hospitalar ou domiciliar", mas a aba do registro mantém o nome antigo. As opções (1, 2, 3) são as mesmas.
   - Anexo 2 ainda numera os campos do registro 30 até 108, mas o registro tem 110. Vale o registro.
   - A tabela auxiliar "Tipo de turma x Etapa" difere do Anexo 7, por exemplo em EAD no Ensino Médio. A V4 do leiaute diz que vale o Anexo 7, que é idêntico ao arquivo "Tabela de mediação didático-pedagógica" (o mais recente). Vale o Anexo 7.
   - A tabela de etapas ainda lista as antigas etapas 30 a 34 (técnico integrado). O registro 20 manda usar as etapas agregadas 304/305 em vez delas. Para rede municipal isso não ocorre.
   - O arquivo "Tabela de Deficiência x Recurso" tem "2025" no nome dentro do pacote 2026. Vale o Anexo 4 do leiaute.

## 5. Arquitetura

O leiaute de cada ano é um **arquivo de dados**, não código.

- **Especificação (pronta neste PR).** `resources/educacenso/{ano}/leiaute.json` e `tabelas/*.csv`, gerados da planilha oficial, com a soma SHA-256 de cada origem.
- **Montagem dos registros.** Uma classe por registro (`Registro00` a `Registro60`) lê os dados do Astuti e devolve a lista de valores na ordem dos campos. Nenhum valor é inventado: campo sem dado vira pendência, nunca um padrão silencioso.
- **Validação em duas camadas.**
  1. **Genérica**, a partir do `leiaute.json`: obrigatoriedade, tamanho, tamanho fixo, tipo (`num`, `alfa`, `selec`, `data`, `hora`, `email`), opções permitidas e caracteres permitidos de cada campo.
  2. **Condicional**, uma regra de código por regra do Inep que dependa de outro campo ou registro (por exemplo "deve ser nulo quando…", Anexos 3 a 8, horários coincidentes). Cada regra implementada leva o número do campo e o número da regra do Inep, para rastrear e atualizar no ano seguinte.

  As regras de processamento, que dependem da base do Inep (CPF na Receita, CEP no DNE, escola existente), ficam como aviso. Só o Educacenso decide sobre elas.
- **Normalização de texto.** Maiúsculas, sem acento e só os caracteres permitidos em cada campo (nomes: só A-Z e espaço). A Conferência mostra o valor exato que vai no arquivo quando ele difere do cadastro, por exemplo "D'ÁVILA" vira "DAVILA". Nada é cortado sem aviso: texto maior que o permitido vira pendência.
- **Gravação.** Codificação ISO-8859-1. Formato de linha conforme o PDF de instruções (seção 4, item 1). Lotes de até 100 escolas.
- **Conferência do Censo (já existe).** Passa a ter uma aba "Educacenso" com as pendências por escola, turma, servidor e aluno, cada uma com o link para corrigir. A contagem diz quantas escolas, turmas e matrículas estão prontas. O botão de gerar só libera escolas sem pendência que recuse a escola.
- **Retorno dos códigos.** Importa o arquivo de exportação que o Educacenso devolve (mesmo leiaute) e grava:
  - o ID Inep da pessoa (12 dígitos);
  - o código Inep da turma;
  - o código da matrícula.
  
  A gravação é auditada, confirmando nome e nascimento antes de vincular.

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
- **Novo (10):** questionário anual de infraestrutura com 187 campos:
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
  
  A tela é gerada a partir do `leiaute.json`, em blocos, com as regras de grupo ("pelo menos um", "nenhum dos itens") já na tela. O questionário do ano anterior serve de ponto de partida, sempre com confirmação. Alguns campos têm sugestão vinda do Astuti, sem preenchimento automático: "alimentação escolar" vem do módulo de merenda, e a contagem de profissionais por função vem do cadastro de servidores.

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
- **Novo (40):** cargo (diretor ou outro), critério de acesso ao cargo e situação funcional.
- **Novo (50):**
  - **função na turma** (1 a 9, conforme a turma);
  - situação funcional;
  - **componentes que leciona** em código (9 a 33). A disciplina em texto vira sugestão.

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
- **Novo:**
  - **filiação 1 e 2** como dado próprio (campos 8 a 10). O responsável cadastrado como mãe ou pai é só sugestão: filiação é dado de registro civil;
  - município de nascimento em código (hoje cidade e UF em texto: busca na tabela com confirmação);
  - país de nacionalidade para estrangeiro;
  - município de residência em código;
  - localização diferenciada da residência;
  - povo indígena (se cor/raça indígena);
  - deficiência múltipla (26);
  - **transtornos que impactam a aprendizagem** (29 a 35, dado de saúde novo);
  - recursos de prova faltantes: CD com áudio, prova de Língua Portuguesa como segunda língua, prova em vídeo em Libras, material em Braille, "nenhum";
  - certidão nova validada (32 caracteres);
  - escolarização em hospital ou domicílio (60.21);
  - poder público do transporte e **tipo de veículo** por rota (60.23 a 33);
  - série do aluno em turma multisseriada (60.8, ligado à #152).

### Identificadores Inep

**Novo:** ID Inep de aluno e servidor (12 dígitos), código Inep da turma e código da matrícula, vindos do retorno do Educacenso (seção 5).

## 7. Fases

Cada fase é um PR com testes. Nada vai para produção sem as regras da fase cobertas.

| Fase | Entrega | Quem |
|---|---|---|
| 0 | Leiaute 2026 extraído e protegido por teste, planejamento (este PR) | Claude |
| 1 | Dados de Censo da escola (00) e questionário de infraestrutura (10) gerado do leiaute, com cópia do ano anterior | ChatGPT (tela), revisão Claude |
| 2 | Turma para o Censo: etapa Inep confirmada, horários por dia, tipo, componentes, turmas de AEE e atividade complementar | Claude |
| 3 | Servidores para o Censo: dados pessoais, formação (tabelas de cursos e IES), gestor, função e componentes por turma | ChatGPT, revisão Claude |
| 4 | Alunos para o Censo: filiação, municípios em código, transtornos, recursos, transporte, hospital/domicílio | Claude (dados sensíveis) |
| 5 | Gerador, validador genérico e condicional, aba Educacenso na Conferência, arquivo ISO-8859-1 | Claude, após o PDF de instruções |
| 6 | Retorno: importação dos códigos Inep do arquivo de exportação | Claude |
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
