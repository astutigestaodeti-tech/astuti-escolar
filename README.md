# Astuti Gestão Escolar

Sistema de gestão escolar para redes públicas municipais, desenvolvido como produto comercial da **Astuti**.

## Objetivo

Reduzir o trabalho de digitação das secretarias escolares. O produto deverá receber planilhas e documentos digitais ou digitalizados (CSV, XLSX, Word, PDF e imagens), extrair dados, apresentar inconsistências e **exigir conferência e validação humana** antes de registrar informações escolares. Também estão previstos acervo histórico, emissão de documentos conferidos, registros de auditoria e apoio às obrigações oficiais.

> **Estado atual:** o projeto ainda está na fundação técnica. A existência do Laravel e da rota `/saude` não significa que cadastros, importações, segregação de municípios, históricos escolares ou obrigações oficiais já estejam implementados. Não usar dados reais de alunos nesta etapa.

## Tecnologias

- Debian em WSL2 para desenvolvimento local; Linux em servidor para produção futura.
- PHP **8.4** no ambiente atualmente testado, Laravel 13 e Composer 2.
- Node.js 24, npm, Vite e Tailwind CSS 4.
- MariaDB para persistência de desenvolvimento; bancos municipais separados são uma decisão arquitetural **ainda não implementada**.
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

O fluxo de revisão usa uma ramificação de desenvolvimento e um pull request. O arquivo `.github/workflows/testes.yml` executa testes PHP, conferência de estilo e compilação da interface. Os testes atuais usam ambiente isolado de demonstração e **não comprovam isolamento entre bancos MariaDB**; essa validação terá testes próprios em incrementos seguintes.

Arquivos com credenciais, configurações locais, backups e bancos de desenvolvimento permanecem fora do controle de versão. Usaremos apenas dados fictícios e sintéticos até que existam proteção de dados, autorização, auditoria e condições operacionais adequadas para um piloto real.

## Próximas etapas

1. Cadastro seguro de municípios, usuários e vínculos autorizados, sem acesso entre redes.
2. Escolas, alunos, turmas e matrículas, com rastreabilidade e regras por município.
3. Cofre privado de documentos, importação de planilhas, revisão humana e prevenção de duplicatas.
4. Expansão progressiva para documentos históricos, rotinas pedagógicas, Educacenso, FUNDEB e obrigações aplicáveis de controle externo.

Nada será enviado automaticamente a órgãos públicos; integrações oficiais dependerão de especificação, homologação e autorização adequadas.
