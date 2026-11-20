# Astuti Gestão Escolar

Sistema de gestão escolar para redes públicas municipais, desenvolvido como produto comercial da **Astuti**.

## Objetivo

Reduzir o trabalho de digitação das secretarias escolares. O produto deverá receber planilhas e documentos digitais ou digitalizados (CSV, XLSX, Word, PDF e imagens), extrair dados, apresentar inconsistências e **exigir conferência e validação humana** antes de registrar informações escolares. Também estão previstos acervo histórico, emissão de documentos conferidos, registros de auditoria e apoio às obrigações oficiais.

> **Estado atual:** o projeto ainda está na fundação técnica. A fundação de bancos separados, a leitura autorizada de nomes de escolas e **a demonstração navegável de Monte Sereno** já têm testes. O cadastro real de alunos, a importação efetiva de documentos, o segundo fator de autenticação e as obrigações oficiais ainda não estão implementados. Não usar dados reais de alunos nesta etapa.

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
```

Para iniciar o servidor local:

```bash
php artisan serve --host=127.0.0.1 --port=8001
```

- Página inicial: `http://127.0.0.1:8001/`.
- **Telas demonstrativas de Monte Sereno:** `http://127.0.0.1:8001/demonstracao`. Clique no menu lateral para visitar Visão geral, Escolas, Alunos, Matrículas, Importações, Documentos e Indicadores. A busca de alunos opera apenas sobre cinco nomes inventados.
- A rota `/demonstracao` responde **404 em produção**. As informações, quantidades, escolas e nomes exibidos são sintéticos, existem somente na memória da renderização e não geram registros no banco. A importação de documentos nessa tela é **simulação visual**, sem upload.

- Verificação pública de aplicação: `http://127.0.0.1:8001/saude`, com resposta `{"status":"ok"}`. A rota não revela versão, credenciais nem dados escolares.

## Qualidade e segurança

O fluxo de revisão usa uma ramificação de desenvolvimento e um pull request. O arquivo `.github/workflows/testes.yml` executa testes PHP, conferência de estilo e compilação da interface. Nos testes de integração, o GitHub cria **três bases MariaDB temporárias**: uma central e duas municipais. O teste alterna A, B, A no mesmo processo e confirma que as escolas ficam separadas. A suíte também exercita os testes HTTP de login, escopos e ausência de acesso a municípios ou escolas não autorizados. Ainda faltam 2FA e testes de operações privilegiadas.

Arquivos com credenciais, configurações locais, backups e bancos de desenvolvimento permanecem fora do controle de versão. Usaremos apenas dados fictícios e sintéticos até que existam proteção de dados, autorização, auditoria e condições operacionais adequadas para um piloto real.


## Isolamento municipal em desenvolvimento

A conexão central (`platform`) é fixa. A conexão de cada município (`tenant`) inicia **bloqueada** e só recebe a configuração que corresponde ao `db_key` previamente cadastrado na base central. As credenciais devem vir da configuração privada do ambiente, nunca da URL nem do repositório. O estado da provisão e o resultado de cada tentativa ficam registrados na base central.

O registro seguro das conexões em `config/tenancy.php` começa vazio intencionalmente. O ambiente de implantação deverá fornecer as configurações autorizadas antes de qualquer provisão. **Não execute migrações ou provisionamento em bases reais nesta fase.**

A suíte completa de integração MariaDB é executada no GitHub Actions. Em máquinas locais, os testes de isolamento são ignorados quando `TENANCY_INTEGRATION=1` não estiver configurado com três bancos exclusivos cujo nome termina em `_test`.

As decisões técnicas estão em [`isolamento-municipal.md`](docs/arquitetura/isolamento-municipal.md) e [`autorizacao-escolar.md`](docs/arquitetura/autorizacao-escolar.md).

## Próximas etapas

1. Concluir a autenticação com segundo fator oficial, convites administrativos e auditoria; as rotas escolares atuais são somente de leitura e com dados sintéticos.
2. Escolas, alunos, turmas e matrículas, com rastreabilidade e regras por município.
3. Cofre privado de documentos, importação de planilhas, revisão humana e prevenção de duplicatas.
4. Expansão progressiva para documentos históricos, rotinas pedagógicas, Educacenso, FUNDEB e obrigações aplicáveis de controle externo.

Nada será enviado automaticamente a órgãos públicos; integrações oficiais dependerão de especificação, homologação e autorização adequadas.
