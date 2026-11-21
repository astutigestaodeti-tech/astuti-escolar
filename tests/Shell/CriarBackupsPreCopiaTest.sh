#!/usr/bin/env bash
set -euo pipefail

repo="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
script="$repo/scripts/operacao/criar-backups-pre-copia-postgresql.sh"
tmp="$(mktemp -d)"
trap 'rm -rf -- "$tmp"' EXIT

sudo() {
    printf '%s\n' "$*" >> "$ASTUTI_MOCK_LOG"
    if [[ "$*" == *' pg_dump '* ]]; then
        if [[ "$*" == *"--dbname=${ASTUTI_FAIL_DUMP_FOR:-NAO_CONFIGURADO}"* ]]; then
            return 47
        fi
        printf 'PGDMP-arquivo-ficticio'
        return 0
    fi
    if [[ "$*" == *' psql '* ]]; then
        if [[ "${ASTUTI_MOCK_JOBS_AUSENTES:-}" == '1' ]]; then
            printf 'f\n'
        elif [[ "$*" == *'to_regclass('* ]]; then
            printf 't\n'
        elif [[ "${ASTUTI_MOCK_TABELA_EXTRA:-}" == '1' && "$*" == *'platform_audit_logs'* ]]; then
            printf '1\n'
        else
            printf '0\n'
        fi
    fi
    return 0
}

pg_restore() {
    [[ "$1" == '--list' && -s "$2" ]]
}

export -f sudo pg_restore

# Um backup novo e um ensaio de restauracao completos em bancos novos.
export ASTUTI_MOCK_LOG="$tmp/sucesso.log"
bash "$script" --backup-root "$tmp/backups" > "$tmp/sucesso.out" 2>&1
test "$(find "$tmp/backups" -maxdepth 1 -type d -name 'pgsql-pre-copia-*' | wc -l)" -eq 1
test "$(find "$tmp/backups" -name '*.dump' -type f | wc -l)" -eq 2
grep -q '^\-u postgres pg_dump .*--dbname=astuti_platform_dev$' "$ASTUTI_MOCK_LOG"
grep -q '^\-u postgres pg_dump .*--dbname=astuti_monte_sereno_dev$' "$ASTUTI_MOCK_LOG"
test "$(grep -c '^\-u postgres createdb ' "$ASTUTI_MOCK_LOG")" -eq 2
grep -q 'BACKUPS PRE-COPIA E RESTAURACAO CONFERIDOS' "$tmp/sucesso.out"

# Repetir a operacao cria outra pasta; nunca sobrescreve a primeira.
export ASTUTI_MOCK_LOG="$tmp/segunda.log"
bash "$script" --backup-root "$tmp/backups" > "$tmp/segunda.out" 2>&1
test "$(find "$tmp/backups" -maxdepth 1 -type d -name 'pgsql-pre-copia-*' | wc -l)" -eq 2
test "$(find "$tmp/backups" -name '*.dump' -type f | wc -l)" -eq 4

# Falha do segundo pg_dump para antes de restaurar; preserva primeira copia.
export ASTUTI_FAIL_DUMP_FOR='astuti_monte_sereno_dev'
export ASTUTI_MOCK_LOG="$tmp/erro-dump.log"
if bash "$script" --backup-root "$tmp/falha" > "$tmp/erro-dump.out" 2>&1; then
    echo 'FALHA: ignorou erro de pg_dump municipal.' >&2
    exit 1
fi
! grep -q '^\-u postgres createdb ' "$ASTUTI_MOCK_LOG"
unset ASTUTI_FAIL_DUMP_FOR

# Sem a tabela jobs no banco de desenvolvimento, nao inicia o backup.
export ASTUTI_MOCK_JOBS_AUSENTES=1
export ASTUTI_MOCK_LOG="$tmp/falta-jobs.log"
if bash "$script" --backup-root "$tmp/sem-jobs" > "$tmp/sem-jobs.out" 2>&1; then
    echo 'FALHA: criou backup de esquema antigo, sem jobs.' >&2
    exit 1
fi
[[ ! -e "$ASTUTI_MOCK_LOG" ]] || ! grep -q '^\-u postgres pg_dump ' "$ASTUTI_MOCK_LOG"
unset ASTUTI_MOCK_JOBS_AUSENTES

# Bloquear dumps dentro de repositorios Git: conteudo inclui dados e e privado.
mkdir -p "$tmp/repositorio"
git -C "$tmp/repositorio" init -q
export ASTUTI_MOCK_LOG="$tmp/git-dentro.log"
if bash "$script" --backup-root "$tmp/repositorio/backups" > "$tmp/git-dentro.out" 2>&1; then
    echo 'FALHA: permitiu gravar dumps dentro de um repositorio Git.' >&2
    exit 1
fi
[[ ! -e "$ASTUTI_MOCK_LOG" ]] || ! grep -q '^\-u postgres pg_dump ' "$ASTUTI_MOCK_LOG"

# Verificar todas as tabelas alvo: uma auditoria preenchida deve impedir backup.
export ASTUTI_MOCK_TABELA_EXTRA=1
export ASTUTI_MOCK_LOG="$tmp/outra-tabela.log"
if bash "$script" --backup-root "$tmp/outra-tabela" > "$tmp/outra-tabela.out" 2>&1; then
    echo 'FALHA: ignorou registros de auditoria em destino de copia.' >&2
    exit 1
fi
! grep -q '^\-u postgres pg_dump ' "$ASTUTI_MOCK_LOG"

echo 'PASS: 6 cenarios de backup pre-copia PostgreSQL.'
