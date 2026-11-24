#!/usr/bin/env bash
# Conferencia SOMENTE LEITURA de uma VPS do Astuti Gestao Escolar.
#
# Uso (na VPS):
#   sudo bash scripts/operacao/vps/verificar-ambiente.sh
#   sudo bash scripts/operacao/vps/verificar-ambiente.sh --detalhes
#   sudo bash scripts/operacao/vps/verificar-ambiente.sh --projeto=/srv/astuti-escolar
#
# Cada item sai como OK, ATENCAO ou FALHA, com o que fazer quando nao estiver
# OK. O resultado pode ser colado na issue: o script nunca imprime senhas,
# tokens, conteudo do .env, IP, nome do servidor, nomes de usuario nem
# enderecos de espelhos do provedor.
#
# Nao grava, nao instala, nao reinicia e nao altera nada. Sem sudo, os itens
# de SSH, firewall e Nginx ficam como ATENCAO ("nao conferido").
#
# Saida: 0 sem FALHA; 1 com alguma FALHA; 2 uso incorreto.
#
# Para os testes automaticos: ASTUTI_VERIFICAR_SEM_REDE=1 pula as consultas
# de internet e ASTUTI_VERIFICAR_SEM_SISTEMA=1 pula os itens do sistema,
# deixando so os do projeto.

main() {
    set -uo pipefail
    export LC_ALL=C

    local projeto='' detalhes=0 arg
    for arg in "$@"; do
        case "$arg" in
            --detalhes) detalhes=1 ;;
            --projeto=*) projeto="${arg#--projeto=}" ;;
            *) printf 'uso: sudo bash %s [--detalhes] [--projeto=PASTA]\n' "$0" >&2; return 2 ;;
        esac
    done
    if [[ -z "$projeto" ]]; then
        projeto="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
    fi

    local n_ok=0 n_atencao=0 n_falha=0
    ok() { printf '  OK       %s\n' "$1"; n_ok=$((n_ok + 1)); }
    atencao() { printf '  ATENCAO  %s\n' "$1"; [[ -n "${2:-}" ]] && printf '           -> %s\n' "$2"; n_atencao=$((n_atencao + 1)); }
    falha() { printf '  FALHA    %s\n' "$1"; [[ -n "${2:-}" ]] && printf '           -> %s\n' "$2"; n_falha=$((n_falha + 1)); }
    info() { [[ "$detalhes" -eq 1 ]] && printf '           %s\n' "$1"; return 0; }
    secao() { printf '\n[%s]\n' "$1"; }
    tem() { command -v "$1" >/dev/null 2>&1; }
    eh_root() { [[ "$(id -u)" -eq 0 ]]; }

    printf 'Astuti Gestao Escolar - conferencia do servidor (somente leitura)\n'
    printf 'Data: %s\n' "$(date '+%d/%m/%Y %H:%M %Z')"
    eh_root || printf 'Rodando sem sudo: SSH, firewall e Nginx nao serao conferidos.\n'

    if [[ "${ASTUTI_VERIFICAR_SEM_SISTEMA:-0}" != 1 ]]; then
        verificar_sistema
        verificar_rede
        verificar_programas
        verificar_servicos
        verificar_acesso
    fi
    verificar_projeto "$projeto"
    verificar_backup

    printf '\nResumo: %d OK, %d ATENCAO, %d FALHA\n' "$n_ok" "$n_atencao" "$n_falha"
    if [[ "$n_falha" -gt 0 ]]; then
        printf 'Ha FALHA: resolva antes de entregar o servidor ou colocar dados reais.\n'
        return 1
    fi
    return 0
}

