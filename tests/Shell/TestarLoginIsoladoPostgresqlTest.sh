#!/usr/bin/env bash
# Testes sem PostgreSQL: o acesso deve falhar fechado antes de qualquer escrita.
set -Eeuo pipefail

repo="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
script="$repo/scripts/operacao/testar-login-isolado-postgresql.sh"
tmp="$(mktemp -d)"
trap 'rm -rf -- "$tmp"' EXIT

sudo() {
    printf 'sudo %s\n' "$*" >> "$ASTUTI_MOCK_LOG"
    # Regressao real do notebook: psql 17 NAO reconhece --database=.
    # O comando precisa usar -d ou --dbname, inclusive ao alterar o papel.
    if [[ "$*" == *' psql '* && "$*" == *'--database='* ]]; then
        printf "psql: unrecognized option '--database'\n" >&2
        return 2
    fi
    if [[ "$*" == *'pg_control_system()'* ]]; then
        if [[ "$*" == *'--port=5432'* ]]; then
            printf '11111111111\n'
        elif [[ "${MOCK_IDENTIDADE_IGUAL:-}" == 1 ]]; then
            printf '11111111111\n'
        else
            printf '22222222222\n'
        fi
    elif [[ "$*" == *'--port=55436'* && ( "$*" == *'-d astuti_platform_dev'* || "$*" == *'--dbname=astuti_platform_dev'* ) ]]; then
        if [[ "$*" == *'COUNT(*) FROM municipalities'* ]]; then
            printf '1\n'
        elif [[ "$*" == *'COUNT(*) FROM users WHERE status'* ]]; then
            printf '1\n'
        elif [[ "$*" == *'COUNT(*) FROM memberships'* ]]; then
            printf '1\n'
        elif [[ "$*" == *'COUNT(*) FROM users'* ]]; then
            printf '1\n'
        fi
    elif [[ "$*" == *'--port=55436'* && ( "$*" == *'-d astuti_monte_sereno_dev'* || "$*" == *'--dbname=astuti_monte_sereno_dev'* ) ]]; then
        if [[ "${MOCK_CONTAGEM_INCORRETA:-}" == 1 ]]; then
            printf '2|115|115\n'
        else
            printf '3|115|115\n'
        fi
    fi
}
pg_lsclusters() {
    printf '17 main 5432 online postgres /var/lib/postgresql/17/main /var/log/postgresql/main.log\n'
    if [[ "${MOCK_CLUSTER_PARADO:-}" == 1 ]]; then
        printf '17 astuti_ens_20261121_112332_22176 55436 down postgres /var/lib/postgresql/17/ensaio /var/log/postgresql/ensaio.log\n'
    else
        printf '17 astuti_ens_20261121_112332_22176 55436 online postgres /var/lib/postgresql/17/ensaio /var/log/postgresql/ensaio.log\n'
    fi
}
psql() { return 0; }
php() {
    printf 'php %s\n' "$*" >> "$ASTUTI_MOCK_LOG"
    if [[ "$1" == '-r' && -n "${ASTUTI_LOGIN_TEST_PASSWORD:-}" && -n "${MOCK_PASSWORD_ESPERADA:-}" ]]; then
        [[ "$ASTUTI_LOGIN_TEST_PASSWORD" == "$MOCK_PASSWORD_ESPERADA" ]] || return 88
    fi
    if [[ "$1" == '-r' && "${MOCK_LARAVEL_INSEGURO:-}" == 1 ]]; then
        return 15
    fi
    if [[ "$1" == '-r' && "${MOCK_ESTRUTURA_PENDENTE:-}" == 1 && "$2" == *'$pendentes'* ]]; then
        return 3
    fi
    if [[ "$1" == 'artisan' && "$2" == 'serve' ]]; then
        [[ "${PLATFORM_DB_PORT:-}" == 55436 ]]
        [[ "${SESSION_COOKIE:-}" == astuti_ensaio_55436 ]]
        [[ "${APP_CONFIG_CACHE:-}" != "$repo/bootstrap/cache/config.php" ]]
        [[ -z "${ASTUTI_LOGIN_TEST_PASSWORD:-}" ]]
        [[ -z "${ASTUTI_DEMO_PASSWORD:-}" ]]
    fi
}
export -f sudo pg_lsclusters psql php

