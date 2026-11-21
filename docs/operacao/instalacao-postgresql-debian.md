# Preparar o PostgreSQL no Debian/WSL (desenvolvimento)

Este guia usa somente o município **fictício** Monte Sereno v2. Não execute os comandos em um servidor de produção e não forneça dados reais de alunos. O MariaDB antigo permanece intacto enquanto a migração é conferida.

## Pré-requisitos

- Debian no WSL2, PHP **8.4.1+** com extensões `pdo_pgsql`, `mbstring`, `xml` e `pgsql`; Composer e Node conforme o `package.json`.
- **PostgreSQL 17 ou superior, obrigatório**: os testes e a conferência de ICU usam `pg_database.datlocale`, disponível a partir da versão 17. Debian 12 costuma fornecer PostgreSQL 15, enquanto Debian 13 fornece PostgreSQL 17; confirme a versão do servidor com `sudo -u postgres psql -c 'SHOW server_version'` e instale a versão 17+ antes de criar os bancos.
- PostgreSQL com serviço ativo: `sudo apt update`, `sudo apt install postgresql postgresql-client php8.4-pgsql`, `sudo service postgresql start`. O nome do pacote PHP pode mudar conforme a versão de seu repositório.
- Acesso local somente a `127.0.0.1:5432`. Confirme com `pg_isready -h 127.0.0.1`.
- Uma cópia de segurança das duas bases MariaDB e confirmação de que o backup pode ser lido. Nunca use `migrate:fresh` nem apague a origem.

## Criar usuário e bases descartáveis de desenvolvimento

Abra `sudo -u postgres psql`:

```sql
CREATE ROLE astuti_app LOGIN;
\password astuti_app
CREATE DATABASE astuti_platform_dev OWNER astuti_app TEMPLATE template0 LOCALE_PROVIDER icu ICU_LOCALE 'pt-BR';
CREATE DATABASE astuti_monte_sereno_dev OWNER astuti_app TEMPLATE template0 LOCALE_PROVIDER icu ICU_LOCALE 'pt-BR';
\q
```

O comando `\password` solicita a senha sem colocá-la no histórico do terminal. No WSL, se `postgresql` já estiver instalado, não reinstale: verifique o serviço antes.

**A ordenação ICU pt-BR é definida ao criar o banco, não pela migration.** Os dois bancos novos precisam usar `TEMPLATE template0`, `LOCALE_PROVIDER icu` e `ICU_LOCALE 'pt-BR'`. Confira antes de importar dados:

```sql
SELECT datname, datlocprovider, datlocale
FROM pg_database
WHERE datname IN ('astuti_platform_dev', 'astuti_monte_sereno_dev');
```

Espere `datlocprovider = i` e `datlocale = pt-BR` para ambos. **Não é possível trocar a ordenação padrão de um banco já criado por uma migration.** Se já existirem bancos `*_dev`, pare e planeje uma restauração supervisionada em bancos novos, após backup verificado. Não apague, renomeie ou altere o PostgreSQL original da porta 5432 por causa deste ajuste.

No diretório `~/astuti-escolar`, configure seu `.env` local, **sem enviá-lo ao GitHub**:

```dotenv
APP_ENV=local
APP_DEBUG=true
DB_CONNECTION=platform
PLATFORM_DB_HOST=127.0.0.1
PLATFORM_DB_PORT=5432
PLATFORM_DB_DATABASE=astuti_platform_dev
PLATFORM_DB_USERNAME=astuti_app
PLATFORM_DB_PASSWORD=SUA_SENHA_LOCAL
```

A conexão municipal `monte_sereno` é gerada automaticamente **somente** no ambiente local com esses nomes de banco. Não defina `TENANT_DB_*` no desenvolvimento. Teste as credenciais:

```bash
psql -h 127.0.0.1 -U astuti_app -d astuti_platform_dev -c 'SELECT current_database()'
psql -h 127.0.0.1 -U astuti_app -d astuti_monte_sereno_dev -c 'SELECT current_database()'
php artisan config:clear
```

## Ambiente fictício novo

Em bases PostgreSQL **vazias**, ainda sem dados a copiar:

```bash
composer install
npm ci
npm run build
php artisan migrate --database=platform --path=database/migrations/platform
php artisan astuti:demo-preparar
php artisan serve --host=127.0.0.1 --port=8001
```

Abra `http://127.0.0.1:8001/login`. O comando de demonstração mostra uma credencial gerada **somente quando cria uma conta ou usa `--renovar-senha`**. Guarde-a localmente, sem compartilhar prints. A demo deve exibir **3 escolas, 115 alunos fictícios, ano letivo 2027**.

**Não execute `astuti:demo-preparar` antes da migração de bases MariaDB que contenham dados a preservar.** Fazer isso tornará os destinos não vazios e bloqueará a transferência segura. Confira o procedimento em [migração controlada](migracao-mariadb-postgresql.md).

## Verificações

```bash
php artisan db:show --database=platform
php artisan test
vendor/bin/pint --test
npm run test:ui
```

Os testes de integração exigem bases **separadas** terminadas em `_test` e suas variáveis específicas; nunca reutilize as bases `*_dev` nem credenciais de produção. O CI cria as bases centrais e municipais com ICU `pt-BR` explícito e testa os metadados `datlocprovider` e `datlocale` para recusar falsos positivos. A migration municipal habilita `unaccent` e `pg_trgm`, instala a função `public.f_unaccent(text)` e um índice GIN para busca de alunos sem acento. A função usa o dicionário padrão `unaccent` como regra fixa: se o dicionário for alterado, é necessário reconstruir o índice com `REINDEX INDEX students_full_name_unaccent_trgm_idx` em cada base municipal, sob procedimento supervisionado.

No servidor VPS de Prefeitura, consulte o [guia operacional](vps-prefeitura.md): o usuário do processo web **não** deve ser dono dos bancos, possuir privilégio de TRIGGER ou poder remover auditoria. O usuário `astuti_app` com propriedade dos bancos acima é permitido apenas no notebook de desenvolvimento.