verificar_sistema() {
    secao 'Sistema'
    local versao mem_mb swap_mb uso fuso
    # shellcheck source=/dev/null
    versao="$(. /etc/os-release 2>/dev/null && printf '%s' "${PRETTY_NAME:-desconhecido}")"
    if [[ "$versao" == *Debian* ]]; then ok "Sistema: $versao"; else atencao "Sistema: ${versao:-desconhecido}" 'o procedimento oficial foi testado no Debian 13.'; fi
    info "Processadores: $(nproc 2>/dev/null || printf '?')"

    mem_mb="$(awk '/^MemTotal:/ {print int($2/1024)}' /proc/meminfo 2>/dev/null)"
    if [[ "${mem_mb:-0}" -ge 1800 ]]; then ok "Memoria: ${mem_mb} MB"; else atencao "Memoria: ${mem_mb:-?} MB" 'recomendado 2 GB ou mais.'; fi

    swap_mb="$(awk '/^SwapTotal:/ {print int($2/1024)}' /proc/meminfo 2>/dev/null)"
    if [[ "${swap_mb:-0}" -gt 0 ]]; then ok "Swap: ${swap_mb} MB"; else atencao 'Swap: nenhuma' 'crie um arquivo de swap (o npm run build pode ficar sem memoria).'; fi

    uso="$(df -P / 2>/dev/null | awk 'NR==2 {gsub("%","",$5); print $5}')"
    if [[ -z "$uso" ]]; then atencao 'Disco: nao foi possivel ler'
    elif [[ "$uso" -ge 90 ]]; then falha "Disco: ${uso}% usado" 'libere espaco: banco e backups param com o disco cheio.'
    elif [[ "$uso" -ge 80 ]]; then atencao "Disco: ${uso}% usado" 'planeje liberar espaco ou ampliar o disco.'
    else ok "Disco: ${uso}% usado"; fi

    fuso="$(timedatectl show -p Timezone --value 2>/dev/null || cat /etc/timezone 2>/dev/null)"
    if [[ "$fuso" == America/Sao_Paulo ]]; then ok 'Fuso: America/Sao_Paulo'; else atencao "Fuso: ${fuso:-desconhecido}" 'sudo timedatectl set-timezone America/Sao_Paulo'; fi

    if locale -a 2>/dev/null | grep -qiE '^pt_BR\.utf-?8$'; then ok 'Locale pt_BR.UTF-8 disponivel'; else atencao 'Locale pt_BR.UTF-8 ausente' 'habilite em /etc/locale.gen e rode locale-gen.'; fi
}

verificar_rede() {
    secao 'Rede'
    if ip -4 route show default 2>/dev/null | grep -q .; then ok 'Rota IPv4 padrao presente'; else falha 'Sem rota IPv4 padrao' 'o servidor nao alcanca a internet.'; fi
    if ip -6 route show default 2>/dev/null | grep -q .; then info 'Rota IPv6 padrao: presente'; else info 'Rota IPv6 padrao: ausente (use Acquire::ForceIPv4 no APT)'; fi

    if [[ "${ASTUTI_VERIFICAR_SEM_REDE:-0}" == 1 ]]; then
        atencao 'Acesso a internet nao conferido (modo de teste)'
        return 0
    fi
    local destino enderecos endereco falhou
    for destino in github.com deb.debian.org; do
        enderecos="$(getent ahostsv4 "$destino" 2>/dev/null | awk '{print $1}' | sort -u)"
        if [[ -z "$enderecos" ]]; then falha "DNS nao resolve $destino" 'confira /etc/resolv.conf.'; continue; fi
        falhou=0
        while read -r endereco; do
            if curl -s -o /dev/null --max-time 10 --resolve "${destino}:443:${endereco}" "https://${destino}/"; then
                info "$destino via $endereco: responde"
            else
                info "$destino via $endereco: SEM RESPOSTA"
                falhou=$((falhou + 1))
            fi
        done <<<"$enderecos"
        if [[ "$falhou" -eq 0 ]]; then ok "HTTPS para $destino"
        elif [[ "$falhou" -lt "$(wc -l <<<"$enderecos")" ]]; then atencao "HTTPS para $destino falha por $falhou endereco(s)" 'rode com --detalhes e abra chamado no provedor com a lista.'
        else falha "HTTPS para $destino nao responde" 'atualizacoes e instalacao nao vao funcionar.'; fi
    done

    if [[ "$detalhes" -eq 1 ]]; then
        local fontes
        fontes="$(grep -rhoE 'https?://[^ ]+' /etc/apt/sources.list /etc/apt/sources.list.d/ 2>/dev/null | sort -u)"
        while read -r fonte; do
            [[ -z "$fonte" ]] && continue
            case "$fonte" in
                *deb.debian.org*|*security.debian.org*|*packages.sury.org*|*apt.postgresql.org*|*deb.nodesource.com*|*pkg.cloudflare.com*) info "APT: $fonte" ;;
                *) info 'APT: espelho do provedor (endereco omitido)' ;;
            esac
        done <<<"$fontes"
    fi
}

