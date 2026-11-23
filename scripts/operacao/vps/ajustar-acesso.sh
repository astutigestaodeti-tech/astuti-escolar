#!/usr/bin/env bash
# Fecha o acesso da VPS: SSH so por chave e sem root, firewall (UFW) so com a
# porta do SSH.
#
# Uso (na VPS):
#   sudo bash scripts/operacao/vps/ajustar-acesso.sh                    # so mostra o que faria
#   sudo bash scripts/operacao/vps/ajustar-acesso.sh --confirmar=AJUSTAR-ACESSO
#
# ANTES da execucao real, abra uma SEGUNDA sessao SSH com o mesmo usuario e
# deixe-a aberta: se algo der errado, ela continua conectada para desfazer.
# O script recusa rodar com uma sessao so.
#
# Travas:
#   - recusa se o seu usuario nao tiver chave em ~/.ssh/authorized_keys
#     (desligar a senha sem chave tranca todo mundo para fora);
#   - grava so /etc/ssh/sshd_config.d/10-astuti.conf (o sshd_config original
#     fica intacto) e valida com "sshd -t" ANTES de recarregar; se a validacao
#     falhar, apaga o arquivo novo e nao recarrega nada;
#   - libera a porta do SSH no UFW, confere que a regra existe e so entao
#     ativa o firewall. Nao abre 80, 443 nem 5432.
#
# Para desfazer o SSH: sudo rm /etc/ssh/sshd_config.d/10-astuti.conf &&
# sudo systemctl reload ssh. Para desligar o firewall: sudo ufw disable.

main() {
    set -Eeuo pipefail
    export LC_ALL=C

    local real=0 arg
    for arg in "$@"; do
        case "$arg" in
            --confirmar=AJUSTAR-ACESSO) real=1 ;;
            --simular) real=0 ;;
            *) printf 'uso: sudo bash %s [--simular | --confirmar=AJUSTAR-ACESSO]\n' "$0" >&2; return 2 ;;
        esac
    done

    erro() { printf '\nERRO: %s\n' "$*" >&2; exit 1; }
    passo() { printf '\n===== %s =====\n' "$*"; }
    executar() {
        if [[ "$real" -eq 1 ]]; then printf '  + %s\n' "$*"; "$@"; else printf '  [simulacao] %s\n' "$*"; fi
    }

    local arquivo=/etc/ssh/sshd_config.d/10-astuti.conf
    local conteudo='# Astuti Gestao Escolar: acesso so por chave, sem root.
PermitRootLogin no
PasswordAuthentication no
KbdInteractiveAuthentication no
PubkeyAuthentication yes'
    local usuario="${SUDO_USER:-}" casa sessoes porta

    if [[ "$real" -eq 1 ]]; then
        [[ "$(id -u)" -eq 0 ]] || erro 'a execucao real precisa de sudo.'
    else
        printf 'SIMULACAO: nada sera alterado. Para executar: sudo bash %s --confirmar=AJUSTAR-ACESSO\n' "$0"
    fi

    passo 'CONFERINDO AS TRAVAS'
    if [[ -z "$usuario" || "$usuario" == root ]]; then
        [[ "$real" -eq 1 ]] && erro 'rode com sudo a partir do SEU usuario (nao como root direto).'
        printf '  (simulacao) usuario nao identificado; na execucao real o script exige sudo a partir do seu usuario.\n'
    else
        casa="$(getent passwd "$usuario" | cut -d: -f6)"
        if grep -qE '^(ssh-|ecdsa-|sk-)' "$casa/.ssh/authorized_keys" 2>/dev/null; then
            printf '  OK  o seu usuario tem chave SSH autorizada\n'
        else
            [[ "$real" -eq 1 ]] && erro 'o seu usuario nao tem chave em ~/.ssh/authorized_keys. Desligar a senha agora trancaria o acesso. Nada foi alterado.'
            printf '  (simulacao) sem chave autorizada: a execucao real seria recusada.\n'
        fi
        sessoes="$(who | awk -v u="$usuario" '$1==u' | wc -l)"
        if [[ "$sessoes" -ge 2 ]]; then
            printf '  OK  %s sessoes abertas (uma fica de reserva)\n' "$sessoes"
        else
            [[ "$real" -eq 1 ]] && erro 'abra uma SEGUNDA sessao SSH com o seu usuario, deixe-a aberta e rode de novo. Nada foi alterado.'
            printf '  (simulacao) so %s sessao aberta: a execucao real seria recusada.\n' "$sessoes"
        fi
    fi
    export PATH="$PATH:/usr/sbin:/sbin"
    if ! command -v sshd >/dev/null 2>&1; then
        [[ "$real" -eq 1 ]] && erro 'servidor SSH (sshd) nao encontrado.'
        printf '  (simulacao) sshd nao encontrado neste computador.\n'
    fi
    porta="$(sshd -T 2>/dev/null | awk '$1=="port" {print $2; exit}' || true)"
    porta="${porta:-22}"
    printf '  Porta do SSH: %s\n' "$porta"

    passo 'SSH: SO POR CHAVE E SEM ROOT'
    if [[ -f "$arquivo" && "$(cat "$arquivo")" == "$conteudo" ]]; then
        printf '  ja pronto: %s\n' "$arquivo"
    elif [[ "$real" -eq 1 ]]; then
        printf '  + gravando %s\n' "$arquivo"
        printf '%s\n' "$conteudo" >"$arquivo"
        if ! sshd -t; then
            rm -f "$arquivo"
            erro 'o sshd recusou a configuracao. O arquivo novo foi apagado e o SSH nao foi recarregado.'
        fi
        executar systemctl reload ssh
    else
        printf '  [simulacao] gravaria %s:\n' "$arquivo"
        while IFS= read -r linha; do printf '      %s\n' "$linha"; done <<<"$conteudo"
        printf '  [simulacao] sshd -t (se falhar, apaga o arquivo e para)\n'
        printf '  [simulacao] systemctl reload ssh\n'
    fi
    if [[ "$real" -eq 1 ]]; then
        sshd -T | grep -qx 'passwordauthentication no' || erro 'depois do reload o SSH ainda aceita senha. Use a sessao de reserva para conferir /etc/ssh/sshd_config.d/.'
        sshd -T | grep -qx 'permitrootlogin no' || erro 'depois do reload o root ainda pode entrar. Use a sessao de reserva para conferir /etc/ssh/sshd_config.d/.'
        printf '  OK  sshd confirma: sem senha e sem root\n'
    fi

    passo 'FIREWALL (UFW)'
    if ! command -v ufw >/dev/null 2>&1; then
        executar apt-get install -y ufw
    fi
    executar ufw default deny incoming
    executar ufw default allow outgoing
    executar ufw allow "${porta}/tcp"
    if [[ "$real" -eq 1 ]]; then
        ufw show added | grep -qE "ufw allow ${porta}/tcp" \
            || erro "a regra da porta ${porta} nao apareceu no UFW. O firewall NAO foi ativado."
    else
        printf '  [simulacao] confere que a regra %s/tcp existe; sem ela, NAO ativa\n' "$porta"
    fi
    executar ufw --force enable

    passo 'PRONTO'
    if [[ "$real" -eq 1 ]]; then
        printf 'Acesso ajustado. ANTES de fechar a sessao de reserva, abra uma TERCEIRA sessao SSH\n'
        printf 'e confirme que entra. So depois feche as outras.\n'
        printf 'Confira tudo com: sudo bash scripts/operacao/vps/verificar-ambiente.sh\n'
    else
        printf 'Fim da simulacao. Nada foi alterado.\n'
    fi
}

main "$@"
exit
