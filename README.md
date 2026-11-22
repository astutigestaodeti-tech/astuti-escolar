# Astuti Gestão Escolar

Sistema de gestão escolar para redes públicas municipais, desenvolvido como produto comercial da **Astuti**.

## Objetivo

Reduzir o trabalho de digitação das secretarias escolares. O produto deverá receber planilhas e documentos digitais ou digitalizados (CSV, XLSX, Word, PDF e imagens), extrair dados, apresentar inconsistências e **exigir conferência e validação humana** antes de registrar informações escolares. Também estão previstos acervo histórico, emissão de documentos conferidos, registros de auditoria e apoio às obrigações oficiais.

> **Estado atual:** o projeto segue em desenvolvimento. A fundação de bancos separados, a consulta autorizada de escolas, turmas e alunos e **a demonstração navegável de Monte Sereno** já têm testes. As tabelas de alunos e vínculos escolares existem, mas ainda não há formulário de cadastro real nem importação efetiva de documentos. Segundo fator de autenticação e obrigações oficiais seguem pendentes. Não usar dados reais de alunos nesta etapa.

## Tecnologias

- Debian em WSL2 para desenvolvimento local; Linux em servidor para produção futura.
- PHP **8.4** no ambiente atualmente testado, Laravel 13 e Composer 2.
- Node.js 24, npm, Vite e Tailwind CSS 4.
- MariaDB para persistência e infraestrutura de conexões separadas por município. O mecanismo interno de troca de bancos e as rotas de **consulta escolar restrita** já contam com testes HTTP. O segundo fator e as operações de escrita ainda não foram liberados.
- PHPUnit e Laravel Pint para os testes e o estilo do código.

O `composer.lock` atual contém pacotes que exigem PHP 8.4.1 ou superior; o PHP 8.3 não é suficiente para instalar essas versões travadas.

## Desenvolvimento local

Na pasta do repositório, com PHP, Composer, Node e npm instalados:

```bash
composer install
npm ci
```

Se o arquivo `.env` **ainda não existir**, crie-o com `cp .env.example .env`, configure **somente bancos de desenvolvimento** e execute `php artisan key:generate`. Se o `.env` já estiver configurado no seu Debian, **preserve-o**. Nunca envie `.env`, senhas, bancos ou documentos reais ao GitHub.

Para executar as verificações:

```bash
php artisan test
vendor/bin/pint --test
npm run build
npm run test:ui
```

Para iniciar o servidor local:

```bash
php artisan serve --host=127.0.0.1 --port=8001
```

- Página inicial: `http://127.0.0.1:8001/`.
- **Telas demonstrativas de Monte Sereno:** `http://127.0.0.1:8001/demonstracao`. Clique no menu lateral para visitar Visão geral, Escolas, Alunos, Matrículas, Importações, Documentos e Indicadores. A busca de alunos opera apenas sobre cinco nomes inventados.
- A rota `/demonstracao` responde **404 em produção**. As informações, quantidades, escolas e nomes exibidos são sintéticos, existem somente na memória da renderização e não geram registros no banco. A importação de documentos nessa tela é **simulação visual**, sem upload.

### Testar as telas reais com Monte Sereno fictício

Quando a instalação local usar `APP_ENV=local` e o banco central `astuti_platform_dev` no MariaDB **da própria máquina**, o projeto reconhece automaticamente a base fictícia `astuti_monte_sereno_dev` já criada. Não há acesso automático a outros bancos. O comando abaixo **não cria bancos de dados nem é permitido em produção**.

Com as duas bases locais já existentes e acessíveis pelo usuário configurado no `.env`, execute uma vez:

```bash
php artisan config:clear
php artisan astuti:demo-preparar
```

O comando migra somente os esquemas de desenvolvimento previstos, cria o município fictício Monte Sereno, três escolas, um ano letivo de 2027, três turmas e 115 alunos **totalmente sintéticos**, além de uma conta de teste. Ele mostra um e-mail e uma senha forte aleatória; guarde a senha sem enviá-la a ninguém. Depois, com o servidor iniciado, entre em `http://127.0.0.1:8001/login` e escolha Monte Sereno para acessar escolas, turmas e alunos reais do ambiente de teste.

O cache de tentativas de login usa por padrão a conexão central `platform`, e o preparo cria as tabelas necessárias nesse banco, **preservando as tabelas e o conteúdo que já existirem**; não precisa executar migrações no SQLite padrão. Se já houver uma conta com o e-mail reservado ligada a outra rede, um vínculo inativo ou o município estiver desativado, o comando interrompe a operação para preservar as permissões. Uma falha ao criar a conta ou o vínculo desfaz ambas as alterações.

Executá-lo novamente não duplica as fichas nem redefine a senha. Se perder a senha fictícia, use `php artisan astuti:demo-preparar --renovar-senha`. A operação não exclui nem substitui dados preexistentes. Para esse teste, o MariaDB deve permanecer local e o `.env` nunca deve ser publicado.

