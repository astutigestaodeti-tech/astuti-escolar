#!/usr/bin/env bash
# Ajusta o desempenho do PHP-FPM, do OPcache e do PostgreSQL ao tamanho da VPS.
#
# Uso (na VPS, dentro do tmux):
#   sudo bash scripts/operacao/vps/ajustar-desempenho.sh                                # so mostra o que faria
#   sudo bash scripts/operacao/vps/ajustar-desempenho.sh --confirmar=AJUSTAR-DESEMPENHO
#   sudo bash scripts/operacao/vps/ajustar-desempenho.sh --desfazer=AJUSTAR-DESEMPENHO
#
# Sem a confirmacao, NADA e alterado: o script mostra os valores atuais e os
# recomendados para a memoria e os processadores desta maquina.
#
# Arquivos proprios do Astuti (nenhum arquivo do sistema e editado):
#   /etc/php/<versao>/fpm/pool.d/zz-astuti-desempenho.conf   (processos do PHP)
#   /etc/php/<versao>/fpm/conf.d/98-astuti-desempenho.ini    (OPcache)
#   /etc/postgresql/<versao>/main/conf.d/astuti-desempenho.conf
#
# Seguranca:
#   - confere a configuracao do PHP-FPM (php-fpm -t) antes de recarregar;
#   - confere que o PHP passou a usar os valores novos (php-fpm -tt);
#   - o PostgreSQL e reiniciado (cerca de 5 segundos sem banco); se nao voltar,
#     o arquivo novo e retirado e o banco volta como estava;
#   - nenhum banco, dado, senha, arquivo de ambiente, firewall ou SSH e tocado.
#
# Registro: /var/log/astuti-ajustar-desempenho.log (so na execucao real).

