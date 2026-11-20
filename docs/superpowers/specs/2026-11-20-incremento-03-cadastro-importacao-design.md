# Incremento 03: cadastro auditado e importação de alunos

## Objetivo

Permitir que a secretaria escolar cadastre e importe alunos atuais de planilhas Excel (`.xlsx`) ou CSV com o mínimo de redigitação. O sistema nunca cria ou altera registros de alunos por mera leitura do arquivo: uma pessoa autorizada confere e confirma as linhas primeiro.

## Estado existente

- Laravel 13, PHP 8.4, MariaDB com banco central `platform` e banco isolado por município `tenant`.
- `Student` armazena `id`, `full_name`, `birth_date`; `StudentEnrollment` armazena escola, ano letivo, situação e datas. A consulta de alunos por escola já existe, mas não há rota HTTP de gravação.
- O Incremento 02, no PR #3, acrescenta auditoria obrigatória e exclusão lógica. Sua revisão independente e aprovação precedem a execução deste plano.
- `SchoolPolicy::create` ainda bloqueia gravações até a implantação de autenticação em dois fatores (2FA). Esse requisito não pode ser removido apenas para adiantar o cadastro.
- O kit Monte Sereno v2 foi definido como gabarito (3 escolas, 115 alunos, ano letivo 2027), porém os arquivos CSV/XLSX originais não estão versionados no repositório. Não inventar sua estrutura ou conteúdo.

## Abordagens discutidas

1. Cadastro manual isolado: entrega rápida de formulários, mas mantém o principal obstáculo da implantação.
2. Cadastro e importação com prévia, mapeamento, pendências e confirmação humana: **opção escolhida por Jefferson**.
3. OCR de Word/PDF/fotos desde o início: depende do mesmo fluxo de conferência e amplia demais o primeiro incremento; será posterior.

## Decisões funcionais

1. **Identidade municipal:** cada `Student` pertence apenas ao banco de um município. Um identificador escolar externo opcional pode apoiar correspondências futuras, mas nome e data de nascimento não bastam para fusão automática. CPF, laudos, anexos clínicos e outras categorias sensíveis ficam fora desta entrega.
2. **Vínculos:** um aluno pode ter matrículas de anos e escolas distintos. A turma é vínculo opcional da matrícula, e a aplicação valida que turma, escola e ano letivo concordam. Não apagar nem sobrescrever registros históricos ao cadastrar o ano atual.
3. **Permissões e 2FA:** operações de leitura preservam os perfis atuais. Escrita é restrita ao gestor municipal ou à secretaria/direção autorizada para a escola, após desafio 2FA efetivamente concluído. Professores não podem importar ou alterar por padrão. Implantar 2FA TOTP por biblioteca de autenticação mantida, sem cadastro público e sem regressão do login, das sessões ou da auditoria.
4. **Cadastro manual:** formulário de inclusão/edição para os dados mínimos do aluno e matrícula, com validação no servidor, mensagem em português, confirmação e trilha de auditoria. Alterações de matrícula são registradas sem destruir a anterior.
5. **Upload:** aceitar inicialmente `.csv` e `.xlsx`, com conferência de extensão e conteúdo, limite de 10 MiB e 2.000 linhas de dados por importação. Arquivos protegidos nunca são servidos pelo diretório público. Não executar fórmulas de planilhas. Suportar BOM UTF-8, separadores CSV comuns e datas brasileiras e ISO 8601; erros de codificação ficam em pendência, sem adivinhar valores.
6. **Mapeamento:** apresentar cabeçalhos detectados e permitir mapear para `nome`, `data_nascimento`, `codigo_externo`, `escola`, `ano_letivo`, `turma`, `situacao_matricula` e datas acadêmicas que existirem na fonte. O operador escolhe escola/ano por padrão quando a planilha não os informa. Campos desconhecidos não devem virar colunas automaticamente.
7. **Conferência:** gravar importações e linhas apenas em tabelas de preparação no banco municipal. Mostrar totais, valores normalizados, origem, erros e potenciais duplicidades. Cada linha deve ser aprovada ou descartada conscientemente; nunca corrigir sobrenomes, datas ou vínculos por adivinhação. O fluxo deve permitir cancelar todo o lote.
8. **Confirmação:** uma confirmação explícita e autorizada grava apenas linhas aprovadas, em transação no banco municipal, com trilha de auditoria obrigatória. Corrigir ou bloquear disputas de duplicidade antes de efetivar. Reenviar o mesmo lote não produz alunos ou matrículas duplicados.
9. **Privacidade:** logs e mensagens não expõem dados pessoais integrais nem conteúdo bruto das planilhas. Arquivos temporários são removidos após leitura; lotes pendentes expiram em sete dias; preservar apenas o mínimo de metadados necessário à auditoria. Não inserir dados reais de menores em fixtures, demonstração ou repositório.
10. **Interface:** reaproveitar `public/css/astuti.css`, componentes Blade, a pauta azul, margem vermelha, brasão e fontes locais definidos em `docs/design/identidade-visual.md`. As telas devem funcionar desde 320 px com teclado e leitor de tela.

## Entregas e critérios de aceite

- Uma secretaria autorizada consegue criar/editar um aluno, associá-lo a escola, ano e turma, e vê-lo na consulta já existente.
- Um professor, usuário sem vínculo, usuário de outra escola ou município e sessão sem 2FA recebem 403 ou redirecionamento seguro, sem escrita parcial.
- CSV e XLSX válidos produzem prévia; arquivos inválidos, dados inconsistentes e datas impossíveis viram erros claros, sem alterar `students` ou `student_enrollments`.
- O sistema reconhece possível duplicidade sem fundir alunos automaticamente; segunda confirmação do mesmo lote não duplica registros.
- O lote pode ser descartado; apenas linhas aprovadas são gravadas após confirmação explícita; a falta de auditoria aborta a escrita.
- A separação municipal A/B/A permanece íntegra; documentos e dados de um município nunca aparecem nos lotes de outro.
- Com os arquivos oficiais do kit v2 disponíveis, importar suas fontes da pasta de documentos até chegar ao gabarito dos 115 alunos fictícios nas três escolas, com conferência humana e sem redigitação integral. Até lá, testar com fixtures pequenas expressamente inventadas, sem alegar equivalência ao kit.
- A suíte PHP passa duas vezes em bancos temporários, Pint e build passam, testes visuais existentes não regridem.

## Fora do Incremento 03

- OCR de PDF, Word, imagens digitalizadas e reconhecimento de escrita manual.
- Emissão de histórico escolar, upload de laudos e tratamento clínico/AEE.
- Importação automática irreversível, sincronização com sistemas externos ou envio oficial ao Censo Escolar.
- Cadastro completo de responsáveis com documentos e informações sensíveis, que precisará de especificação própria.

## Dependências para iniciar a implementação

1. Revisão e aprovação do PR #3. Não criar a branch de execução sobre alterações ainda contestadas.
2. Integrar 2FA seguro antes de habilitar rotas de escrita em produção.
3. Disponibilizar os arquivos originais do kit Monte Sereno v2, sem dados reais de crianças, para o teste de equivalência com o gabarito.
