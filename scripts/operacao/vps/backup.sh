#!/usr/bin/env bash
# Backup do Astuti: os bancos desta instalacao e os arquivos privados (fotos,
# anexos), conferidos antes de serem dados como prontos.
#
# Uso (na VPS, na pasta do projeto):
#   sudo bash scripts/operacao/vps/backup.sh                                  # so mostra o que faria
#   sudo bash scripts/operacao/vps/backup.sh --confirmar=FAZER-BACKUP
#   sudo bash scripts/operacao/vps/backup.sh --confirmar=FAZER-BACKUP --manter=14
#   sudo bash scripts/operacao/vps/backup.sh --confirmar=FAZER-BACKUP --criptografar=/etc/astuti/backup-chave-publica.txt
#   sudo bash scripts/operacao/vps/backup.sh --agendar=AGENDAR-BACKUP [--manter=14] [--criptografar=...] [--grupo-leitura=GRUPO]
#   sudo bash scripts/operacao/vps/backup.sh --desagendar=AGENDAR-BACKUP
#
# Cada backup fica numa pasta propria, AAAA-MM-DD_HHMMSS, em /var/backups/astuti
# (ou --destino=PASTA), acessivel so pelo root:
#   <rotulo>.dump           um por banco (pg_dump formato custom)
#   arquivos-privados.tar.gz storage/app/private
#   CONTAGENS-<rotulo>.txt  linhas por tabela no momento do backup
#   MANIFESTO.txt           data, versao do sistema e conteudo
#   SHA256SUMS              para conferir a integridade depois
#
# Conferencias: espaco livre antes de comecar; pg_restore --list em cada banco;
# tar -t nos arquivos; somas SHA-256. Um backup com qualquer falha fica com o
# nome terminado em .incompleto e o script sai com erro.
#
# Criptografia (recomendada): com --criptografar=ARQUIVO, cada arquivo e
# cifrado com o age para a chave PUBLICA do arquivo. A chave privada fica fora
# da VPS (no cofre da equipe); sem ela, o backup nao pode ser lido.
#
# Copia para fora da VPS: com --criptografar, a opcao --grupo-leitura=GRUPO
# deixa os backups legiveis (nunca gravaveis) pelo grupo informado, para que o
# notebook da equipe os baixe com rsync sem sudo. Backup sem criptografia nunca
# fica legivel por ninguem alem do root.
#
# Retencao: --manter=N (minimo 3) apaga as pastas de backup mais antigas, so
# as completas, criadas por este script, e nunca as N mais novas.
#
# Nao altera bancos, .env, senhas, SSH nem firewall. Nao copia o .env.
# Registro: /var/log/astuti-backup.log (so na execucao real).

# Global: se algo falhar depois de criada, a pasta e marcada como .incompleto.
PASTA_EM_ANDAMENTO=''

