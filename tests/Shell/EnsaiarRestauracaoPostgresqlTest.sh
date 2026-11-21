#!/usr/bin/env bash
set -euo pipefail

script="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)/scripts/operacao/ensaiar-restauracao-postgresql.sh"
tmp="$(mktemp -d)"
trap 'rm -rf -- "$tmp"' EXIT

sudo() {
    printf '%s\n' "$*" >> "$ASTUTI_MOCK_LOG"
    if [[ "$*" == *' pg_restore '* && "${ASTUTI_FAIL_RESTORE:-}" == '1' ]]; then
        return 42
    fi
    return 0
}

pg_restore() {
    [[ "$1" == '--list' ]] && [[ -s "$2" ]]
}

export -f sudo pg_restore

# Sem arquivos de backup, nenhum comando privilegiado pode acontecer.
mkdir -p "$tmp/ausentes"
export ASTUTI_MOCK_LOG="$tmp/sem-efeito.log"
if bash "$script" --backup-dir "$tmp/ausentes" > "$tmp/sem-efeito.out" 2>&1; then
    echo 'FALHA: permitiu continuar sem backups.' >&2
    exit 1
fi
[[ ! -e "$ASTUTI_MOCK_LOG" ]] || {
    echo 'FALHA: usou sudo mesmo sem backups.' >&2
    exit 1
}

# Com arquivos validados, criar dois bancos novos; nunca restaurar sobre *_dev.
mkdir -p "$tmp/validos"
printf 'arquivo ficticio' > "$tmp/validos/astuti_platform_dev.dump"
printf 'arquivo ficticio' > "$tmp/validos/astuti_monte_sereno_dev.dump"
export ASTUTI_MOCK_LOG="$tmp/sucesso.log"
bash "$script" --backup-dir "$tmp/validos" > "$tmp/sucesso.out" 2>&1
[[ "$(grep -c '^\-u postgres createdb ' "$ASTUTI_MOCK_LOG")" == '2' ]] || {
    echo 'FALHA: quantidade incorreta de bancos novos.' >&2
    exit 1
}
[[ "$(grep -c '^\-u postgres pg_restore ' "$ASTUTI_MOCK_LOG")" == '2' ]] || {
    echo 'FALHA: quantidade incorreta de restauracoes.' >&2
    exit 1
}
! grep -E -- '--dbname=astuti_(platform|monte_sereno)_dev' "$ASTUTI_MOCK_LOG"
grep -q 'ENSAIO DE RESTAURACAO CONCLUIDO' "$tmp/sucesso.out"

# Erro de restauracao deve interromper antes de criar o segundo banco.
export ASTUTI_MOCK_LOG="$tmp/falha.log"
export ASTUTI_FAIL_RESTORE=1
if bash "$script" --backup-dir "$tmp/validos" > "$tmp/falha.out" 2>&1; then
    echo 'FALHA: ignorou erro do pg_restore.' >&2
    exit 1
fi
[[ "$(grep -c '^\-u postgres createdb ' "$ASTUTI_MOCK_LOG")" == '1' ]] || {
    echo 'FALHA: criou segundo banco apos erro.' >&2
    exit 1
}
! grep -q 'ENSAIO DE RESTAURACAO CONCLUIDO' "$tmp/falha.out"

echo 'PASS: 3 cenarios de seguranca do ensaio PostgreSQL.'