verificar_programas() {
    secao 'Programas'
    local versao faltando modulo modulos
    if tem php; then
        versao="$(php -r 'echo PHP_VERSION;' 2>/dev/null)"
        if php -r 'exit(version_compare(PHP_VERSION, "8.4.1", ">=") ? 0 : 1);' 2>/dev/null; then ok "PHP $versao"; else falha "PHP $versao" 'o sistema exige PHP 8.4.1 ou mais novo.'; fi
        modulos="$(php -m 2>/dev/null | tr '[:upper:]' '[:lower:]')"
        faltando=''
        for modulo in ctype curl dom exif fileinfo gd iconv intl mbstring openssl pdo_pgsql pgsql tokenizer xml xmlreader zip; do
            grep -qx "$modulo" <<<"$modulos" || faltando="$faltando $modulo"
        done
        if [[ -z "$faltando" ]]; then ok 'Modulos PHP obrigatorios'; else falha "Modulos PHP ausentes:$faltando" "instale os pacotes php${versao%.*}-<modulo> correspondentes."; fi
        verificar_limite_php upload_max_filesize 12
        verificar_limite_php post_max_size 16
    else
        falha 'PHP nao instalado'
    fi

    if tem psql; then
        versao="$(psql --version 2>/dev/null | awk '{print $3}')"
        if [[ "${versao%%.*}" -ge 17 ]] 2>/dev/null; then ok "PostgreSQL (cliente) $versao"; else atencao "PostgreSQL (cliente) $versao" 'o padrao do sistema e o PostgreSQL 17.'; fi
    else
        falha 'PostgreSQL nao instalado'
    fi

    if tem node; then
        versao="$(node --version 2>/dev/null)"
        if [[ "$(sed -E 's/^v([0-9]+).*/\1/' <<<"$versao")" -ge 20 ]] 2>/dev/null; then ok "Node $versao"; else atencao "Node $versao" 'use o Node 24 (NodeSource).'; fi
    else
        atencao 'Node nao instalado' 'necessario para compilar a interface.'
    fi
    if tem npm; then info "npm $(npm --version 2>/dev/null)"; else atencao 'npm nao instalado'; fi
    if tem composer; then info "$(composer --version 2>/dev/null | head -1)"; else falha 'Composer nao instalado'; fi
    if tem nginx; then info "$(nginx -v 2>&1)"; else falha 'Nginx nao instalado'; fi

    if tem tesseract && tesseract --list-langs 2>/dev/null | grep -qx por; then ok 'OCR (tesseract com portugues)'; else atencao 'OCR sem portugues' 'sudo apt-get install -y tesseract-ocr tesseract-ocr-por (leitura de listas em PDF ou foto).'; fi
    if tem pdftoppm && tem pdftotext; then ok 'Leitura de PDF (poppler-utils)'; else atencao 'poppler-utils ausente' 'sudo apt-get install -y poppler-utils'; fi
}

# Compara um limite do PHP-FPM (em MB) com o minimo recomendado.
verificar_limite_php() {
    local chave="$1" minimo="$2" ini='' valor numero candidato
    for candidato in /etc/php/*/fpm/php.ini; do
        [[ -f "$candidato" ]] && ini="$candidato"
    done
    if [[ -n "$ini" ]]; then
        valor="$(php -n -c "$ini" -r "echo ini_get('$chave');" 2>/dev/null)"
    else
        valor="$(php -r "echo ini_get('$chave');" 2>/dev/null)"
    fi
    numero="$(sed -E 's/[^0-9].*//' <<<"$valor")"
    case "$valor" in *G|*g) numero=$((numero * 1024)) ;; *K|*k) numero=0 ;; esac
    if [[ "${numero:-0}" -ge "$minimo" ]]; then ok "PHP-FPM $chave = $valor"; else atencao "PHP-FPM $chave = ${valor:-?}" "recomendado ${minimo}M (planilhas de importacao)."; fi
}

