#!/usr/bin/env bash
set -euo pipefail

script="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)/scripts/operacao/ensaiar-restauracao-postgresql.sh"
tmp="$(mktemp -d)"
trap 'rm -rf -- "$tmp"' EXIT

sudo() {
    printf '%s\n' "$*" >> "$ASTUTI_MOCK_LOG"
    if [[ "${1:-}" == '-u' && "${2:-}" == 'postgres' ]]; then
        if [[ " $* " != *' --host=/var/run/postgresql '* ||
              " $* " != *' --port=5432 '* ||
              " $* " != *' --username=postgres '* ]]; then
            echo 'FALHA: comando PostgreSQL sem socket, porta e usuario explicitos.' >&2
            return 87
        fi
    fi
    if [[ "$*" == *' pg_restore '* && "${ASTUTI_FAIL_RESTORE:-}" == '1' ]]; then
        return 42
    fi
    if [[ "$*" == *' createdb '* && "${ASTUTI_FAIL_CREATEDB:-}" == '1' ]]; then
        return 43
    fi
    return 0
}

pg_restore() {
    [[ "$1" == '--list' ]] && [[ -s "$2" ]] || return 1
    printf '%s\n' "$2" >> "${ASTUTI_LIST_LOG:-/dev/null}"
    [[ "$2" != "${ASTUTI_FAIL_LIST_ON:-}" ]]
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
export ASTUTI_LIST_LOG="$tmp/lista.log"
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
grep -Eq 'createdb .*--template=template0 astuti_restore_pg_platform_[0-9]{8}_[0-9]{6}_[0-9]+$' "$ASTUTI_MOCK_LOG"
grep -Eq 'createdb .*--template=template0 astuti_restore_pg_monte_[0-9]{8}_[0-9]{6}_[0-9]+$' "$ASTUTI_MOCK_LOG"
grep -Eq 'pg_restore .*--dbname=astuti_restore_pg_platform_[0-9]{8}_[0-9]{6}_[0-9]+$' "$ASTUTI_MOCK_LOG"
grep -Eq 'pg_restore .*--dbname=astuti_restore_pg_monte_[0-9]{8}_[0-9]{6}_[0-9]+$' "$ASTUTI_MOCK_LOG"
[[ "$(wc -l < "$ASTUTI_LIST_LOG")" == '2' ]]
grep -q 'ENSAIO DE RESTAURACAO CONCLUIDO' "$tmp/sucesso.out"

# Falha de formato em um arquivo impede o primeiro sudo.
export ASTUTI_MOCK_LOG="$tmp/lista-falha-sudo.log"
export ASTUTI_FAIL_LIST_ON="$tmp/validos/astuti_monte_sereno_dev.dump"
if bash "$script" --backup-dir "$tmp/validos" > "$tmp/lista-falha.out" 2>&1; then
    echo 'FALHA: aceitou pg_restore --list com erro.' >&2
    exit 1
fi
[[ ! -e "$ASTUTI_MOCK_LOG" ]]
unset ASTUTI_FAIL_LIST_ON

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

# Erro no createdb impede a tentativa de pg_restore.
unset ASTUTI_FAIL_RESTORE
export ASTUTI_FAIL_CREATEDB=1
export ASTUTI_MOCK_LOG="$tmp/createdb-falha.log"
if bash "$script" --backup-dir "$tmp/validos" > "$tmp/createdb-falha.out" 2>&1; then
    echo 'FALHA: ignorou erro na criacao do primeiro banco.' >&2
    exit 1
fi
! grep -q '^\-u postgres pg_restore ' "$ASTUTI_MOCK_LOG"
unset ASTUTI_FAIL_CREATEDB

# Detecta o diretorio de backups mesmo se seu sufixo contiver "1" em vez de "l".
unset ASTUTI_FAIL_RESTORE
mkdir -p "$tmp/colecao/pgsql-antes-jobs-2Xqrc91g"
cp "$tmp/validos/astuti_platform_dev.dump" "$tmp/colecao/pgsql-antes-jobs-2Xqrc91g/"
cp "$tmp/validos/astuti_monte_sereno_dev.dump" "$tmp/colecao/pgsql-antes-jobs-2Xqrc91g/"
export ASTUTI_MOCK_LOG="$tmp/descoberta.log"
bash "$script" --backup-root "$tmp/colecao" > "$tmp/descoberta.out" 2>&1
grep -q '/pgsql-antes-jobs-2Xqrc91g/astuti_platform_dev.dump' "$tmp/descoberta.out"
grep -q 'ENSAIO DE RESTAURACAO CONCLUIDO' "$tmp/descoberta.out"

# Se houver mais de um par completo, nao seleciona um backup arbitrario.
mkdir -p "$tmp/colecao/pgsql-antes-jobs-outro"
cp "$tmp/validos/astuti_platform_dev.dump" "$tmp/colecao/pgsql-antes-jobs-outro/"
cp "$tmp/validos/astuti_monte_sereno_dev.dump" "$tmp/colecao/pgsql-antes-jobs-outro/"
export ASTUTI_MOCK_LOG="$tmp/ambiguo.log"
if bash "$script" --backup-root "$tmp/colecao" > "$tmp/ambiguo.out" 2>&1; then
    echo 'FALHA: selecionou arbitrariamente entre dois backups completos.' >&2
    exit 1
fi
[[ ! -e "$ASTUTI_MOCK_LOG" ]] || {
    echo 'FALHA: sudo foi chamado com backups ambiguos.' >&2
    exit 1
}

# Uma busca parcialmente malsucedida nao pode ser confundida com lista completa.
find() {
    if [[ "${ASTUTI_FAIL_FIND:-}" == '1' ]]; then
        printf '%s\0' "$ASTUTI_PARTIAL_FOLDER"
        return 23
    fi
    command find "$@"
}
export -f find
export ASTUTI_FAIL_FIND=1
export ASTUTI_PARTIAL_FOLDER="$tmp/colecao/pgsql-antes-jobs-2Xqrc91g"
export ASTUTI_MOCK_LOG="$tmp/find-erro.log"
if bash "$script" --backup-root "$tmp/colecao" > "$tmp/find-erro.out" 2>&1; then
    echo 'FALHA: aceitou busca parcial com find retornando erro.' >&2
    exit 1
fi
[[ ! -e "$ASTUTI_MOCK_LOG" ]] || {
    echo 'FALHA: sudo foi chamado apesar de find ter falhado.' >&2
    exit 1
}
unset ASTUTI_FAIL_FIND ASTUTI_PARTIAL_FOLDER

echo 'PASS: 8 cenarios de seguranca do ensaio PostgreSQL.'
