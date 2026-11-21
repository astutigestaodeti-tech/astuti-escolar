#!/usr/bin/env bash
# Abre a interface real contra a COPIA FICTICIA no PostgreSQL isolado 55436.
# Nao altera .env, o PostgreSQL 5432, os backups ou a origem MariaDB.
set -Eeuo pipefail
umask 077

repo="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$repo"

erro() { printf 'ERRO: %s\n' "$*" >&2; exit 1; }
[[ $# -eq 0 ]] || erro 'uso: bash scripts/operacao/testar-login-isolado-postgresql.sh'

cluster='astuti_ens_20261121_112332_22176'
porta='55436'
email='demo.monte-sereno@astuti.invalid'
socket='/var/run/postgresql'

for ferramenta in sudo pg_lsclusters openssl php psql; do
    command -v "$ferramenta" >/dev/null || erro "ferramenta ausente: $ferramenta"
done

clusters="$(pg_lsclusters -h)" || erro 'nao foi possivel consultar os clusters.'
awk '$1 == 17 && $2 == "main" && $3 == 5432 && $4 == "online" { n++ } END { exit n != 1 }' \
    <<< "$clusters" || erro 'PostgreSQL principal nao esta na configuracao esperada; nenhuma alteracao realizada.'
awk -v nome="$cluster" -v porta="$porta" \
    '$1 == 17 && $2 == nome && $3 == porta && $4 == "online" { n++ } END { exit n != 1 }' \
    <<< "$clusters" || erro 'cluster isolado 55436 nao esta ativo; nenhuma alteracao realizada.'

printf '===== CONFERINDO IDENTIDADE E DADOS, APENAS LEITURA =====\n'
sudo -v
principal=(--host="$socket" --port=5432 --username=postgres)
ensaio=(--host="$socket" --port="$porta" --username=postgres)
sudo -u postgres pg_isready "${principal[@]}" -q || erro 'PostgreSQL principal indisponivel.'
sudo -u postgres pg_isready "${ensaio[@]}" -q || erro 'PostgreSQL isolado indisponivel.'

identidade() {
    sudo -u postgres psql "$@" -X -At -v ON_ERROR_STOP=1 -d postgres \
        -c 'SELECT system_identifier FROM pg_control_system();'
}
id_principal="$(identidade "${principal[@]}")" || erro 'identidade principal indisponivel.'
id_ensaio="$(identidade "${ensaio[@]}")" || erro 'identidade isolada indisponivel.'
[[ "$id_principal" =~ ^[0-9]+$ && "$id_ensaio" =~ ^[0-9]+$ && "$id_principal" != "$id_ensaio" ]] || \
    erro 'cluster isolado nao possui identidade fisica distinta; nenhuma escrita permitida.'

consulta() {
    local banco="$1" sql="$2"
    sudo -u postgres psql "${ensaio[@]}" -X -At -F '|' -v ON_ERROR_STOP=1 \
        -d "$banco" -c "$sql"
}

[[ "$(consulta astuti_platform_dev 'SELECT COUNT(*) FROM municipalities;')" == 1 ]] || \
    erro 'municipio ficticio nao identificado.'
[[ "$(consulta astuti_platform_dev 'SELECT COUNT(*) FROM users;')" == 1 ]] || \
    erro 'a copia nao contem exatamente um usuario; nenhuma senha sera alterada.'
[[ "$(consulta astuti_platform_dev "SELECT COUNT(*) FROM users WHERE status = 'active' AND email = '$email';")" == 1 ]] || \
    erro 'conta ficticia de demonstracao nao encontrada ou inativa.'
[[ "$(consulta astuti_platform_dev "SELECT COUNT(*) FROM memberships WHERE user_id IN (SELECT id FROM users WHERE email = '$email') AND status = 'active';")" == 1 ]] || \
    erro 'vinculo institucional da demonstracao ausente ou ambiguo.'
[[ "$(consulta astuti_monte_sereno_dev 'SELECT (SELECT COUNT(*) FROM schools), (SELECT COUNT(*) FROM students), (SELECT COUNT(*) FROM student_enrollments);')" == '3|115|115' ]] || \
    erro 'a copia municipal nao tem as contagens verificadas de 3 escolas e 115 alunos/matriculas.'

if [[ ${ASTUTI_DEMO_PASSWORD+x} ]]; then
    # Opcao explicitamente solicitada para o usuario ficticio LOCAL.
    # Nunca repassar esta variavel ao servidor de desenvolvimento.
    senha_login="$ASTUTI_DEMO_PASSWORD"
    unset ASTUTI_DEMO_PASSWORD
    [[ "$senha_login" =~ ^[A-Za-z0-9]{6,64}$ ]] || \
        erro 'senha de demonstracao deve ter de 6 a 64 caracteres alfanumericos.'
else
    senha_login="$(openssl rand -hex 12)" || erro 'nao foi possivel gerar a senha de login.'
fi
senha_banco="$(openssl rand -hex 24)" || erro 'nao foi possivel gerar a senha PostgreSQL.'
[[ "$senha_banco" =~ ^[a-f0-9]{48}$ && "$senha_login" =~ ^[A-Za-z0-9]{6,64}$ ]] || \
    erro 'senha temporaria invalida.'

# Cache de configuracao dedicado a este processo: nao apaga configuracoes do notebook.
cache_dir="$(mktemp -d)" || erro 'nao foi possivel criar diretorio temporario.'
trap 'rm -rf -- "$cache_dir"' EXIT
export APP_CONFIG_CACHE="$cache_dir/config.php"
export APP_ENV=local
export APP_URL=http://127.0.0.1:8002
export DB_CONNECTION=platform
export PLATFORM_DB_HOST=127.0.0.1
export PLATFORM_DB_PORT="$porta"
export PLATFORM_DB_DATABASE=astuti_platform_dev
export PLATFORM_DB_USERNAME=astuti_ensaio
export PLATFORM_DB_PASSWORD="$senha_banco"
export SESSION_DRIVER=file
export SESSION_COOKIE=astuti_ensaio_55436
export CACHE_STORE=array
export QUEUE_CONNECTION=sync

# Verifica Laravel ANTES de trocar qualquer senha no banco de ensaio.
php -r '
    require "vendor/autoload.php";
    $app = require "bootstrap/app.php";
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $central = config("database.connections.platform", []);
    $municipal = config("tenancy.connections.monte_sereno", []);
    foreach ([[$central, "astuti_platform_dev"], [$municipal, "astuti_monte_sereno_dev"]] as [$c, $nome]) {
        if (($c["driver"] ?? null) !== "pgsql"
            || ($c["host"] ?? null) !== "127.0.0.1"
            || (string) ($c["port"] ?? "") !== "55436"
            || ($c["database"] ?? null) !== $nome
            || ($c["username"] ?? null) !== "astuti_ensaio"
            || !empty($c["url"])) {
            fwrite(STDERR, "ERRO: configuracao Laravel fora do cluster isolado.\n");
            exit(1);
        }
    }
    if (config("app.env") !== "local"
        || config("database.default") !== "platform"
        || config("session.driver") !== "file"
        || config("session.cookie") !== "astuti_ensaio_55436"
        || is_file($app->getCachedConfigPath())) {
        fwrite(STDERR, "ERRO: configuracao de ambiente ou sessao insegura.\n");
        exit(1);
    }
' || erro 'Laravel nao passou pelas verificacoes; nenhuma senha alterada.'

printf 'Identidade fisica distinta confirmada. 1 usuario, 3 escolas e 115 alunos da copia ficticia.\n'
printf '===== PREPARANDO CREDENCIAIS APENAS NO ENSAIO 55436 =====\n'
# Senha chega ao psql via stdin, sem aparecer na lista de processos.
sudo -u postgres psql "${ensaio[@]}" -X -v ON_ERROR_STOP=1 -d postgres --file=- >/dev/null <<< \
    "ALTER ROLE astuti_ensaio PASSWORD '$senha_banco';" || erro 'falha ao configurar a conta PostgreSQL isolada.'

# Verifica as conexoes EFETIVAS antes de trocar a senha do unico usuario ficticio.
# O segundo PHP altera somente a copia da tabela users no banco central 55436.
ASTUTI_LOGIN_TEST_PASSWORD="$senha_login" ASTUTI_LOGIN_TEST_EMAIL="$email" php -r '
    require "vendor/autoload.php";
    $app = require "bootstrap/app.php";
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $municipal = config("tenancy.connections.monte_sereno", []);
    config()->set("database.connections.astuti_login_ensaio_municipal", $municipal);
    foreach ([
        ["platform", "astuti_platform_dev"],
        ["astuti_login_ensaio_municipal", "astuti_monte_sereno_dev"],
    ] as [$conexao, $banco]) {
        $dados = \Illuminate\Support\Facades\DB::connection($conexao)->selectOne(
            "SELECT current_database() AS banco, current_user AS usuario, inet_server_port() AS porta"
        );
        if ($dados->banco !== $banco || $dados->usuario !== "astuti_ensaio" || (int) $dados->porta !== 55436) {
            fwrite(STDERR, "ERRO: conexao efetiva fora do cluster isolado.\n");
            exit(1);
        }
    }
    \Illuminate\Support\Facades\DB::connection("platform")->transaction(function (): void {
        $usuario = \App\Models\User::query()
            ->where("email", getenv("ASTUTI_LOGIN_TEST_EMAIL"))
            ->where("status", "active")
            ->sole();
        $senha = getenv("ASTUTI_LOGIN_TEST_PASSWORD");
        if (!is_string($senha) || !preg_match("/^[A-Za-z0-9]{6,64}$/", $senha)) {
            throw new \RuntimeException("Senha de teste invalida.");
        }
        $usuario->password = \Illuminate\Support\Facades\Hash::make($senha);
        $usuario->saveOrFail();
        if (!\Illuminate\Support\Facades\Hash::check($senha, $usuario->password)) {
            throw new \RuntimeException("Senha de teste nao foi verificada.");
        }
    });
' || erro 'falha na configuracao do login ficticio isolado; nenhum banco original foi alterado.'

# Opcional: aplica migracoes pendentes SOMENTE na copia ficticia 55436, para a
# interface rodar com a estrutura atual do codigo. Uso explicito:
# ASTUTI_ATUALIZAR_ESTRUTURA=1 bash scripts/operacao/testar-login-isolado-postgresql.sh
if [[ "${ASTUTI_ATUALIZAR_ESTRUTURA:-}" == 1 ]]; then
    printf '===== ATUALIZANDO A ESTRUTURA, APENAS NO ENSAIO 55436 =====\n'
    php -r '
        require "vendor/autoload.php";
        $app = require "bootstrap/app.php";
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $conferir = function (string $conexao, string $banco): void {
            $dados = \Illuminate\Support\Facades\DB::connection($conexao)->selectOne(
                "SELECT current_database() AS banco, current_user AS usuario, inet_server_port() AS porta"
            );
            if ($dados->banco !== $banco || $dados->usuario !== "astuti_ensaio" || (int) $dados->porta !== 55436) {
                throw new \RuntimeException("Conexao efetiva fora do cluster isolado.");
            }
        };
        $migrar = function (string $conexao, string $pasta): void {
            $codigo = \Illuminate\Support\Facades\Artisan::call("migrate", [
                "--database" => $conexao,
                "--path" => database_path("migrations/".$pasta),
                "--realpath" => true,
                "--force" => true,
            ]);
            echo \Illuminate\Support\Facades\Artisan::output();
            if ($codigo !== 0) {
                throw new \RuntimeException("Migracao recusada em ".$pasta.".");
            }
        };
        $conferir("platform", "astuti_platform_dev");
        $migrar("platform", "platform");
        $municipio = \App\Models\Platform\Municipality::query()->sole();
        app(\App\Tenancy\TenantContext::class)->runFor($municipio, function () use ($conferir, $migrar): void {
            $conferir("tenant", "astuti_monte_sereno_dev");
            $migrar("tenant", "tenant");
        });
    ' || erro 'falha ao atualizar a estrutura da copia isolada; o PostgreSQL original nao foi alterado.'
fi
unset ASTUTI_ATUALIZAR_ESTRUTURA

printf '\n===== TESTE DA INTERFACE REAL, APENAS NO CLUSTER ISOLADO =====\n'
printf 'Endereco: http://127.0.0.1:8002/login\n'
printf 'E-mail: %s\n' "$email"
printf 'Senha temporaria SOMENTE DO ENSAIO: %s\n' "$senha_login"
printf 'O PostgreSQL original 5432 e o MariaDB nao foram alterados por este comando.\n'
printf 'Deixe este terminal aberto e pressione Ctrl+C para encerrar o servidor.\n\n'

# Uma unica tentativa: falha caso a porta 8002 ja esteja em uso.
php artisan serve --host=127.0.0.1 --port=8002 --tries=1
