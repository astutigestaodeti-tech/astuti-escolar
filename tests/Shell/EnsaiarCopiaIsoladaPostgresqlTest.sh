#!/usr/bin/env bash
# TDD: segurança do ensaio MariaDB -> PostgreSQL em cluster descartável.
set -euo pipefail

repo="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
script="$repo/scripts/operacao/ensaiar-copia-isolada-postgresql.sh"
tmp="$(mktemp -d)"
trap 'rm -rf -- "$tmp"' EXIT

mkdir -p "$tmp/backups/pgsql-pre-copia-PRIMEIRA"
backup="$tmp/backups/pgsql-pre-copia-PRIMEIRA"
printf 'PGDMP-test-platform\n' > "$backup/astuti_platform_dev.dump"
printf 'PGDMP-test-monte\n' > "$backup/astuti_monte_sereno_dev.dump"
(
    cd "$backup"
    sha256sum astuti_platform_dev.dump astuti_monte_sereno_dev.dump > SHA256SUMS
)

sudo() {
    printf 'sudo %s\n' "$*" >> "$ASTUTI_MOCK_LOG"
    # PgCommon::quote_conf_value classifica 127.0.0.1 como numero e o grava
    # sem aspas no postgresql.conf. O servidor real recusa essa configuracao.
    if [[ "$*" == *'pg_ctlcluster '*" start" ]] && \
        grep -q 'pg_conftool .* set listen_addresses 127[.]0[.]0[.]1$' "$ASTUTI_MOCK_LOG"; then
        return 1
    fi
    if [[ "$*" == *'pg_control_system()'* ]]; then
        if [[ "$*" == *'--port=5432'* ]]; then
            printf '1234567890\n'
        elif [[ "${ASTUTI_MOCK_SAME_SYSID:-}" == 1 ]]; then
            printf '1234567890\n'
        else
            printf '9876543210\n'
        fi
    elif [[ "$*" == *'pg_isready'* && "${ASTUTI_PORTA_OCUPADA:-}" == 1 && "$*" == *'--port=55432'* ]]; then
        return 0
    elif [[ "$*" == *'pg_isready'* && "$*" == *'--port=55432'* ]]; then
        if grep -q 'sudo pg_ctlcluster .* start$' "$ASTUTI_MOCK_LOG"; then
            return 0
        fi
        return 2
    elif [[ "$*" == *'--port=55432'* && "$*" == *'SELECT count(*) FROM public.municipalities'* ]]; then
        printf '1\n'
    elif [[ "$*" == *'--port=55432'* && "$*" == *'SELECT (SELECT count(*) FROM schools)'* ]]; then
        printf '3|115\n'
    elif [[ "$*" == *'SELECT'*'count('* ]]; then
        if [[ "${ASTUTI_ORIGINAL_ALTERADO:-}" == 1 && "$*" == *'--port=5432'* ]]; then
            printf '1\n'
        else
            printf '0\n'
        fi
    fi
    return 0
}

pg_createcluster() { return 0; }
pg_ctlcluster() { return 0; }
pg_conftool() { return 0; }
pg_lsclusters() { return 0; }
pg_restore() { [[ "$1" == '--list' && -s "$2" ]]; }
php() {
    printf 'php %s\n' "$*" >> "$ASTUTI_MOCK_LOG"
    if [[ "$*" == *'astuti:migrar-dados-postgresql --executar'* && "${ASTUTI_MOCK_DIAGNOSTICO_EXIGIDO:-}" == 1 && "${ASTUTI_MIGRATION_DIAGNOSE_ISOLATED:-}" != 1 ]]; then
        return 47
    fi
    if [[ "$1" == '-r' && "${ASTUTI_MOCK_CONFIG_INCORRETA:-}" == 1 ]]; then
        return 42
    fi
    if [[ "$*" == *'astuti:migrar-dados-postgresql'* && "$*" != *'--executar'* && "${ASTUTI_SIMULACAO_FALHA:-}" == 1 ]]; then
        return 41
    fi
    return 0
}
export -f sudo pg_createcluster pg_ctlcluster pg_conftool pg_lsclusters pg_restore php

nao_deve_conter() {
    if grep -q -- "$1" "$2"; then
        echo "FALHA: comando proibido no log: $1" >&2
        exit 1
    fi
}

# Sem manifesto válido, nenhum cluster novo deve ser criado.
export ASTUTI_MOCK_LOG="$tmp/sem-manifesto.log"
if bash "$script" --backup-dir "$tmp/inexistente" > "$tmp/sem-manifesto.out" 2>&1; then
    echo 'FALHA: aceitou backup ausente.' >&2
    exit 1
fi
if [[ -e "$ASTUTI_MOCK_LOG" ]]; then
    nao_deve_conter 'pg_createcluster' "$ASTUTI_MOCK_LOG"
fi

# Manifesto incompleto nao pode liberar uma restauracao.
export ASTUTI_MOCK_LOG="$tmp/manifesto-incompleto.log"
cp "$backup/SHA256SUMS" "$tmp/manifesto-completo"
sed -n '1p' "$tmp/manifesto-completo" > "$backup/SHA256SUMS"
if bash "$script" --backup-dir "$backup" > "$tmp/manifesto-incompleto.out" 2>&1; then
    echo 'FALHA: aceitou manifesto que ignora um dump.' >&2
    exit 1
fi
if [[ -e "$ASTUTI_MOCK_LOG" ]]; then
    nao_deve_conter 'pg_createcluster' "$ASTUTI_MOCK_LOG"