main() {
    set -Eeuo pipefail
    export LC_ALL=C
    umask 077

    local modo=simular destino=/var/backups/astuti manter=0 chave='' grupo='' arg
    for arg in "$@"; do
        case "$arg" in
            --confirmar=FAZER-BACKUP) modo=backup ;;
            --agendar=AGENDAR-BACKUP) modo=agendar ;;
            --desagendar=AGENDAR-BACKUP) modo=desagendar ;;
            --simular) modo=simular ;;
            --manter=*) manter="${arg#--manter=}" ;;
            --destino=*) destino="${arg#--destino=}" ;;
            --criptografar=*) chave="${arg#--criptografar=}" ;;
            --grupo-leitura=*) grupo="${arg#--grupo-leitura=}" ;;
            *) uso; return 2 ;;
        esac
    done

    erro() { printf '\nERRO: %s\n' "$*" >&2; exit 1; }
    passo() { printf '\n===== %s =====\n' "$*"; }

    [[ "$manter" =~ ^[0-9]+$ ]] || erro '--manter precisa ser um numero.'
    if (( manter != 0 && manter < 3 )); then erro '--manter precisa ser 0 (nao apagar) ou pelo menos 3.'; fi
    [[ "$destino" == /* && "$destino" != / && "$destino" != *..* ]] || erro '--destino precisa ser uma pasta absoluta.'
    if [[ -n "$chave" ]]; then
        [[ -f "$chave" ]] || erro "chave publica nao encontrada: $chave"
        grep -Eq '^age1[0-9a-z]{58}$' "$chave" || erro 'o arquivo da chave precisa ter so chaves publicas do age (age1...).'
        ! grep -q 'AGE-SECRET-KEY' "$chave" || erro 'esse arquivo tem uma chave PRIVADA. Na VPS fica so a chave publica.'
    fi
    if [[ -n "$grupo" ]]; then
        [[ -n "$chave" ]] || erro '--grupo-leitura so com --criptografar: backup aberto nunca fica legivel por outra conta.'
        if [[ ! "$grupo" =~ ^[a-z_][a-z0-9_-]*$ ]] || ! getent group "$grupo" >/dev/null; then erro "grupo inexistente: $grupo"; fi
        [[ "$grupo" != root ]] || erro 'informe o grupo do usuario que vai baixar os backups.'
    fi

    local raiz dono
    raiz="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
    [[ -f "$raiz/artisan" ]] || erro 'rode o script de dentro da pasta do projeto.'

    if [[ "$modo" == simular ]]; then
        printf 'SIMULACAO: nada sera alterado.\n'
    fi

    if [[ "$modo" == agendar || "$modo" == desagendar ]]; then
        agendamento "$modo" "$raiz" "$destino" "$manter" "$chave" "$grupo"
        return 0
    fi

    [[ "$(id -u)" -eq 0 ]] || erro 'rode com sudo (o backup usa a conta postgres e grava em pasta protegida).'
    command -v pg_dump >/dev/null || erro 'pg_dump nao encontrado.'
    command -v pg_restore >/dev/null || erro 'pg_restore nao encontrado.'
    if [[ -n "$chave" ]]; then command -v age >/dev/null || erro 'age nao instalado (sudo apt install age).'; fi
    dono="$(stat -c %U "$raiz")"
    [[ "$dono" != root ]] || erro 'a pasta do projeto pertence ao root; esperado o usuario da instalacao.'

    passo 'BANCOS DESTA INSTALACAO'
    local lista
    lista="$(cd "$raiz" && runuser -u "$dono" -- php artisan astuti:bancos 2>&1)" || erro "nao foi possivel listar os bancos: $lista"
    local -a rotulos=() bancos=()
    local rotulo banco
    while read -r rotulo banco; do
        [[ "$rotulo" =~ ^[a-z0-9-]+$ && "$banco" =~ ^[A-Za-z0-9_]+$ ]] || erro "linha inesperada de astuti:bancos: $rotulo $banco"
        rotulos+=("$rotulo")
        bancos+=("$banco")
    done <<< "$lista"
    (( ${#bancos[@]} > 0 )) || erro 'nenhum banco para copiar.'

    local i total_bytes=0 tamanho
    for i in "${!bancos[@]}"; do
        tamanho="$(runuser -u postgres -- psql -X -At -v ON_ERROR_STOP=1 -d postgres \
            -v banco="${bancos[$i]}" <<< "SELECT pg_database_size(:'banco');" 2>/dev/null)" \
            || erro "o banco ${bancos[$i]} nao existe ou nao pode ser lido pela conta postgres."
        total_bytes=$(( total_bytes + tamanho ))
        printf '  %-28s %s (%s MB)\n' "${rotulos[$i]}" "${bancos[$i]}" "$(( tamanho / 1048576 ))"
    done

    local privados="$raiz/storage/app/private" privados_bytes=0
    if [[ -d "$privados" ]]; then
        privados_bytes="$(du -sb "$privados" | cut -f1)"
    fi
    printf '  %-28s %s MB\n' 'arquivos privados' "$(( privados_bytes / 1048576 ))"

    passo 'ESPACO'
    local precisa livre pai="$destino"
    while [[ ! -d "$pai" ]]; do pai="$(dirname "$pai")"; done
    precisa=$(( (total_bytes + privados_bytes) * 2 + 104857600 ))
    livre="$(df -PB1 "$pai" | awk 'NR == 2 { print $4 }')"
    printf '  livre em %s: %s MB; reservado para o backup: %s MB\n' "$pai" "$(( livre / 1048576 ))" "$(( precisa / 1048576 ))"
    (( livre > precisa )) || erro 'espaco insuficiente. Nada foi gravado.'

    if [[ "$modo" == simular ]]; then
        passo 'O QUE SERIA FEITO'
        printf '  pasta nova em %s/%s\n' "$destino" "$(date +%F_%H%M%S)"
        printf '  um .dump por banco, conferido com pg_restore --list\n'
        printf '  arquivos privados em tar.gz, conferidos com tar -t\n'
        printf '  criptografia: %s\n' "$([[ -n "$chave" ]] && echo "sim (age, chave publica em $chave)" || echo 'NAO (recomendado usar --criptografar)')"
        printf '  retencao: %s\n' "$( (( manter > 0 )) && echo "manter os $manter mais novos" || echo 'nao apagar nenhum backup antigo')"
        printf '\nFim da simulacao. Nada foi alterado.\n'
        printf 'Para fazer o backup: sudo bash scripts/operacao/vps/backup.sh --confirmar=FAZER-BACKUP\n'
        return 0
    fi

    exec 9>/run/astuti-backup.lock
    flock -n 9 || erro 'outro backup esta em andamento.'
    exec > >(tee -a /var/log/astuti-backup.log) 2>&1
    printf '\n##### backup iniciado em %s #####\n' "$(date '+%F %T %z')"

    install -d -m 700 -o root -g root "$destino"
    local nome pasta
    nome="$(date +%F_%H%M%S)"
    pasta="$destino/$nome"
    [[ ! -e "$pasta" ]] || erro "a pasta $pasta ja existe."
    install -d -m 700 -o root -g root "$pasta"
    PASTA_EM_ANDAMENTO="$pasta"
    trap 'falhou' EXIT

    passo 'COPIANDO OS BANCOS'
    for i in "${!bancos[@]}"; do
        rotulo="${rotulos[$i]}"
        banco="${bancos[$i]}"
        printf '  %s ...\n' "$rotulo"
        contar_linhas "$banco" > "$pasta/CONTAGENS-$rotulo.txt"
        runuser -u postgres -- pg_dump -Fc -Z 6 -d "$banco" > "$pasta/$rotulo.dump"
        pg_restore --list "$pasta/$rotulo.dump" > /dev/null
        printf '    ok: %s KB, %s tabelas\n' "$(( $(stat -c %s "$pasta/$rotulo.dump") / 1024 ))" "$(wc -l < "$pasta/CONTAGENS-$rotulo.txt")"
    done

    passo 'COPIANDO OS ARQUIVOS PRIVADOS'
    if [[ -d "$privados" ]]; then
        tar -C "$raiz/storage/app" -czf "$pasta/arquivos-privados.tar.gz" private
        tar -tzf "$pasta/arquivos-privados.tar.gz" > /dev/null
        printf '  ok: %s arquivos\n' "$(tar -tzf "$pasta/arquivos-privados.tar.gz" | grep -vc '/$' || true)"
    else
        printf '  nao ha arquivos privados nesta instalacao.\n'
    fi

    passo 'MANIFESTO'
    {
        printf 'astuti-backup 1\n'
        printf 'data: %s\n' "$(date '+%F %T %z')"
        printf 'versao do sistema: %s\n' "$(runuser -u "$dono" -- git -C "$raiz" rev-parse --short HEAD 2>/dev/null || echo desconhecida)"
        printf 'criptografado: %s\n' "$([[ -n "$chave" ]] && echo sim || echo nao)"
        for i in "${!bancos[@]}"; do printf 'banco: %s %s\n' "${rotulos[$i]}" "${bancos[$i]}"; done
    } > "$pasta/MANIFESTO.txt"

    if [[ -n "$chave" ]]; then
        passo 'CRIPTOGRAFANDO'
        local arquivo
        for arquivo in "$pasta"/*.dump "$pasta"/*.tar.gz "$pasta"/CONTAGENS-*.txt; do
            [[ -e "$arquivo" ]] || continue
            age -R "$chave" -o "$arquivo.age" "$arquivo"
            [[ -s "$arquivo.age" ]]
            rm -f -- "$arquivo"
        done
        printf '  ok: so quem tem a chave privada consegue ler este backup.\n'
    fi

    local somas
    somas="$(cd "$pasta" && find . -maxdepth 1 -type f -printf '%f\n' | sort | xargs sha256sum)"
    printf '%s\n' "$somas" > "$pasta/SHA256SUMS"
    (cd "$pasta" && sha256sum --quiet -c SHA256SUMS)
    PASTA_EM_ANDAMENTO=''
    trap - EXIT
    if [[ -n "$grupo" && -n "$chave" ]]; then
        chgrp "$grupo" "$destino"
        chmod 750 "$destino"
        chgrp -R "$grupo" "$pasta"
        chmod 750 "$pasta"
        find "$pasta" -type f -exec chmod 640 {} +
    fi
    date '+%F %T %z' > "$destino/ULTIMO-BACKUP-OK"
    chmod 644 "$destino/ULTIMO-BACKUP-OK"
    printf '  pronto: %s (%s MB)\n' "$pasta" "$(( $(du -sb "$pasta" | cut -f1) / 1048576 ))"

    if (( manter > 0 )); then
        passo "RETENCAO: MANTER OS $manter MAIS NOVOS"
        local -a completos=()
        mapfile -t completos < <(find "$destino" -mindepth 1 -maxdepth 1 -type d -regextype posix-extended \
            -regex '.*/[0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{6}' -printf '%f\n' | sort -r)
        local antigo n=0
        for antigo in "${completos[@]}"; do
            n=$(( n + 1 ))
            (( n > manter )) || continue
            head -1 "$destino/$antigo/MANIFESTO.txt" 2>/dev/null | grep -qx 'astuti-backup 1' || continue
            [[ -f "$destino/$antigo/SHA256SUMS" ]] || continue
            rm -rf -- "${destino:?}/$antigo"
            printf '  removido: %s\n' "$antigo"
        done
    fi

    local incompletos
    incompletos="$(find "$destino" -mindepth 1 -maxdepth 1 -type d -name '*.incompleto' | wc -l)"
    if (( incompletos > 0 )); then
        printf '\nATENCAO: ha %s backup(s) incompleto(s) em %s. Veja o registro e apague-os depois de entender a falha.\n' "$incompletos" "$destino"
    fi
    printf '\nBackup concluido. Lembre-se: so vale como backup depois de copiado para fora desta VPS e ensaiado.\n'
}

uso() {
    printf 'uso: sudo bash %s [--simular | --confirmar=FAZER-BACKUP | --agendar=AGENDAR-BACKUP | --desagendar=AGENDAR-BACKUP]\n' "$0" >&2
    printf '     opcoes: --manter=N  --destino=PASTA  --criptografar=ARQUIVO_DA_CHAVE_PUBLICA  --grupo-leitura=GRUPO\n' >&2
}

# Linhas de cada tabela do esquema public, para conferir a restauracao.
contar_linhas() {
    runuser -u postgres -- psql -X -At -F ' ' -v ON_ERROR_STOP=1 -d "$1" <<'SQL'
SELECT c.relname,
       (xpath('/row/n/text()', query_to_xml(format('SELECT count(*) AS n FROM public.%I', c.relname), false, true, '')))[1]::text
FROM pg_class c
JOIN pg_namespace s ON s.oid = c.relnamespace
WHERE s.nspname = 'public' AND c.relkind IN ('r', 'p')
ORDER BY c.relname;
SQL
}

falhou() {
    local status=$?
    if [[ -n "$PASTA_EM_ANDAMENTO" && -d "$PASTA_EM_ANDAMENTO" ]]; then
        mv -- "$PASTA_EM_ANDAMENTO" "$PASTA_EM_ANDAMENTO.incompleto"
        printf '\nFALHA: o backup NAO foi concluido. A pasta ficou como %s.incompleto para analise.\n' "$PASTA_EM_ANDAMENTO" >&2
        (( status != 0 )) || status=1
    fi
    exit "$status"
}

agendamento() {
    local modo="$1" raiz="$2" destino="$3" manter="$4" chave="$5" grupo="$6"
    local arquivo=/etc/cron.d/astuti-backup
    [[ "$(id -u)" -eq 0 ]] || erro 'rode com sudo para agendar.'
    if [[ "$modo" == desagendar ]]; then
        if [[ -f "$arquivo" ]] && grep -q '^# astuti-backup' "$arquivo"; then
            rm -f -- "$arquivo"
            printf 'Agendamento removido. Os backups ja feitos continuam em %s.\n' "$destino"
        else
            printf 'Nao ha agendamento do Astuti neste servidor.\n'
        fi
        return 0
    fi
    [[ "$raiz" =~ ^[A-Za-z0-9/._-]+$ && "$destino" =~ ^[A-Za-z0-9/._-]+$ ]] || erro 'pastas com caracteres inesperados para o agendamento.'
    if [[ -n "$chave" && ! "$chave" =~ ^/[A-Za-z0-9/._-]+$ ]]; then erro 'para agendar, informe o caminho completo da chave publica (comecando com /).'; fi
    if [[ -e "$arquivo" ]] && ! grep -q '^# astuti-backup' "$arquivo"; then
        erro "$arquivo existe e nao foi criado por este script. Nada foi alterado."
    fi
    local opcoes="--confirmar=FAZER-BACKUP --destino=$destino"
    (( manter > 0 )) && opcoes+=" --manter=$manter"
    [[ -n "$chave" ]] && opcoes+=" --criptografar=$chave"
    [[ -n "$grupo" ]] && opcoes+=" --grupo-leitura=$grupo"
    local temporario
    temporario="$(mktemp)"
    {
        printf '# astuti-backup: criado por scripts/operacao/vps/backup.sh em %s\n' "$(date +%F)"
        printf '# Remover: sudo bash %s/scripts/operacao/vps/backup.sh --desagendar=AGENDAR-BACKUP\n' "$raiz"
        printf 'SHELL=/bin/bash\n'
        printf 'PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin\n'
        printf '37 2 * * * root bash %s/scripts/operacao/vps/backup.sh %s >/dev/null 2>&1\n' "$raiz" "$opcoes"
    } > "$temporario"
    install -m 644 -o root -g root "$temporario" "$arquivo"
    rm -f -- "$temporario"
    printf 'Backup agendado para todo dia as 02:37 (%s).\n' "$arquivo"
    printf 'Opcoes: %s\n' "$opcoes"
    [[ -n "$chave" ]] || printf 'ATENCAO: sem --criptografar, os backups ficam legiveis por quem tiver acesso de root.\n'
    printf 'Acompanhe em /var/log/astuti-backup.log e em %s/ULTIMO-BACKUP-OK.\n' "$destino"
}

main "$@"