if [[ ! -f "$script" ]]; then
    echo 'FALHA ESPERADA (TDD): script de login isolado ainda nao existe.' >&2
    exit 1
fi

verificar_ausencia_de_escrita() {
    if [[ -f "$ASTUTI_MOCK_LOG" ]] && grep -q 'ALTER ROLE\|php -r' "$ASTUTI_MOCK_LOG"; then
        echo 'FALHA: escrita ou inicializacao Laravel antes de validar identidade.' >&2
        exit 1
    fi
}

export ASTUTI_MOCK_LOG="$tmp/cluster-parado.log"
export MOCK_CLUSTER_PARADO=1
if bash "$script" > "$tmp/cluster-parado.out" 2>&1; then
    echo 'FALHA: aceitou o cluster parado.' >&2
    exit 1
fi
verificar_ausencia_de_escrita
unset MOCK_CLUSTER_PARADO

export ASTUTI_MOCK_LOG="$tmp/mesma-identidade.log"
export MOCK_IDENTIDADE_IGUAL=1
if bash "$script" > "$tmp/mesma-identidade.out" 2>&1; then
    echo 'FALHA: aceitou a identidade do PostgreSQL original.' >&2
    exit 1
fi
verificar_ausencia_de_escrita
unset MOCK_IDENTIDADE_IGUAL

export ASTUTI_MOCK_LOG="$tmp/contagem-insegura.log"
export MOCK_CONTAGEM_INCORRETA=1
if bash "$script" > "$tmp/contagem-insegura.out" 2>&1; then
    echo 'FALHA: aceitou contagens de dados inesperadas.' >&2
    exit 1
fi
verificar_ausencia_de_escrita
unset MOCK_CONTAGEM_INCORRETA

export ASTUTI_MOCK_LOG="$tmp/laravel-inseguro.log"
export MOCK_LARAVEL_INSEGURO=1
if bash "$script" > "$tmp/laravel-inseguro.out" 2>&1; then
    echo 'FALHA: aceitou a configuracao Laravel incorreta.' >&2
    exit 1
fi
if grep -q 'php artisan serve' "$ASTUTI_MOCK_LOG"; then
    echo 'FALHA: iniciou servidor com configuracao incorreta.' >&2
    exit 1
fi
unset MOCK_LARAVEL_INSEGURO

export ASTUTI_MOCK_LOG="$tmp/sucesso.log"
if ! bash "$script" > "$tmp/sucesso.out" 2>&1; then
    echo 'FALHA: nao iniciou servidor no ensaio seguro.' >&2
    tail -n 20 "$tmp/sucesso.out" >&2
    exit 1
fi
grep -q 'sudo -u postgres psql .*--port=55436 .*--file=-' "$ASTUTI_MOCK_LOG"
grep -q 'php artisan serve --host=127.0.0.1 --port=8002' "$ASTUTI_MOCK_LOG"
grep -q 'http://127.0.0.1:8002/login' "$tmp/sucesso.out"
if grep -q 'config:clear\|migrate\|--port=5432 .*--file=-' "$ASTUTI_MOCK_LOG"; then
    echo 'FALHA: comando perigoso no acesso de teste.' >&2
    exit 1
fi

# O operador pode escolher uma senha simples SOMENTE para a conta ficticia
# neste cluster isolado. O Laravel deve receber exatamente esse valor.
export ASTUTI_MOCK_LOG="$tmp/senha-definida.log"
export MOCK_PASSWORD_ESPERADA='123456'
if ! ASTUTI_DEMO_PASSWORD=123456 bash "$script" > "$tmp/senha-definida.out" 2>&1; then
    echo 'FALHA: nao aceitou senha explicita na demonstracao isolada.' >&2
    tail -n 20 "$tmp/senha-definida.out" >&2
    exit 1
