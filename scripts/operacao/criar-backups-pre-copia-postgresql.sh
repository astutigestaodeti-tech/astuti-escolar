#!/usr/bin/env bash
# Cria backups COMPLETOS (estrutura e dados) do PostgreSQL atual e testa copias.
# Nunca sobrescreve dumps anteriores e nao modifica bancos *_dev.
set -Eeuo pipefail
umask 077

if [[ $# -eq 0 ]]; then
    raiz="$HOME/astuti-backups"
elif [[ $# -eq 2 && "$1" == '--backup-root' && -n "$2" ]]; then
    raiz="$2"
else
    echo 'Uso: bash scripts/operacao/criar-backups-pre-copia-postgresql.sh [--backup-root PASTA]' >&2
    exit 2
fi

# Dumps customizados incluem todos os dados. Nunca grava-los dentro do Git.
mkdir -p -- "$raiz"
if git -C "$raiz" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    echo 'ERRO: pasta de backup dentro de repositorio Git. Escolha outra pasta.' >&2
    exit 1
fi

conexao_pg=(--host=/var/run/postgresql --port=5432 --username=postgres)
roteiro_restauracao="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/ensaiar-restauracao-postgresql.sh"

printf '\n===== CONFERINDO POSTGRESQL LOCAL =====\n'
sudo -v
sudo -u postgres pg_isready "${conexao_pg[@]}" -q

jobs="$(sudo -u postgres psql "${conexao_pg[@]}" -X -At \
    -v ON_ERROR_STOP=1 -d astuti_platform_dev \
    -c "SELECT to_regclass('public.jobs') IS NOT NULL;")"
if [[ "$jobs" != 't' ]]; then
    echo 'ERRO: tabela jobs nao presente no banco central atual. Backup interrompido.' >&2
    exit 1
fi

central_vazio="$(sudo -u postgres psql "${conexao_pg[@]}" -X -At \
    -v ON_ERROR_STOP=1 -d astuti_platform_dev \
    -c "SELECT (SELECT count(*) FROM public.municipalities)
       + (SELECT count(*) FROM public.users)
       + (SELECT count(*) FROM public.memberships)
       + (SELECT count(*) FROM public.municipality_provision_attempts)
       + (SELECT count(*) FROM public.school_scopes)
       + (SELECT count(*) FROM public.platform_audit_logs);")"
municipal_vazio="$(sudo -u postgres psql "${conexao_pg[@]}" -X -At \
    -v ON_ERROR_STOP=1 -d astuti_monte_sereno_dev \
    -c "SELECT (SELECT count(*) FROM public.schools)
       + (SELECT count(*) FROM public.school_years)
       + (SELECT count(*) FROM public.school_classes)
       + (SELECT count(*) FROM public.students)
       + (SELECT count(*) FROM public.student_enrollments)
       + (SELECT count(*) FROM public.audit_logs);")"
if [[ "$central_vazio" != '0' || "$municipal_vazio" != '0' ]]; then
    echo 'ERRO: destinos originais nao estao vazios. Operacao interrompida.' >&2
    exit 1
fi

mkdir -p -- "$raiz"
backup_dir="$(mktemp -d -- "$raiz/pgsql-pre-copia-XXXXXXXX")"
trap 'codigo=$?; printf "ERRO: backup interrompido (codigo %s). Arquivos existentes em %s preservados.\n" "$codigo" "$backup_dir" >&2' ERR

printf '\n===== BACKUPS NOVOS EM %s =====\n' "$backup_dir"
for banco in astuti_platform_dev astuti_monte_sereno_dev; do
    arquivo="$backup_dir/$banco.dump"
    sudo -u postgres pg_dump "${conexao_pg[@]}" --format=custom \
        --dbname="$banco" > "$arquivo"
    test -s "$arquivo"
    pg_restore --list "$arquivo" >/dev/null
    printf 'OK: %s\n' "$arquivo"
done

(
    cd "$backup_dir"
    sha256sum astuti_platform_dev.dump astuti_monte_sereno_dev.dump > SHA256SUMS
    sha256sum -c SHA256SUMS
)

printf '\n===== ENSAIANDO RESTAURACAO DOS BACKUPS ATUAIS =====\n'
bash "$roteiro_restauracao" --backup-dir "$backup_dir"

printf '\n===== BACKUPS PRE-COPIA E RESTAURACAO CONFERIDOS =====\n'
printf 'Local: %s\n' "$backup_dir"
printf 'Nenhuma tabela dos bancos PostgreSQL originais foi alterada.\n'