**Importante:** esses 115 alunos não são o kit Monte Sereno v2 validado; são dados gerados especificamente para experimentar o funcionamento. Os registros de alunos continuam sem formulário de gravação, CPF ou documentos reais, e o ambiente não está homologado para uso escolar.

- Verificação pública de aplicação: `http://127.0.0.1:8001/saude`, com resposta `{"status":"ok"}`. A rota não revela versão, credenciais nem dados escolares.

## Qualidade e segurança

O fluxo de revisão usa uma ramificação de desenvolvimento e um pull request. O arquivo `.github/workflows/testes.yml` executa testes PHP, conferência de estilo e compilação da interface. Nos testes de integração, o GitHub cria **três bases MariaDB temporárias**: uma central e duas municipais. O teste alterna A, B, A no mesmo processo e confirma que as escolas ficam separadas. A suíte também exercita os testes HTTP de login, escopos e ausência de acesso a municípios ou escolas não autorizados. Ainda faltam 2FA e testes de operações privilegiadas.

Arquivos com credenciais, configurações locais, backups e bancos de desenvolvimento permanecem fora do controle de versão. Usaremos apenas dados fictícios e sintéticos até que existam proteção de dados, autorização, auditoria e condições operacionais adequadas para um piloto real.


## Isolamento municipal em desenvolvimento

A conexão central (`platform`) é fixa. A conexão de cada município (`tenant`) inicia **bloqueada** e só recebe a configuração que corresponde ao `db_key` previamente cadastrado na base central. As credenciais devem vir da configuração privada do ambiente, nunca da URL nem do repositório. O estado da provisão e o resultado de cada tentativa ficam registrados na base central.

O registro seguro das conexões em `config/tenancy.php` começa vazio intencionalmente. O ambiente de implantação deverá fornecer as configurações autorizadas antes de qualquer provisão. **Não execute migrações ou provisionamento em bases reais nesta fase.**

A suíte completa de integração MariaDB é executada no GitHub Actions. Em máquinas locais, os testes de isolamento são ignorados quando `TENANCY_INTEGRATION=1` não estiver configurado com três bancos exclusivos cujo nome termina em `_test`.

As decisões técnicas estão em [`isolamento-municipal.md`](docs/arquitetura/isolamento-municipal.md) e [`autorizacao-escolar.md`](docs/arquitetura/autorizacao-escolar.md).

## Organização acadêmica em desenvolvimento

Os bancos individuais dos municípios agora possuem tabelas para **ano letivo** (`school_years`) e **turmas** (`school_classes`), ambas com identificação própria. Uma turma deve referenciar uma escola e um ano existentes **no mesmo banco municipal**. A exclusão física de escola ou ano com turmas associadas é impedida por chave estrangeira.

A rota protegida `GET /m/{municipio}/escolas/{escola}/turmas` exibe apenas turmas ativas de escolas para as quais a conta tem acesso. Ela faz parte da versão de desenvolvimento, não da demonstração pública. Por enquanto **não há formulário de gravação**: a inclusão, alteração e exclusão dependerão de autenticação reforçada, validação de datas e auditoria, ainda pendentes.

## Consulta de alunos em desenvolvimento

Cada banco municipal possui agora duas tabelas específicas: `students`, que identifica o aluno, e `student_enrollments`, que registra seus vínculos com escolas e anos letivos. Um mesmo aluno pode ter vínculos diferentes ao longo da vida escolar sem duplicar o cadastro. Não existe movimentação de alunos entre bancos municipais por essas tabelas.

A rota protegida `GET /m/{municipio}/escolas/{escola}/alunos`, acessível pela ficha da escola, apresenta **somente alunos com vínculo ativo naquela escola**. A consulta exige login, valida o município e as permissões da escola, oferece busca literal por nome e paginação de 25 resultados. Vínculos múltiplos do mesmo aluno não duplicam a listagem. **Não há rota pública de criação, edição ou exclusão de alunos.**

A consulta real depende de um município já provisionado e uma conta autorizada na instalação de desenvolvimento. A demonstração em `/demonstracao` continua independente e acessível apenas em `local` ou `testing`.

## Próximas etapas

1. Concluir a autenticação com segundo fator oficial, convites administrativos e auditoria; as rotas escolares atuais são somente de leitura e com dados sintéticos.
2. Concluir os fluxos de cadastro de alunos e matrículas, com rastreabilidade, validação e regras por município; suas tabelas e consultas já estão em desenvolvimento.
3. Cofre privado de documentos, importação de planilhas, revisão humana e prevenção de duplicatas.
4. Expansão progressiva para documentos históricos, rotinas pedagógicas, Educacenso, FUNDEB e obrigações aplicáveis de controle externo.

Nada será enviado automaticamente a órgãos públicos; integrações oficiais dependerão de especificação, homologação e autorização adequadas.
