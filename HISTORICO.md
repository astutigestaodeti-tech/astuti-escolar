# HISTORICO DE DESENVOLVIMENTO

## 2026-11-22 | Homologação | União segura dos PRs #26 e #27 sobre o #30

- Branch de integração em rascunho reúne o importador de transporte da SME (#26) e o layout compacto de ficha/endereço (#27) sobre o endereço inteligente e seus avisos (#28, #29 e #30). Os PRs originais permanecem abertos e sem rebase ou merge.
- CSS, formulário, ficha e testes de StudentRecordWriteTest foram combinados por união: preservam CEP primeiro, complemento, cidade e UF, âncora de edição, apresentação lado a lado, campos manuais e alertas azuis. Nove arquivos não conflitantes do #26 reaproveitam exatamente os blobs revisados.
- Testes específicos de ficha vazia, isolamento e endereço persistente acompanham a implementação. Pint e suíte PHP/Node/PostgreSQL serão avaliados pelo CI deste PR, sem alterar bancos reais ou notebooks.


## 2026-11-22 | Cadastro manual | Contraste da orientação de CEP

- A dica permanente do CEP usa fonte maior, peso mais forte e texto escuro. A resposta da consulta ganha caixa azul informativa e borda lateral, exibida somente quando houver mensagem.
- Testes de interface verificam os estilos específicos e preservam a atualização de endereço e o aviso de CEP geral. Sem mudanças em controllers, migrações, permissões ou dados.


## 2026-11-22 | Cadastro manual | Explicação para CEP geral do município

- Consulta de CEP sem logradouro e bairro, mas com cidade e UF, informa que é um CEP geral e orienta a preencher os campos restantes. O aviso de cache antigo e a consulta de CEP específico são preservados.
- Testes de interface simulam o CEP geral de Santa Isabel/SP, a consulta específica, cache desatualizado e a manutenção dos campos manuais. Nenhuma migração, controlador ou dado foi alterado.


## 2026-11-21 | Cadastro manual | CEP primeiro e endereço assistido

- CEP passa a abrir o grupo de endereço; consulta ViaCEP pelo servidor, com cache PostgreSQL próprio do município (30 dias), limite de requisições, autorização escolar e segundo fator confirmado.
- Logradouro, bairro, cidade e UF são sugeridos quando disponíveis, sem substituir correções feitas durante a consulta; número, complemento e zona permanecem exclusivamente manuais. Sem rede, o formulário continua utilizável.
- Migração municipal nova adiciona complemento, cidade, estado e cache de CEP, sem alterar migrações antigas, bases originais ou importadores de alunos.
- Criação, edição, ficha e auditoria contemplam os três campos; testes fictícios cobrem retorno, cache, falhas, permissões, criação, edição e interface acessível. O PR depende de Pint e CI para integração.


## 2026-11-21 | Organização acadêmica | Cadastro manual de turmas

- Direção, secretaria e gestão municipal podem criar/editar turmas ativas da escola, com nome/código, etapa, modalidade, turno, vagas opcionais e ano letivo. Professores permanecem somente com consulta.
- As quatro novas rotas usam RequireConfirmedTwoFactor, sem alterar os arquivos de segundo fator; autorização e vínculo escolar são validados em cada ação.
- Índice único parcial novo rejeita nomes repetidos em turmas ativas da mesma escola/ano, inclusive na concorrência; validação em português e versão xmin protegem o formulário.
- Auditoria Eloquent registra antes/depois dentro da transação; botão Nova turma, ação Editar e retorno seguro no layout municipal foram incluídos.
- Testes fictícios cobrem criação, edição, auditoria, nome duplicado, perfis, escola, 2FA, ano letivo e capacidade. Sem alteração em importadores, branches anteriores ou bases reais.


## 2026-11-21 | Cadastro manual | Endereço opcional de alunos

- Migração municipal aditiva inclui logradouro, número, bairro, CEP e zona de residência, todos opcionais, com CHECK PostgreSQL para `urban` ou `rural`.
- Cadastro e edição normalizam CEP de oito dígitos, validam campos em português, preservam valores não enviados e permitem limpar campos intencionalmente.
- Formulário e ficha exibem endereço; a auditoria registra alterações antes/depois sem ampliar permissões escolares.
- Testes fictícios cobrem inclusão, alteração, CEP, zona, CHECK no banco, valores vazios, auditoria e isolamento por escola. Pint e CI são condições da entrega.
- Nenhum importador de planilhas ou OCR, tela de importação, segundo fator, banco original ou ramo de outro PR foi alterado.



## 2026-11-21 | Cadastro manual | Orientacao do formato RA SED

- Criacao e edicao manual de alunos exibem erro especifico ao informar RA fora do padrao SED, e o atributo recebe o nome "RA" nas mensagens da validacao.
- Formulario apresenta dica permanente sobre digitos, hifen, digito verificador e UF, associada ao campo por `aria-describedby` mesmo quando ha erro.
- Testes ficticios cobrem RA incompleto, RA valido nas duas acoes, mensagem de atributo e a ajuda acessivel. Verificar o CI antes de integrar.


## 2026-11-21 | Incremento 03 parte B | Movimentação auditada de matrículas

- Nova branch e PR próprios sobre `feature/cadastro-aluno-gravacao` (PR #7); PRs #6, #7, #8 e #9 não foram modificados.
- Transferência interna fecha a matrícula de origem e cria a matrícula ativa no destino, no mesmo ano letivo e na mesma transação; encerramentos admitem somente saída da rede, abandono ou falecimento, com texto opcional de destino em tabela auxiliar e auditoria no mesmo commit.
- Exclusão por erro de cadastro é lógica e exige justificativa com 15 caracteres, permitida somente à gestão municipal e direção da escola; aluno com outros vínculos permanece cadastrado.
- Formulários de confirmação, segundo fator confirmado nas gravações, versões otimistas por `xmin` (inclusive duas alterações no mesmo segundo), controle por escola e auditoria antes/depois preservados.
- Testes fictícios para permissões, 2FA, datas, concorrência, consulta por unidade e auditoria. Validação final depende de Pint e CI deste PR; nenhuma base real alterada e nenhum merge automático.

## 2026-11-21 | Transporte escolar: CSV conferido, atualização auditada

- Novo importador CSV por escola e ano letivo: UTF-8 e Windows-1252, RA obrigatório, conferência antes de gravar e 2FA para gestões autorizadas.
- Situação de transporte na matrícula (`active`, `not_requested`, `cancelled`), sem criar alunos e sem reativar cancelamentos por importação. Escopo escolar, versão PostgreSQL `xmin`, transação integral e auditoria antes/depois.
- Recibo sem informações pessoais impede replay; CSV descartado após prévia cifrada por uma hora. Testes fictícios cobrem conversão, permissões, atomicidade, cancelamento e concorrência.
- Arquivo original da Secretaria não consta no repositório e ainda exige validação de layout. Sem merge automático, deploy ou alteração do notebook.

## 2026-11-21 | Importação assistida: conferência e gravação no banco municipal

- Novo fluxo de envio de .xlsx por escola e ano letivo, com prévia legível, confirmação expressa de linhas repetidas, autorização escolar e segundo fator.
- O arquivo não é armazenado: a prévia mínima fica cifrada por uma hora no próprio formulário e vinculada à conta/escola. Recibo municipal sem PII impede repetição do lote.
- Gravação de alunos e matrículas com RA e turma em uma transação PostgreSQL; datas de matrícula ausentes não são inventadas. Duplicidade existente, turma inválida ou falha de auditoria desfaz o lote inteiro.
- Testes do kit fictício E01/E02/E03, 115 alunos e matrículas, seis nascimentos pendentes, auditoria, replay, alteração de token e matriz de permissões. Sem merge automático, deploy ou bancos reais.

## 2026-11-21 | Base do importador: RA e turma da matrícula

- Revisados os PRs #5 a #11 e a cadeia de dependências. CI do ajuste de integridade histórica do PR #10 concluído com sucesso; o vínculo externo da matrícula usa RESTRICT, sem exclusão em cascata.
- Em PR próprio sobre o #9, acrescentados RA opcional com índice único sobre forma canônica no município e turma opcional com FK composta escola/ano; cadastro e edição com segundo fator, matrícula explícita, versão segura em gravações no mesmo segundo e auditoria; ficha mostra os campos.
- A migração de dados fictícios compara colunas legadas sem preencher artificialmente as novas; o importador permanece somente em prévia até a futura conferência humana e gravação segura.
- Testes de regressão cobrem duplicidade, isolamento de turmas, 403, 2FA e trilha auditada. O CI e Pint devem estar verdes antes do pedido de revisão; nada foi mesclado nem implantado no notebook.

## 2026-11-21 | PR #5 | Bloco 2: ICU pt-BR e busca sem acento

- Bancos PostgreSQL novos do guia Debian, CI e ensaios isolados criados com ICU `pt-BR` e `template0`, mantendo intocados os bancos originais.
- Testes exigem metadados reais da collation, ordem Á/É correta e busca `joao` encontrando `João` somente na escola autorizada.
- Migration municipal instala `unaccent`, `pg_trgm`, a função imutável `f_unaccent` e índice GIN; a busca mantém escape de curingas e escopo de permissão.
- CI volta a usar somente `vendor/bin/pint --test`, sem rotina de depuração; fluxos de cópia e restauração possuem verificações das opções ICU.
- PR #5 permanece rascunho e sem merge; Bloco 3 de permissões de produção não iniciado. Confirmação dos testes e Pint depende do resultado do CI do commit deste bloco.

## 2026-11-21 | PR #5 | Normalizacao segura de emails no PostgreSQL

- Login, cadastro e importacao ficticia passaram a normalizar emails em minusculas na plataforma.
- Uma migracao PostgreSQL detecta previamente conflitos sem divulgar emails, interrompe a operacao e reverte a transacao; sem conflitos, normaliza as contas existentes e cria indice unico sobre `lower(email)`.
- Testes cobrem login com caixa mista, recusa de duplicidade, conversao e falha segura da migracao e transferencia da conta ficticia sem alterar a origem.
- O fluxo de CI deixou de duplicar testes em pushes de branches que ja possuem PR: as validacoes passam a rodar em `pull_request`.
- `AGENTS.md` registra o novo processo de trabalho por risco e a regra de VPS exclusiva por Prefeitura.
- O PR permanece em rascunho, sem merge; nenhum banco original, dado real nem notebook foi alterado.
- Proximos blocos, na ordem solicitada: ordenacao ICU pt-BR e busca sem acento, permissoes de producao em PR proprio, e limite/auditoria do segundo fator.

Registro unico desta sessao. Testes completos e Pint sao condicionantes de entrega; verificar o status do CI deste commit antes do ensaio.

## 2026-11-21 | PR #13 | Reimportacao de alunos sem bloqueio por cadastro existente

- Previa classifica RA ou nome com data ja existentes como `ja_cadastrado`, com descarte automatico e contagem no recibo, sem reutilizar RA.
- Nome coincidente com nascimento ausente exige confirmar `homonimo` como outra pessoa antes da inclusao.
- Confirmacao revalida os cadastros sob advisory lock e grava de forma transacional somente as linhas aceitas.
- Testes cobrem reenvio da E02 (58 ignorados, zero gravados), E02 com uma aluna nova e homonimo sem data; formatacao e CI exigidos antes da integracao.
