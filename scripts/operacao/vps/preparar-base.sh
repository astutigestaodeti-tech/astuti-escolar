#!/usr/bin/env bash
# Prepara os PROGRAMAS de uma VPS Debian 13 para o Astuti Gestao Escolar.
#
# Uso (na VPS, dentro do tmux):
#   sudo bash scripts/operacao/vps/preparar-base.sh                  # so mostra o que faria
#   sudo bash scripts/operacao/vps/preparar-base.sh --confirmar=PREPARAR-BASE
#
# Sem a confirmacao, NADA e alterado: o script lista cada comando que rodaria.
# Pode ser repetido (idempotente): o que ja esta pronto e pulado.
#
# Instala somente pacotes oficiais do Debian 13: PHP 8.4 e modulos,
# PostgreSQL 17, Nginx, Composer, Node e npm, OCR em portugues, leitura de
# PDF, tmux, git, unattended-upgrades e fail2ban. Ajusta swap, fuso,
# locale pt-BR e os limites de envio do PHP-FPM e do Nginx.
#
# NAO mexe em SSH, firewall, bancos, contas, configuracao da aplicacao nem
# migrations. SSH e
# firewall ficam no ajustar-acesso.sh; bancos e contas seguem o
# docs/operacao/vps-prefeitura.md.
#
# Registro: /var/log/astuti-preparar-base.log (so na execucao real).

main() {
    set -Eeuo pipefail
    export DEBIAN_FRONTEND=noninteractive LC_ALL=C

    local real=0 arg
    for arg in "$@"; do
        case "$arg" in
            --confirmar=PREPARAR-BASE) real=1 ;;
            --simular) real=0 ;;
            *) printf 'uso: sudo bash %s [--simular | --confirmar=PREPARAR-BASE]\n' "$0" >&2; return 2 ;;
        esac
    done

    erro() { printf '\nERRO: %s\n' "$*" >&2; exit 1; }
    passo() { printf '\n===== %s =====\n' "$*"; }
    pular() { printf '  ja pronto: %s\n' "$*"; }
    executar() {
        if [[ "$real" -eq 1 ]]; then
            printf '  + %s\n' "$*"
            "$@"
        else
            printf '  [simulacao] %s\n' "$*"
        fi
    }
    # Grava um arquivo de configuracao proprio do Astuti (nunca um do sistema).
    gravar() {
        local destino="$1" conteudo="$2"
        if [[ -f "$destino" && "$(cat "$destino")" == "$conteudo" ]]; then
            pular "$destino"
            return 0
        fi
        if [[ "$real" -eq 1 ]]; then
            printf '  + gravando %s\n' "$destino"
            printf '%s\n' "$conteudo" >"$destino"
        else
            printf '  [simulacao] gravaria %s:\n' "$destino"
            while IFS= read -r linha; do printf '      %s\n' "$linha"; done <<<"$conteudo"
        fi
    }

    if [[ "$real" -eq 1 ]]; then
        [[ "$(id -u)" -eq 0 ]] || erro 'a execucao real precisa de sudo.'
        # shellcheck source=/dev/null
        [[ "$(. /etc/os-release && printf '%s' "${ID:-}:${VERSION_ID:-}")" == debian:13 ]] \
            || erro 'este script foi testado so no Debian 13. Nada foi alterado.'
        if pgrep -x 'apt|apt-get|dpkg' >/dev/null; then
            erro 'ha uma instalacao do APT em andamento. Espere terminar (veja vps-problemas-e-solucoes.md). Nada foi alterado.'
        fi
        exec > >(tee -a /var/log/astuti-preparar-base.log) 2>&1
        printf '\n##### preparar-base %s #####\n' "$(date '+%d/%m/%Y %H:%M')"
    else
        printf 'SIMULACAO: nada sera alterado. Para executar: sudo bash %s --confirmar=PREPARAR-BASE\n' "$0"
    fi

    passo 'APT'
    if ! ip -6 route show default 2>/dev/null | grep -q .; then
        gravar /etc/apt/apt.conf.d/99astuti-rede 'Acquire::ForceIPv4 "true";