main() {
    set -Eeuo pipefail
    export LC_ALL=C

    local modo=simular arg
    for arg in "$@"; do
        case "$arg" in
            --confirmar=AJUSTAR-DESEMPENHO) modo=aplicar ;;
            --desfazer=AJUSTAR-DESEMPENHO) modo=desfazer ;;
            --simular) modo=simular ;;
            *) printf 'uso: sudo bash %s [--simular | --confirmar=AJUSTAR-DESEMPENHO | --desfazer=AJUSTAR-DESEMPENHO]\n' "$0" >&2; return 2 ;;
        esac
    done

    erro() { printf '\nERRO: %s\n' "$*" >&2; exit 1; }
    passo() { printf '\n===== %s =====\n' "$*"; }
    [[ "$(id -u)" -eq 0 ]] || erro 'rode com sudo (o script le e grava configuracoes do sistema).'

    # ---------- O que existe nesta maquina ----------
    local versao_php versao_pg memoria_mb cpus php_fpm servico_fpm pg_conf_d
    versao_php="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null)" || erro 'PHP nao encontrado.'
    php_fpm="php-fpm${versao_php}"
    servico_fpm="php${versao_php}-fpm"
    command -v "$php_fpm" >/dev/null || erro "$php_fpm nao encontrado."
    versao_pg="$(pg_lsclusters -h 2>/dev/null | awk '$2 == "main" { print $1; exit }')"
    [[ -n "$versao_pg" ]] || erro 'cluster PostgreSQL "main" nao encontrado.'
    pg_conf_d="/etc/postgresql/${versao_pg}/main/conf.d"
    grep -Eq "^[[:space:]]*include_dir[[:space:]]*=[[:space:]]*'conf.d'" "/etc/postgresql/${versao_pg}/main/postgresql.conf" \
        || erro "o postgresql.conf nao inclui a pasta conf.d; nada foi alterado."
    memoria_mb="$(awk '/^MemTotal:/ { printf "%d", $2 / 1024 }' /proc/meminfo)"
    cpus="$(nproc)"

    local arq_pool="/etc/php/${versao_php}/fpm/pool.d/zz-astuti-desempenho.conf"
    local arq_opcache="/etc/php/${versao_php}/fpm/conf.d/98-astuti-desempenho.ini"
    local arq_pg="${pg_conf_d}/astuti-desempenho.conf"

    if [[ "$modo" == desfazer ]]; then
        passo 'DESFAZENDO O AJUSTE DE DESEMPENHO'
        exec > >(tee -a /var/log/astuti-ajustar-desempenho.log) 2>&1
        rm -f "$arq_pool" "$arq_opcache"
        "$php_fpm" -t || erro 'a configuracao do PHP-FPM ficou invalida; confira os arquivos em pool.d.'
        systemctl reload "$servico_fpm"
        if [[ -f "$arq_pg" ]]; then
            rm -f "$arq_pg"
            pg_ctlcluster "$versao_pg" main restart || erro 'o PostgreSQL nao reiniciou; rode: sudo systemctl status postgresql'
        fi
        printf 'Pronto: PHP e PostgreSQL voltaram aos valores padrao do Debian.\n'
        return 0
    fi

    # ---------- Valores recomendados ----------
    # PHP: cada processo do Laravel usa cerca de 60 MB; reserva 35% da memoria para eles,
    # sem passar de 8 por processador (acima disso o processador vira o gargalo).
    local filhos inicial ocioso_min ocioso_max
    filhos=$(( memoria_mb * 35 / 100 / 60 ))
    (( filhos > cpus * 8 )) && filhos=$(( cpus * 8 ))
    (( filhos < 5 )) && filhos=5
    inicial=$(( cpus * 2 )); (( inicial > filhos )) && inicial=$filhos
    ocioso_min=$(( cpus )); (( ocioso_min < 2 )) && ocioso_min=2
    ocioso_max=$(( cpus * 3 )); (( ocioso_max > filhos )) && ocioso_max=$filhos
    (( inicial < ocioso_min )) && inicial=$ocioso_min
    (( inicial > ocioso_max )) && inicial=$ocioso_max

    # PostgreSQL: 25% da memoria para o cache proprio, 50% como estimativa do cache do sistema.
    local shared effective manutencao
    shared=$(( memoria_mb / 4 ))
    effective=$(( memoria_mb / 2 ))
    manutencao=$(( memoria_mb / 32 )); (( manutencao > 512 )) && manutencao=512; (( manutencao < 64 )) && manutencao=64

    local conteudo_pool conteudo_opcache conteudo_pg
    conteudo_pool="; Astuti: processos do PHP para ${memoria_mb} MB e ${cpus} processadores.
; Mesmo nome de pool: os valores abaixo substituem os do www.conf.
[www]
pm = dynamic
pm.max_children = ${filhos}
pm.start_servers = ${inicial}
pm.min_spare_servers = ${ocioso_min}
pm.max_spare_servers = ${ocioso_max}
pm.max_requests = 500"
    conteudo_opcache="; Astuti: codigo PHP compilado fica em memoria.
opcache.enable = 1
opcache.memory_consumption = 192
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 1
opcache.revalidate_freq = 2
realpath_cache_size = 4096K
realpath_cache_ttl = 600"
    conteudo_pg="# Astuti: memoria do PostgreSQL para ${memoria_mb} MB e ${cpus} processadores.