fi
cp "$tmp/manifesto-completo" "$backup/SHA256SUMS"

# O ensaio passa por outro cluster, porta distinta e duas fases: conferir e executar.
export ASTUTI_MOCK_LOG="$tmp/sucesso.log"
if ! bash "$script" --backup-dir "$backup" > "$tmp/sucesso.out" 2>&1; then
    echo 'FALHA: cenario de sucesso do ensaio.' >&2
    tail -n 25 "$tmp/sucesso.out" >&2
    exit 1
fi
grep -q 'sudo pg_createcluster 17 .*--port=55432 --start-conf=manual$' "$ASTUTI_MOCK_LOG"
grep -q 'sudo pg_conftool 17 .* set listen_addresses localhost$' "$ASTUTI_MOCK_LOG"
grep -q 'sudo pg_ctlcluster --skip-systemctl-redirect 17 .* start$' "$ASTUTI_MOCK_LOG"
grep -q 'sudo pg_ctlcluster --skip-systemctl-redirect 17 .* stop$' "$ASTUTI_MOCK_LOG"
grep -q 'php artisan astuti:migrar-dados-postgresql$' "$ASTUTI_MOCK_LOG"
grep -q 'php artisan astuti:migrar-dados-postgresql --executar --confirmar=MONTE-SERENO-DEV$' "$ASTUTI_MOCK_LOG"
nao_deve_conter 'php artisan config:clear' "$ASTUTI_MOCK_LOG"
grep -q 'ENSAIO ISOLADO CONCLUIDO' "$tmp/sucesso.out"

# Um erro da copia precisa sair do Laravel com diagnostico sanitizado no cluster isolado.
# O marcador de diagnostico nao pode ser exportado para o shell do usuario.
export ASTUTI_MOCK_LOG="$tmp/diagnostico.log"
export ASTUTI_MOCK_DIAGNOSTICO_EXIGIDO=1
if ! bash "$script" --backup-dir "$backup" > "$tmp/diagnostico.out" 2>&1; then
    echo 'FALHA: a copia isolada nao ativou o diagnostico seguro.' >&2
    tail -n 12 "$tmp/diagnostico.out" >&2
    exit 1
fi
[[ -z "${ASTUTI_MIGRATION_DIAGNOSE_ISOLATED:-}" ]]
unset ASTUTI_MOCK_DIAGNOSTICO_EXIGIDO

# Falha de simulação deve barrar a cópia, mesmo após restaurar o teste.
export ASTUTI_MOCK_LOG="$tmp/falha-simulacao.log"
export ASTUTI_SIMULACAO_FALHA=1
if bash "$script" --backup-dir "$backup" > "$tmp/falha-simulacao.out" 2>&1; then
    echo 'FALHA: ignorou erro da simulacao.' >&2
    exit 1
fi
nao_deve_conter 'php artisan astuti:migrar-dados-postgresql --executar' "$ASTUTI_MOCK_LOG"
unset ASTUTI_SIMULACAO_FALHA

# Recusa uso de porta já ocupada antes de criar outro cluster.
export ASTUTI_MOCK_LOG="$tmp/porta-ocupada.log"
export ASTUTI_PORTA_OCUPADA=1
if bash "$script" --backup-dir "$backup" > "$tmp/porta-ocupada.out" 2>&1; then
    echo 'FALHA: aceitou porta ocupada.' >&2
    exit 1
fi
nao_deve_conter 'pg_createcluster' "$ASTUTI_MOCK_LOG"
unset ASTUTI_PORTA_OCUPADA

# Mesmo uma diferença mínima nos bancos originais encerra o ensaio.
export ASTUTI_MOCK_LOG="$tmp/original-alterado.log"
export ASTUTI_ORIGINAL_ALTERADO=1
if bash "$script" --backup-dir "$backup" > "$tmp/original-alterado.out" 2>&1; then
    echo 'FALHA: permitiu dados em PostgreSQL original.' >&2
    exit 1
fi
nao_deve_conter 'php artisan astuti:migrar-dados-postgresql --executar' "$ASTUTI_MOCK_LOG"
unset ASTUTI_ORIGINAL_ALTERADO

# Se a identidade do cluster for a do original, aborta antes de executar Artisan.
export ASTUTI_MOCK_LOG="$tmp/id-repetido.log"
export ASTUTI_MOCK_SAME_SYSID=1
if bash "$script" --backup-dir "$backup" > "$tmp/id-repetido.out" 2>&1; then
    echo 'FALHA: aceitou cluster sem isolamento fisico.' >&2
    exit 1
fi
nao_deve_conter 'php artisan astuti:migrar-dados-postgresql --executar' "$ASTUTI_MOCK_LOG"
unset ASTUTI_MOCK_SAME_SYSID

# Configuração Laravel divergente deve impedir até a simulação.
export ASTUTI_MOCK_LOG="$tmp/config-insegura.log"
export ASTUTI_MOCK_CONFIG_INCORRETA=1
if bash "$script" --backup-dir "$backup" > "$tmp/config-insegura.out" 2>&1; then
    echo 'FALHA: aceitou configuracao Laravel insegura.' >&2
    exit 1
fi
nao_deve_conter 'php artisan astuti:migrar-dados-postgresql --executar' "$ASTUTI_MOCK_LOG"
unset ASTUTI_MOCK_CONFIG_INCORRETA

echo 'PASS: 9 cenarios do ensaio de copia isolada PostgreSQL.'