fi
grep -q 'Senha temporaria SOMENTE DO ENSAIO: 123456' "$tmp/senha-definida.out"
grep -q 'php artisan serve --host=127.0.0.1 --port=8002' "$ASTUTI_MOCK_LOG"
unset MOCK_PASSWORD_ESPERADA

# Senhas malformadas nao podem alcançar ALTER ROLE nem o Laravel.
export ASTUTI_MOCK_LOG="$tmp/senha-invalida.log"
if ASTUTI_DEMO_PASSWORD='12345' bash "$script" > "$tmp/senha-invalida.out" 2>&1; then
    echo 'FALHA: aceitou senha inferior ao limite previsto para demonstracao.' >&2
    exit 1
fi
if grep -q -- '--file=-\|php -r' "$ASTUTI_MOCK_LOG"; then
    echo 'FALHA: senha invalida nao interrompeu o fluxo antes da escrita.' >&2
    exit 1
fi

# Atualizar a estrutura so acontece quando pedido, depois das conferencias,
# com verificacao da conexao efetiva 55436 e antes de abrir o servidor.
export ASTUTI_MOCK_LOG="$tmp/atualizar-estrutura.log"
if ! ASTUTI_ATUALIZAR_ESTRUTURA=1 bash "$script" > "$tmp/atualizar-estrutura.out" 2>&1; then
    echo 'FALHA: nao atualizou a estrutura quando pedido.' >&2
    tail -n 20 "$tmp/atualizar-estrutura.out" >&2
    exit 1
fi
grep -q 'ATUALIZANDO A ESTRUTURA, APENAS NO ENSAIO 55436' "$tmp/atualizar-estrutura.out"
grep -q 'Artisan::call("migrate"' "$ASTUTI_MOCK_LOG"
grep -q 'astuti_monte_sereno_dev' "$ASTUTI_MOCK_LOG"
linha_migrar="$(grep -n 'Artisan::call("migrate"' "$ASTUTI_MOCK_LOG" | head -1 | cut -d: -f1)"
linha_servir="$(grep -n 'php artisan serve' "$ASTUTI_MOCK_LOG" | head -1 | cut -d: -f1)"
[[ -n "$linha_migrar" && -n "$linha_servir" && "$linha_migrar" -lt "$linha_servir" ]] || {
    echo 'FALHA: o servidor abriu antes da atualizacao da estrutura.' >&2
    exit 1
}
if grep -q -- '--port=5432 .*--file=-\|migrate:fresh\|db:wipe' "$ASTUTI_MOCK_LOG"; then
    echo 'FALHA: comando perigoso ao atualizar a estrutura.' >&2
    exit 1
fi

# Sem a opcao, uma copia com estrutura antiga e recusada antes de abrir o
# servidor, com instrucao clara, e nada e migrado.
export ASTUTI_MOCK_LOG="$tmp/estrutura-pendente.log"
export MOCK_ESTRUTURA_PENDENTE=1
if bash "$script" > "$tmp/estrutura-pendente.out" 2>&1; then
    echo 'FALHA: abriu o sistema com a estrutura desatualizada.' >&2
    exit 1
fi
grep -q 'ASTUTI_ATUALIZAR_ESTRUTURA=1' "$tmp/estrutura-pendente.out"
if grep -q 'php artisan serve\|Artisan::call("migrate"' "$ASTUTI_MOCK_LOG"; then
    echo 'FALHA: serviu ou migrou com a estrutura desatualizada.' >&2
    exit 1
fi
unset MOCK_ESTRUTURA_PENDENTE

printf 'PASS: 9 cenarios de acesso seguro ao ensaio PostgreSQL.\n'