shared_buffers = ${shared}MB
effective_cache_size = ${effective}MB
work_mem = 16MB
maintenance_work_mem = ${manutencao}MB
random_page_cost = 1.1
effective_io_concurrency = 200
jit = off"

    # ---------- Diagnostico (sempre, somente leitura) ----------
    passo "MAQUINA: ${memoria_mb} MB de memoria, ${cpus} processadores, PHP ${versao_php}, PostgreSQL ${versao_pg}"
    passo 'PHP-FPM AGORA'
    "$php_fpm" -tt 2>&1 | grep -E 'pm(\.max_children|\.start_servers|\.min_spare_servers|\.max_spare_servers|\.max_requests)? =' | sed 's/^.*NOTICE: */  /' || true
    printf '  OPcache: %s\n' "$("$php_fpm" -i 2>/dev/null | awk -F' => ' '/^opcache.(enable|memory_consumption) =>/ { printf "%s=%s ", $1, $2 }')"
    passo 'POSTGRESQL AGORA'
    local nome
    for nome in shared_buffers effective_cache_size work_mem maintenance_work_mem random_page_cost jit max_connections; do
        printf '  %s = %s\n' "$nome" "$(runuser -u postgres -- psql -XAtqc "SHOW ${nome}" 2>/dev/null || echo '?')"
    done
    passo 'RECOMENDADO PARA ESTA MAQUINA'
    printf '  PHP: ate %s processos ao mesmo tempo (hoje: %s)\n' "$filhos" "$("$php_fpm" -tt 2>&1 | awk -F' = ' '/pm\.max_children =/ { print $2; exit }')"
    printf '  PostgreSQL: shared_buffers %s MB, effective_cache_size %s MB, work_mem 16 MB, jit desligado\n' "$shared" "$effective"
    printf '  Conexoes: %s processos x 2 bancos = ate %s conexoes, abaixo do limite do PostgreSQL\n' "$filhos" "$(( filhos * 2 ))"

    if [[ "$modo" == simular ]]; then
        printf '\nNada foi alterado. Para aplicar (o banco fica cerca de 5 segundos fora do ar):\n'
        printf '  sudo bash %s --confirmar=AJUSTAR-DESEMPENHO\n' "$0"
        return 0
    fi

    # ---------- Aplicacao ----------
    exec > >(tee -a /var/log/astuti-ajustar-desempenho.log) 2>&1
    printf '\n[%s] aplicando ajuste de desempenho\n' "$(date '+%d/%m/%Y %H:%M:%S')"

    passo 'PHP-FPM E OPCACHE'
    printf '%s\n' "$conteudo_pool" >"$arq_pool"
    printf '%s\n' "$conteudo_opcache" >"$arq_opcache"
    if ! "$php_fpm" -t; then
        rm -f "$arq_pool" "$arq_opcache"
        erro 'o PHP-FPM recusou a configuracao nova; os arquivos foram retirados e nada mudou.'
    fi
    if ! "$php_fpm" -tt 2>&1 | grep -Eq "pm\.max_children = ${filhos}\$"; then
        rm -f "$arq_pool" "$arq_opcache"
        erro 'o PHP-FPM nao aplicou o novo numero de processos; os arquivos foram retirados e nada mudou.'
    fi
    systemctl reload "$servico_fpm"
    printf '  PHP-FPM recarregado: ate %s processos.\n' "$filhos"

    passo 'POSTGRESQL (reinicio rapido)'
    local anterior=''
    [[ -f "$arq_pg" ]] && anterior="$(cat "$arq_pg")"
    printf '%s\n' "$conteudo_pg" >"$arq_pg"
    chown postgres:postgres "$arq_pg"
    if ! pg_ctlcluster "$versao_pg" main restart; then
        if [[ -n "$anterior" ]]; then printf '%s\n' "$anterior" >"$arq_pg"; else rm -f "$arq_pg"; fi
        pg_ctlcluster "$versao_pg" main restart || true
        erro 'o PostgreSQL nao aceitou os valores novos; o arquivo foi desfeito e o banco reiniciado como estava.'
    fi
    printf '  shared_buffers agora: %s\n' "$(runuser -u postgres -- psql -XAtqc 'SHOW shared_buffers')"

    passo 'CONFERENCIA FINAL'
    systemctl is-active --quiet "$servico_fpm" && printf '  PHP-FPM ativo.\n' || erro "o $servico_fpm nao esta ativo; rode: sudo systemctl status $servico_fpm"
    runuser -u postgres -- psql -XAtqc 'SELECT 1' >/dev/null && printf '  PostgreSQL respondendo.\n' || erro 'o PostgreSQL nao responde.'
    printf '\nPronto. Para medir: python3 scripts/operacao/vps/teste-de-carga.py --help\n'
    printf 'Para voltar ao padrao: sudo bash %s --desfazer=AJUSTAR-DESEMPENHO\n' "$0"
}

main "$@"
exit