verificar_servicos() {
    secao 'Servicos'
    local servico php_fpm
    php_fpm="$(systemctl list-units --type=service --all --no-legend 'php*-fpm.service' 2>/dev/null | awk '{print $1}' | sort -V | tail -1)"
    for servico in nginx postgresql "${php_fpm:-php-fpm.service}"; do
        if systemctl is-active --quiet "$servico" 2>/dev/null; then ok "Servico ${servico%.service} ativo"; else falha "Servico ${servico%.service} parado ou ausente" "sudo systemctl status ${servico%.service}"; fi
    done
    for servico in fail2ban unattended-upgrades; do
        if systemctl is-active --quiet "$servico" 2>/dev/null; then ok "$servico ativo"; else atencao "$servico inativo" "sudo apt-get install -y $servico && sudo systemctl enable --now $servico"; fi
    done
    if systemctl list-unit-files cloudflared.service >/dev/null 2>&1 && systemctl list-unit-files cloudflared.service | grep -q cloudflared; then
        if systemctl is-active --quiet cloudflared; then ok 'Tunel cloudflared ativo'; else falha 'Tunel cloudflared instalado mas parado' 'sudo systemctl status cloudflared'; fi
        info 'Lembrete: o cloudflared nao e atualizado automaticamente (atualize pelo APT no checklist mensal).'
    fi
}

verificar_acesso() {
    secao 'Portas e acesso'
    local linhas porta local_ publico
    linhas="$(ss -ltnH 2>/dev/null | awk '{print $4}')"
    for porta in 5432 80 443; do
        local_=0 publico=0
        while read -r endereco; do
            [[ "$endereco" == *":$porta" ]] || continue
            case "$endereco" in 127.*|'[::1]'*|'::1'*) local_=1 ;; *) publico=1 ;; esac
        done <<<"$linhas"
        if [[ "$porta" == 5432 ]]; then
            if [[ "$publico" -eq 1 ]]; then falha 'PostgreSQL escuta fora do proprio servidor' "use listen_addresses = 'localhost' no postgresql.conf."
            elif [[ "$local_" -eq 1 ]]; then ok 'PostgreSQL so no proprio servidor'
            else atencao 'PostgreSQL nao esta escutando na 5432'; fi
        elif [[ "$publico" -eq 1 ]]; then
            info "Porta $porta escutando em todos os enderecos (confira o firewall abaixo)"
        elif [[ "$local_" -eq 1 ]]; then
            info "Porta $porta so no proprio servidor"
        fi
    done

    if ! eh_root; then
        atencao 'Firewall, SSH e Nginx nao conferidos' 'rode de novo com sudo.'
        return 0
    fi

    local ufw
    if tem ufw; then
        ufw="$(ufw status 2>/dev/null)"
        if grep -q '^Status: active' <<<"$ufw"; then
            ok 'Firewall (UFW) ativo'
            if grep -qE '^22(/tcp)?[[:space:]].*ALLOW' <<<"$ufw" || grep -qE '^(OpenSSH|SSH)[[:space:]].*ALLOW' <<<"$ufw"; then ok 'UFW libera a porta 22'; else falha 'UFW nao mostra regra para a 22' 'risco de perder o acesso SSH; confira antes de qualquer mudanca.'; fi
            if grep -qE '^5432(/tcp)?[[:space:]].*ALLOW' <<<"$ufw"; then falha 'UFW libera a 5432 (banco)' 'remova a regra: o banco nunca fica publico.'; else ok 'UFW nao libera o banco (5432)'; fi
            if grep -qE '^(80|443)(/tcp)?[[:space:]].*ALLOW|Nginx' <<<"$ufw"; then atencao 'UFW libera 80/443' 'so com dominio e HTTPS valido; com o tunel da Cloudflare essas portas ficam fechadas.'; fi
        else
            falha 'Firewall (UFW) inativo' 'ative so depois de liberar e testar a 22 (ajustar-acesso.sh).'
        fi
    else
        falha 'UFW nao instalado'
    fi

    local sshd root senha teclado
    if tem sshd; then
        sshd="$(sshd -T 2>/dev/null)"
        root="$(awk '$1=="permitrootlogin" {print $2}' <<<"$sshd")"
        senha="$(awk '$1=="passwordauthentication" {print $2}' <<<"$sshd")"
        teclado="$(awk '$1=="kbdinteractiveauthentication" {print $2}' <<<"$sshd")"
        case "$root" in
            no) ok 'SSH: root nao entra' ;;
            prohibit-password|without-password) atencao 'SSH: root entra com chave' 'use PermitRootLogin no; entre com o seu usuario e sudo.' ;;
            *) falha "SSH: root pode entrar (${root:-?})" 'PermitRootLogin no' ;;
        esac
        if [[ "$senha" == no && "${teclado:-no}" == no ]]; then ok 'SSH: so por chave'; else falha 'SSH: aceita senha' 'PasswordAuthentication no e KbdInteractiveAuthentication no, com uma sessao de reserva aberta.'; fi
    else
        atencao 'sshd nao encontrado'
    fi

    local corpo
    if tem nginx; then
        corpo="$(nginx -T 2>/dev/null | awk '$1=="client_max_body_size" {gsub(";","",$2); print $2}' | sort -u | tail -1)"
        case "$corpo" in
            '') atencao 'Nginx sem client_max_body_size (padrao 1 MB)' 'use client_max_body_size 16m; no site do Astuti.' ;;
            *[0-9][mM]) if [[ "${corpo%[mM]}" -ge 16 ]]; then ok "Nginx client_max_body_size $corpo"; else atencao "Nginx client_max_body_size $corpo" 'recomendado 16m.'; fi ;;
            *[gG]) ok "Nginx client_max_body_size $corpo" ;;
            *) atencao "Nginx client_max_body_size $corpo" 'recomendado 16m.' ;;
        esac
    fi
}

