# Astuti Gestão Escolar

Sistema de gestão escolar para redes públicas municipais, desenvolvido como produto comercial da **Astuti**.

## Objetivo

Reduzir o trabalho de digitação das secretarias escolares. O produto deverá receber planilhas e documentos digitais ou digitalizados (CSV, XLSX, Word, PDF e imagens), extrair dados, apresentar inconsistências e **exigir conferência e validação humana** antes de registrar informações escolares. Também estão previstos acervo histórico, emissão de documentos conferidos, registros de auditoria e apoio às obrigações oficiais.

> **Estado atual:** o projeto ainda está na fundação técnica. A existência do Laravel e da rota `/saude` não significa que cadastros, importações, segregação de municípios, históricos escolares ou obrigações oficiais já estejam implementados. Não usar dados reais de alunos nesta etapa.

## Tecnologias

- Debian em WSL2 para desenvolvimento local; Linux em servidor para produção futura.
- PHP **8.4** no ambiente atualmente testado, Laravel 13 e Composer 2.
- Node.js 24, npm, Vite e Tailwind CSS 4.
- MariaDB para persistência e infraestrutura de conexões separadas por município. O mecanismo interno de troca de bancos existe, mas **a autenticação e a autorização por município/escola ainda não estão prontas**.
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
```

Para iniciar o servidor local:

```bash
php artisan serve --host=127.0.0.1 --port=8001
```

- Página inicial: `http://127.0.0.1:8001/`.
- Verificação pública de aplicação: `http://127.0.0.1:8001/saude`, com resposta `{"status":"ok"}`. A rota não revela versão, credenciais nem dados escolares.

## Qualidade e segurança

O fluxo de revisão usa uma ramificação de desenvolvimento e um pull request. O arquivo `.github/workflows/testes.yml` executa testes PHP, conferência de estilo e compilação da interface. Nos testes de integração, o GitHub cria **três bases MariaDB temporárias**: uma central e duas municipais. O teste alterna A, B, A no mesmo processo e confirma que as escolas ficam separadas. **Esses testes não substituem as futuras verificações de autorização HTTP, usuários e escolas.**

Arquivos com credenciais, configurações locais, backups e bancos de desenvolvimento permanecem fora do controle de versão. Usaremos apenas dados fictícios e sintéticos até que existam proteção de dados, autorização, auditoria e condições operacionais adequadas para um piloto real.


## Isolamento municipal em desenvolvimento

A conexão central (`platform`) é fixa. A conexão de cada município (`tenant`) inicia **bloqueada** e só recebe a configuração que corresponde ao `db_key` previamente cadastrado na base central. As credenciais devem vir da configuração privada do ambiente, nunca da URL nem do repositório. O estado da provisão e o resultado de cada tentativa ficam registrados na base central.

O registro seguro das conexões em `config/tenancy.php` começa vazio intencionalmente. O ambiente de implantação deverá fornecer as configurações autorizadas antes de qualquer provisão. **Não execute migrações ou provisionamento em bases reais nesta fase.**

A suíte completa de integração MariaDB é executada no GitHub Actions. Em máquinas locais, os testes de isolamento são ignorados quando `TENANCY_INTEGRATION=1` não estiver configurado com três bancos exclusivos cujo nome termina em `_test`.

A decisão técnica e as limitações estão em [`docs/arquitetura/isolamento-municipal.md`](docs/arquitetura/isolamento-municipal.md).

## Próximas etapas

1. Autenticação central, vínculos de usuários com municípios e escolas, e testes HTTP contra acesso indevido. O mecanismo de conexão municipal já tem testes de integração, mas ainda não pode ser usado por usuários finais.
2. Escolas, alunos, turmas e matrículas, com rastreabilidade e regras por município.
3. Cofre privado de documentos, importação de planilhas, revisão humana e prevenção de duplicatas.
4. Expansão progressiva para documentos históricos, rotinas pedagógicas, Educacenso, FUNDEB e obrigações aplicáveis de controle externo.

Nada será enviado automaticamente a órgãos públicos; integrações oficiais dependerão de especificação, homologação e autorização adequadas.
