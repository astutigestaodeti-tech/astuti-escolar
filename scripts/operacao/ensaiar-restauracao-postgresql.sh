#!/usr/bin/env bash
# Restaura copias PostgreSQL anteriores a migration jobs em bancos NOVOS.
# Nunca sobrescreve ou remove bancos existentes. Preserva copias em caso de erro.
set -Eeuo pipefail
umask 077

if [[ $# -eq 0 ]]; then
    origem="$HOME/astuti-backups/pgsql-antes-jobs-2Xqrc9lg"
elif [[ $# -eq 2 && "$1" == '--backup-dir' ]]; then
    origem="$2"
else
    echo 'Uso: bash scripts/operacao/ensaiar-restauracao-postgresql.sh [--backup-dir PASTA]' >&2
    exit 2
fi

central="$origem/astuti_platform_dev.dump"
municipal="$origem/astuti_monte_sereno_dev.dump"

trap 'codigo=$?; printf "ERRO: ensaio interrompido (codigo %s). Bancos de teste ja criados foram preservados.\n" "$codigo" >&2' ERR

printf '\n===== VERIFICANDO BACKUPS =====\n'
for arquivo in "$central" "$municipal"; do
    if [[ ! -s "$arquivo" ]]; then
        printf 'ERRO: backup ausente ou vazio: %s\n' "$arquivo" >&2
        exit 1
    fi
    pg_restore --list "$arquivo" >/dev/null
    printf 'OK: %s\n' "$arquivo"
done

# Apenas o cluster PostgreSQL local por socket. Nenhuma conexao remota.
sudo -v
sudo -u postgres pg_isready -q

sufixo="$(date +%Y%m%d_%H%M%S)_$RANDOM"
banco_central="astuti_restore_pg_platform_$sufixo"
banco_municipal="astuti_restore_pg_monte_$sufixo"

[[ "$banco_central" =~ ^astuti_restore_pg_platform_[0-9]{8}_[0-9]{6}_[0-9]+$ ]]
[[ "$banco_municipal" =~ ^astuti_restore_pg_monte_[0-9]{8}_[0-9]{6}_[0-9]+$ ]]

printf '\n===== NOVOS BANCOS EXCLUSIVOS DE TESTE =====\n'
printf 'Plataforma: %s\nMonte Sereno: %s\n' "$banco_central" "$banco_municipal"

printf '\n===== RESTAURANDO PLATAFORMA =====\n'
sudo -u postgres createdb --template=template0 "$banco_central"
sudo -u postgres pg_restore \
    --no-owner --no-privileges --single-transaction --exit-on-error \
    --dbname="$banco_central" < "$central"

printf '\n===== RESTAURANDO MONTE SERENO =====\n'
sudo -u postgres createdb --template=template0 "$banco_municipal"
sudo -u postgres pg_restore \
    --no-owner --no-privileges --single-transaction --exit-on-error \
    --dbname="$banco_municipal" < "$municipal"

printf '\n===== CONTAGENS DA RESTAURACAO CENTRAL =====\n'
sudo -u postgres psql -X -v ON_ERROR_STOP=1 -P pager=off \
    -d "$banco_central" -c "
SELECT 'municipalities' AS tabela, count(*) AS registros FROM public.municipalities
UNION ALL SELECT 'users', count(*) FROM public.users
UNION ALL SELECT 'migrations', count(*) FROM public.migrations
ORDER BY tabela;"

printf '\n===== CONTAGENS DA RESTAURACAO MUNICIPAL =====\n'
sudo -u postgres psql -X -v ON_ERROR_STOP=1 -P pager=off \
    -d "$banco_municipal" -c "
SELECT 'schools' AS tabela, count(*) AS registros FROM public.schools
UNION ALL SELECT 'students', count(*) FROM public.students
UNION ALL SELECT 'migrations', count(*) FROM public.migrations
ORDER BY tabela;"

printf '\n===== ENSAIO DE RESTAURACAO CONCLUIDO =====\n'
printf 'Plataforma: %s\nMonte Sereno: %s\n' "$banco_central" "$banco_municipal"
printf 'Bancos originais *_dev e arquivos de backup nao foram alterados.\n'
printf 'Nenhuma copia MariaDB -> PostgreSQL foi executada.\n'
