#!/usr/bin/env bash
# Transfere a demo ficticia MariaDB para um cluster PG NOVO, isolado.
# NUNCA executa transferencia nos bancos PostgreSQL originais na porta 5432.
set -Eeuo pipefail
umask 077

repo="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$repo"

erro() { printf 'ERRO: %s\n' "$*" >&2; exit 1; }

if [[ $# -eq 0 ]]; then
    raiz="$HOME/astuti-backups"
    [[ -d "$raiz" ]] || erro 'pasta de backups inexistente.'
    indice="$(mktemp)"
    trap 'rm -f -- "$indice"' EXIT
    find "$raiz" -mindepth 1 -maxdepth 1 -type d -name 'pgsql-pre-copia-*' -print0 > "$indice"
    mapfile -d '' pastas < "$indice"
    rm -f -- "$indice"
    trap - EXIT
    [[ ${#pastas[@]} -eq 1 ]] || erro 'esperada uma pasta pgsql-pre-copia-*; informe --backup-dir PASTA se houver mais de uma.'
    origem="${pastas[0]}"
elif [[ $# -eq 2 && "$1" == '--backup-dir' && -n "$2" ]]; then
    origem="$2"
else
    erro 'uso: bash scripts/operacao/ensaiar-copia-isolada-postgresql.sh [--backup-dir PASTA]'
fi

for nome in astuti_platform_dev astuti_monte_sereno_dev; do
    [[ -s "$origem/$nome.dump" ]] || erro "backup ausente ou vazio: $nome.dump"
    pg_restore --list "$origem/$nome.dump" >/dev/null || erro "dump PostgreSQL invalido: $nome.dump"
done
[[ -s "$origem/SHA256SUMS" ]] || erro 'manifesto SHA256SUMS ausente.'
[[ "$(wc -l < "$origem/SHA256SUMS")" -eq 2 ]] || erro 'manifesto deve conter exatamente os dois dumps.'
for nome in astuti_platform_dev astuti_monte_sereno_dev; do
    grep -Eq "^[[:xdigit:]]{64}  ${nome}[.]dump$" "$origem/SHA256SUMS" || \
        erro "dump ausente do manifesto: $nome.dump"
done
(
    cd "$origem"
    sha256sum -c SHA256SUMS
) || erro 'checksum de backup invalido.'

for ferramenta in sudo pg_createcluster pg_conftool pg_ctlcluster pg_lsclusters openssl php; do
    command -v "$ferramenta" >/dev/null || erro "ferramenta ausente: $ferramenta"
done

printf '\n===== CONFERINDO BANCOS ORIGINAIS, SOMENTE LEITURA =====\n'
sudo -v
conexao_original=(--host=/var/run/postgresql --port=5432 --username=postgres)
sudo -u postgres pg_isready "${conexao_original[@]}" -q || erro 'PostgreSQL original indisponivel.'

id_original="$(sudo -u postgres psql "${conexao_original[@]}" -X -At -v ON_ERROR_STOP=1 -d postgres \
    -c 'SELECT system_identifier FROM pg_control_system();')"
[[ "$id_original" =~ ^[0-9]+$ ]] || erro 'nao foi possivel verificar a identidade do PostgreSQL original.'

contar_original() {
    local banco="$1" consulta="$2"
    sudo -u postgres psql "${conexao_original[@]}" -X -At -v ON_ERROR_STOP=1 \
        -d "$banco" -c "$consulta"
}

total_central="$(contar_original astuti_platform_dev 'SELECT
    (SELECT count(*) FROM municipalities) + (SELECT count(*) FROM users) +
    (SELECT count(*) FROM municipality_provision_attempts) + (SELECT count(*) FROM memberships) +
    (SELECT count(*) FROM school_scopes) + (SELECT count(*) FROM platform_audit_logs);')"
total_municipal="$(contar_original astuti_monte_sereno_dev 'SELECT
    (SELECT count(*) FROM schools) + (SELECT count(*) FROM school_years) +
    (SELECT count(*) FROM students) + (SELECT count(*) FROM school_classes) +
    (SELECT count(*) FROM student_enrollments) + (SELECT count(*) FROM audit_logs);')"
[[ "$total_central" == 0 && "$total_municipal" == 0 ]] || erro 'os bancos PostgreSQL originais deixaram de estar vazios.'

# A porta escolhida nao pode pertencer nem mesmo a um cluster parado.
clusters="$(pg_lsclusters -h)" || erro 'nao foi possivel listar clusters PostgreSQL.'
porta=''
for candidata in $(seq 55432 55531); do
    if awk -v p="$candidata" '$3 == p { encontrado=1 } END { exit !encontrado }' <<< "$clusters"; then
        continue
    fi
    if sudo -u postgres pg_isready --host=/var/run/postgresql --port="$candidata" --username=postgres -q; then
        continue
    else
        resposta=$?
        [[ $resposta -eq 2 ]] || continue
    fi
    porta="$candidata"
    break
done
[[ -n "$porta" ]] || erro 'nenhuma porta exclusiva disponivel para ensaio.'

cluster="astuti_ens_$(date +%Y%m%d_%H%M%S)_$RANDOM"
[[ "$cluster" =~ ^astuti_ens_[0-9]{8}_[0-9]{6}_[0-9]+$ ]] || erro 'nome de cluster inesperado.'
cluster_criado=0
finalizar() {
    local codigo=$?
    trap - EXIT
    if [[ $cluster_criado -eq 1 ]]; then
        if ! sudo pg_ctlcluster --skip-systemctl-redirect 17 "$cluster" stop >/dev/null; then
            printf 'ERRO: nao foi possivel parar o cluster %s. Verifique o processo manualmente.\n' "$cluster" >&2
            [[ $codigo -ne 0 ]] || codigo=1
        fi
        printf 'Cluster de ensaio preservado para auditoria: 17/%s (porta %s).\n' "$cluster" "$porta"
    fi
    if [[ $codigo -ne 0 ]]; then
        printf 'Ensaio interrompido. Os backups e os bancos originais foram preservados.\n' >&2
    fi
    exit "$codigo"
}
trap finalizar EXIT

printf '\n===== CRIANDO CLUSTER NOVO EM PORTA EXCLUSIVA =====\n'
printf 'Cluster: %s; porta: %s\n' "$cluster" "$porta"
sudo pg_createcluster 17 "$cluster" --port="$porta" --start-conf=manual
cluster_criado=1
# PgCommon trata um IP formado apenas por digitos/pontos como numero, nao
# coloca aspas e gera postgresql.conf invalido. "localhost" e identificador
# simples valido e continua restrito as interfaces de loopback.
sudo pg_conftool 17 "$cluster" set listen_addresses localhost
sudo pg_ctlcluster --skip-systemctl-redirect 17 "$cluster" start
conexao_isolada=(--host=/var/run/postgresql --port="$porta" --username=postgres)
sudo -u postgres pg_isready "${conexao_isolada[@]}" -q || erro 'cluster isolado indisponivel.'

id_isolado="$(sudo -u postgres psql "${conexao_isolada[@]}" -X -At -v ON_ERROR_STOP=1 -d postgres \
    -c 'SELECT system_identifier FROM pg_control_system();')"
[[ "$id_isolado" =~ ^[0-9]+$ && "$id_original" != "$id_isolado" ]] || erro 'identidades PostgreSQL coincidentes ou invalidas; copia proibida.'

senha_temporaria="$(openssl rand -hex 24)"
[[ "$senha_temporaria" =~ ^[a-f0-9]{48}$ ]] || erro 'falha ao gerar credencial temporaria.'
sudo -u postgres psql "${conexao_isolada[@]}" -X -v ON_ERROR_STOP=1 -d postgres -f - <<< \
    "CREATE ROLE astuti_ensaio LOGIN PASSWORD '$senha_temporaria';"

for banco in astuti_platform_dev astuti_monte_sereno_dev; do
    printf '\n===== RESTAURANDO SOMENTE NO CLUSTER ISOLADO: %s =====\n' "$banco"
    sudo -u postgres createdb "${conexao_isolada[@]}" --template=template0 --owner=astuti_ensaio "$banco"
    sudo -u postgres pg_restore "${conexao_isolada[@]}" --role=astuti_ensaio \
        --no-owner --no-privileges --single-transaction --exit-on-error \
        --dbname="$banco" < "$origem/$banco.dump"
done

# As variaveis aplicam-se SOMENTE ao bloco abaixo e nunca reescrevem .env.
(
    cache_dir="$(mktemp -d)"
    trap 'rm -rf -- "$cache_dir"' EXIT
    export APP_CONFIG_CACHE="$cache_dir/config.php"
    export APP_ENV=local
    export PLATFORM_DB_HOST=127.0.0.1
    export PLATFORM_DB_PORT="$porta"
    export PLATFORM_DB_DATABASE=astuti_platform_dev
    export PLATFORM_DB_USERNAME=astuti_ensaio
    export PLATFORM_DB_PASSWORD="$senha_temporaria"
    # Apenas este processo Laravel recebe diagnostico sanitizado de falhas.
    # O comando PHP tambem exige porta exclusiva e usuario de ensaio.
    export ASTUTI_MIGRATION_DIAGNOSE_ISOLATED=1
    export ASTUTI_SOURCE_MARIADB_HOST=127.0.0.1
    export ASTUTI_SOURCE_MARIADB_PORT=3307
    export ASTUTI_SOURCE_MARIADB_SOCKET=/run/mysqld/mysqld.sock
    export ASTUTI_SOURCE_MARIADB_USERNAME="${ASTUTI_SOURCE_MARIADB_USERNAME:-aflora}"
    export ASTUTI_SOURCE_MARIADB_PASSWORD="${ASTUTI_SOURCE_MARIADB_PASSWORD-}"

    # Cache PRIVADO inexistente: nao apaga o cache global do notebook.
    php -r '
        require "vendor/autoload.php";
        $app = require "bootstrap/app.php";
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        if (is_file($app->getCachedConfigPath())) {
            fwrite(STDERR, "ERRO: cache de configuracao do ensaio nao deveria existir.\n");
            exit(1);
        }
        $platform = config("database.connections.platform", []);
        $tenant = config("tenancy.connections.monte_sereno", []);
        foreach ([$platform, $tenant] as $settings) {
            if (($settings["driver"] ?? null) !== "pgsql"
                || ($settings["host"] ?? null) !== "127.0.0.1"
                || (string) ($settings["port"] ?? "") !== getenv("PLATFORM_DB_PORT")
                || ($settings["username"] ?? null) !== "astuti_ensaio") {
                fwrite(STDERR, "ERRO: Laravel nao aponta para o cluster isolado.\n");
                exit(1);
            }
        }
        if (($platform["database"] ?? null) !== "astuti_platform_dev"
            || ($tenant["database"] ?? null) !== "astuti_monte_sereno_dev"
            || config("app.env") !== "local") {
            fwrite(STDERR, "ERRO: configuracao Laravel insegura.\n");
            exit(1);
        }
        config()->set("database.connections.astuti_ensaio_tenant_verificacao", $tenant);
        foreach ([
            ["platform", "astuti_platform_dev"],
            ["astuti_ensaio_tenant_verificacao", "astuti_monte_sereno_dev"],
        ] as [$conexao, $nomeEsperado]) {
            $identidade = \Illuminate\Support\Facades\DB::connection($conexao)->selectOne(
                "SELECT current_database() AS banco, current_user AS usuario, inet_server_port() AS porta"
            );
            if ($identidade->banco !== $nomeEsperado
                || $identidade->usuario !== "astuti_ensaio"
                || (string) $identidade->porta !== getenv("PLATFORM_DB_PORT")) {
                fwrite(STDERR, "ERRO: conexao PostgreSQL efetiva fora do ensaio.\n");
                exit(1);
            }
        }
    '

    printf '\n===== SIMULACAO MARIA DB PARA CLUSTER ISOLADO =====\n'
    php artisan astuti:migrar-dados-postgresql
    printf '\n===== COPIA FICTICIA APENAS NO CLUSTER ISOLADO =====\n'
    php artisan astuti:migrar-dados-postgresql --executar --confirmar=MONTE-SERENO-DEV
)

central_copiada="$(sudo -u postgres psql "${conexao_isolada[@]}" -X -At -v ON_ERROR_STOP=1 \
    -d astuti_platform_dev -c 'SELECT count(*) FROM public.municipalities;')"
municipal_copiada="$(sudo -u postgres psql "${conexao_isolada[@]}" -X -At -F '|' -v ON_ERROR_STOP=1 \
    -d astuti_monte_sereno_dev -c 'SELECT (SELECT count(*) FROM schools), (SELECT count(*) FROM students);')"
[[ "$central_copiada" == 1 && "$municipal_copiada" == '3|115' ]] || erro 'contagens finais divergentes no cluster de ensaio.'

[[ "$(contar_original astuti_platform_dev 'SELECT
    (SELECT count(*) FROM municipalities) + (SELECT count(*) FROM users) +
    (SELECT count(*) FROM municipality_provision_attempts) + (SELECT count(*) FROM memberships) +
    (SELECT count(*) FROM school_scopes) + (SELECT count(*) FROM platform_audit_logs);')" == 0 ]] || erro 'banco central original foi alterado.'
[[ "$(contar_original astuti_monte_sereno_dev 'SELECT
    (SELECT count(*) FROM schools) + (SELECT count(*) FROM school_years) +
    (SELECT count(*) FROM students) + (SELECT count(*) FROM school_classes) +
    (SELECT count(*) FROM student_enrollments) + (SELECT count(*) FROM audit_logs);')" == 0 ]] || erro 'banco municipal original foi alterado.'

printf '\n===== ENSAIO ISOLADO CONCLUIDO =====\n'
printf 'Cluster: %s; porta: %s; 1 municipio, 3 escolas e 115 alunos ficticios copiados.\n' "$cluster" "$porta"
printf 'Bancos PostgreSQL originais na porta 5432 e MariaDB de origem intactos.\n'