Acquire::Retries "3";
Acquire::http::Timeout "30";
Acquire::https::Timeout "30";'
    else
        pular 'rota IPv6 presente, sem forcar IPv4'
    fi
    executar apt-get update

    passo 'PACOTES OFICIAIS DO DEBIAN'
    local pacotes=(
        ca-certificates curl git unzip tmux locales
        unattended-upgrades fail2ban
        nginx
        postgresql-17 postgresql-client-17
        php8.4-fpm php8.4-cli php8.4-pgsql php8.4-mbstring php8.4-xml
        php8.4-curl php8.4-intl php8.4-gd php8.4-zip
        composer nodejs npm
        tesseract-ocr tesseract-ocr-por poppler-utils
    )
    local faltando=() pacote
    for pacote in "${pacotes[@]}"; do
        # Composer, Node e npm instalados por fora (ex.: NodeSource, que ja
        # traz o npm) sao mantidos: o npm do Debian conflitaria com eles.
        case "$pacote" in
            composer|npm) command -v "$pacote" >/dev/null 2>&1 && continue ;;
            nodejs) command -v node >/dev/null 2>&1 && continue ;;
        esac
        dpkg-query -W -f='${Status}' "$pacote" 2>/dev/null | grep -q 'install ok installed' || faltando+=("$pacote")
    done
    if [[ ${#faltando[@]} -eq 0 ]]; then
        pular 'todos os pacotes'
    else
        executar apt-get install -y --no-install-recommends "${faltando[@]}"
    fi

    passo 'SWAP'
    if [[ "$(awk '/^SwapTotal:/ {print $2}' /proc/meminfo)" -gt 0 ]]; then
        pular 'swap ativa'
    elif [[ -e /swapfile ]]; then
        erro '/swapfile existe mas nao esta ativo. Confira a mao (swapon --show) antes de repetir.'
    else
        executar fallocate -l 2G /swapfile
        executar chmod 600 /swapfile
        executar mkswap /swapfile
        executar swapon /swapfile
        if ! grep -q '^/swapfile ' /etc/fstab; then
            if [[ "$real" -eq 1 ]]; then
                printf '  + incluindo /swapfile no /etc/fstab\n'
                printf '/swapfile none swap sw 0 0\n' >>/etc/fstab
            else
                printf '  [simulacao] incluiria "/swapfile none swap sw 0 0" no /etc/fstab\n'
            fi
        fi
    fi

    passo 'FUSO E IDIOMA'
    if [[ "$(timedatectl show -p Timezone --value 2>/dev/null)" == America/Sao_Paulo ]]; then
        pular 'fuso America/Sao_Paulo'
    else
        executar timedatectl set-timezone America/Sao_Paulo
    fi
    if locale -a 2>/dev/null | grep -qiE '^pt_BR\.utf-?8$'; then
        pular 'locale pt_BR.UTF-8'
    else
        if ! grep -q '^pt_BR.UTF-8 UTF-8' /etc/locale.gen 2>/dev/null; then
            if [[ "$real" -eq 1 ]]; then
                printf '  + incluindo pt_BR.UTF-8 no /etc/locale.gen\n'
                printf 'pt_BR.UTF-8 UTF-8\n' >>/etc/locale.gen
            else
                printf '  [simulacao] incluiria "pt_BR.UTF-8 UTF-8" no /etc/locale.gen\n'
            fi
        fi
        executar locale-gen
    fi

    passo 'LIMITES DE ENVIO (planilhas e fotos)'
    if [[ -d /etc/php/8.4/fpm/conf.d || "$real" -eq 0 ]]; then
        gravar /etc/php/8.4/fpm/conf.d/99-astuti.ini 'upload_max_filesize = 12M
post_max_size = 16M'
    fi
    if [[ -d /etc/nginx/conf.d || "$real" -eq 0 ]]; then
        gravar /etc/nginx/conf.d/astuti-limites.conf 'client_max_body_size 16m;'
    fi

    passo 'ATUALIZACOES AUTOMATICAS E PROTECAO DO SSH'
    gravar /etc/apt/apt.conf.d/20auto-upgrades 'APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";'
    executar systemctl enable --now unattended-upgrades
    executar systemctl enable --now fail2ban

    passo 'SERVICOS'
    executar systemctl enable --now postgresql php8.4-fpm
    if [[ "$real" -eq 1 ]]; then
        nginx -t || erro 'a configuracao do Nginx tem erro. Veja a mensagem acima; o Nginx nao foi recarregado.'
    fi
    executar systemctl enable nginx
    executar systemctl reload-or-restart nginx php8.4-fpm

    passo 'PRONTO'
    if [[ "$real" -eq 1 ]]; then
        printf 'Programas preparados. Confira com:\n  sudo bash scripts/operacao/vps/verificar-ambiente.sh\n'
        printf 'Proximos passos: ajustar-acesso.sh (SSH e firewall) e, depois, bancos e contas pelo vps-prefeitura.md.\n'
    else
        printf 'Fim da simulacao. Nada foi alterado.\n'
    fi
}

main "$@"
exit