# Le uma chave do .env sem imprimir o arquivo.
valor_env() {
    grep -E "^$1=" "$2" 2>/dev/null | tail -1 | cut -d= -f2- | tr -d '"'"'"' \r'
}

verificar_projeto() {
    local projeto="$1" env ambiente debug url cookie modo grupo codigo
    secao 'Aplicacao'
    if [[ ! -f "$projeto/artisan" ]]; then
        atencao 'Projeto nao encontrado' 'informe a pasta com --projeto=/srv/astuti-escolar'
        return 0
    fi
    env="$projeto/.env"
    if [[ ! -f "$env" ]]; then
        atencao 'Arquivo .env ainda nao existe' 'siga docs/operacao/vps-prefeitura.md.'
        return 0
    fi

    modo="$(stat -c '%a' "$env")"
    grupo="$(stat -c '%G' "$env")"
    if [[ "${modo: -1}" != 0 ]]; then falha ".env legivel por qualquer usuario (modo $modo)" 'chmod 640 .env'
    elif [[ "$grupo" != www-data ]]; then atencao ".env com modo $modo, mas grupo diferente de www-data" 'sudo chgrp www-data .env'
    else ok ".env protegido (modo $modo, grupo www-data)"; fi

    ambiente="$(valor_env APP_ENV "$env")"
    debug="$(valor_env APP_DEBUG "$env")"
    url="$(valor_env APP_URL "$env")"
    cookie="$(valor_env SESSION_SECURE_COOKIE "$env")"
    case "$ambiente" in
        production) ok 'APP_ENV=production' ;;
        local) ok 'APP_ENV=local (desenvolvimento, so dados ficticios)' ;;
        *) atencao "APP_ENV=${ambiente:-vazio}" 'use production na Prefeitura e local na VPS de desenvolvimento.' ;;
    esac
    if [[ "$debug" == true ]]; then
        if [[ "$ambiente" == production ]]; then falha 'APP_DEBUG=true em producao' 'APP_DEBUG=false: o modo de depuracao mostra detalhes internos a qualquer visitante.'
        else atencao 'APP_DEBUG=true' 'use false em servidor acessivel pela internet.'; fi
    else
        ok 'APP_DEBUG desligado'
    fi
    if [[ "$url" == https://* ]]; then
        ok 'APP_URL com https'
        if [[ "$cookie" == true ]]; then ok 'Cookie de sessao so por https'; else falha 'SESSION_SECURE_COOKIE nao esta true' 'SESSION_SECURE_COOKIE=true'; fi
    elif [[ "$ambiente" == production ]]; then
        falha 'APP_URL sem https em producao' 'configure o dominio com HTTPS.'
    else
        atencao 'APP_URL sem https'
    fi
    if [[ -n "$(valor_env APP_KEY "$env")" ]]; then ok 'APP_KEY definida'; else falha 'APP_KEY vazia' 'php artisan key:generate (uma unica vez, na instalacao).'; fi

    if tem git && git -C "$projeto" -c "safe.directory=$projeto" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
        info "Versao: $(git -C "$projeto" -c "safe.directory=$projeto" log --oneline -1 2>/dev/null)"
        if [[ -z "$(git -C "$projeto" -c "safe.directory=$projeto" status --porcelain --untracked-files=no 2>/dev/null)" ]]; then ok 'Codigo sem alteracoes manuais'; else atencao 'Ha arquivos do projeto alterados a mao' 'git status; o script de atualizacao recusa seguir assim.'; fi
    fi

    if eh_root && id www-data >/dev/null 2>&1; then
        if sudo -u www-data test -r "$projeto/vendor/autoload.php"; then ok 'Site le as dependencias'; else falha 'Site (www-data) nao le vendor/autoload.php' 'reaplique as permissoes (veja vps-desenvolvimento.md).'; fi
        if sudo -u www-data test -w "$projeto/storage/logs"; then ok 'Site grava em storage/logs'; else falha 'Site (www-data) nao grava em storage/logs'; fi
    fi

    if [[ "${ASTUTI_VERIFICAR_SEM_REDE:-0}" != 1 ]] && tem curl; then
        codigo="$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 http://127.0.0.1/login 2>/dev/null)"
        if [[ "$codigo" == 200 ]]; then ok 'Tela de login responde (200)'; else falha "Tela de login respondeu ${codigo:-sem resposta}" 'veja as ultimas linhas de storage/logs/laravel.log.'; fi
    fi

    atencao 'Backup externo e ensaio de restauracao nao sao conferidos automaticamente' 'confira no docs/operacao/vps-checklist-seguranca.md.'
}


verificar_backup() {
    secao 'Backup'
    local marca=/var/backups/astuti/ULTIMO-BACKUP-OK idade_h
    if ! eh_root; then
        info 'Backup: rode com sudo para conferir.'
        return 0
    fi
    if [[ -f /etc/cron.d/astuti-backup ]]; then ok 'Backup diario agendado'; else atencao 'Backup nao agendado' 'sudo bash scripts/operacao/vps/backup.sh --agendar=AGENDAR-BACKUP --manter=14 --criptografar=...'; fi
    if [[ -f /etc/cron.d/astuti-backup ]] && grep -q -- '--criptografar=' /etc/cron.d/astuti-backup; then ok 'Backup criptografado'; else atencao 'Backup sem criptografia' 'use --criptografar com a chave publica (docs/operacao/vps-backup.md).'; fi
    if [[ ! -f "$marca" ]]; then
        atencao 'Nenhum backup concluido neste servidor' 'sudo bash scripts/operacao/vps/backup.sh --confirmar=FAZER-BACKUP'
        return 0
    fi
    idade_h=$(( ( $(date +%s) - $(stat -c %Y "$marca") ) / 3600 ))
    if (( idade_h <= 26 )); then ok "Ultimo backup ha ${idade_h} h"; else falha "Ultimo backup ha ${idade_h} h" 'veja /var/log/astuti-backup.log.'; fi
    if find /var/backups/astuti -mindepth 1 -maxdepth 1 -name '*.incompleto' 2>/dev/null | grep -q .; then atencao 'Ha backup incompleto' 'veja /var/log/astuti-backup.log.'; fi
}

main "$@"
exit
