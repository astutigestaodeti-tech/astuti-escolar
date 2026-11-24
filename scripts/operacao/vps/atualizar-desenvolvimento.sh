#!/usr/bin/env bash
# Atualiza a VPS de DESENVOLVIMENTO/HOMOLOGACAO para a versao aprovada (main).
#
# Uso, na pasta do projeto na VPS (normalmente /srv/astuti-escolar):
#   bash scripts/operacao/vps/atualizar-desenvolvimento.sh
#
# O que faz, nesta ordem, parando no primeiro erro:
#   1. confere que e a VPS de desenvolvimento (APP_ENV=local) e que ninguem
#      editou codigo a mao na pasta;
#   2. baixa a main do GitHub (so avanca, nunca reescreve);
#   3. instala dependencias PHP e compila a interface SO se mudaram;
#   4. aplica as migrations novas pelo comando do Monte Sereno ficticio, que
#      preserva escolas, alunos importados e a senha da conta ficticia;
#   5. reaplica as permissoes do site (www-data) e recarrega o PHP-FPM;
#   6. confere que a tela de login responde.
#
# Nao toca em .env, senhas, firewall, SSH, Nginx, nem nos bancos de outra
# instalacao. Nunca roda em producao (APP_ENV=production e recusado).
#
# Todo o corpo fica dentro de main(): o bash le o arquivo inteiro antes de
# executar, entao a troca de versao no meio nao embaralha o proprio script.

main() {
    set -Eeuo pipefail
    umask 022

    erro() { printf '\nERRO: %s\n' "$*" >&2; exit 1; }
    passo() { printf '\n===== %s =====\n' "$*"; }

    local raiz antes depois ambiente versao_php codigo
    raiz="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
    cd "$raiz"

    [[ $# -eq 0 ]] || erro 'uso: bash scripts/operacao/vps/atualizar-desenvolvimento.sh'
    [[ "$(id -u)" -ne 0 ]] || erro 'rode como o seu usuario (sem sudo); o script pede a senha so quando precisar.'
    git rev-parse --is-inside-work-tree >/dev/null 2>&1 || erro 'rode dentro da pasta do projeto astuti-escolar.'
    [[ -f .env ]] || erro 'arquivo .env nao encontrado. Esta pasta ainda nao foi instalada.'

    passo 'CONFERINDO O AMBIENTE'
    ambiente="$(grep -E '^APP_ENV=' .env | tail -1 | cut -d= -f2- | tr -d '"'"'"' \r')"
    [[ "$ambiente" == local ]] || erro "esta pasta esta com APP_ENV=${ambiente:-vazio}. Este script e so para a VPS de desenvolvimento (APP_ENV=local)."
    printf 'Ambiente de desenvolvimento confirmado.\n'

    passo 'CONFERINDO ALTERACOES LOCAIS'
    if [[ -n "$(git status --porcelain --untracked-files=no)" ]]; then
        git status --short --untracked-files=no >&2
        erro 'ha arquivos do projeto alterados nesta pasta. Nada foi atualizado; mande esta lista para o Claude.'
    fi
    [[ "$(git branch --show-current)" == main ]] || erro "a pasta esta no ramo '$(git branch --show-current)', e nao na main. Nada foi atualizado."
    printf 'Nenhuma alteracao manual.\n'

    passo 'BAIXANDO A VERSAO APROVADA (main)'
    antes="$(git rev-parse HEAD)"
    git fetch origin main || erro 'nao foi possivel baixar do GitHub. Confira a internet da VPS (o GitHub ja ficou inalcancavel por um endereco).'
    git merge --ff-only origin/main || erro 'a main local divergiu da do GitHub. Nada foi alterado; mande esta mensagem para o Claude.'
    depois="$(git rev-parse HEAD)"
    printf 'Versao: %s\n' "$(git log --oneline -1)"
    if [[ "$antes" == "$depois" ]]; then
        printf 'Ja estava na versao mais recente; conferindo o restante assim mesmo.\n'
    fi

    passo 'DEPENDENCIAS'
    if [[ ! -d vendor ]] || ! git diff --quiet "$antes" "$depois" -- composer.json composer.lock; then
        composer install --no-interaction --prefer-dist || erro 'falha no composer install.'
    else
        printf 'PHP: sem mudancas.\n'
    fi
    if [[ ! -f public/build/manifest.json ]] || ! git diff --quiet "$antes" "$depois" -- package.json package-lock.json vite.config.js resources/js resources/css; then
        { npm ci && npm run build; } || erro 'falha ao compilar a interface.'
    else
        printf 'Interface: sem mudancas.\n'
    fi

    passo 'ESTRUTURA DOS BANCOS (migrations)'
    # Idempotente: aplica so o que falta e mantem a senha da conta ficticia.
    php artisan astuti:demo-preparar || erro 'falha ao atualizar a estrutura. Nada mais foi feito; mande a mensagem acima para o Claude.'
    php artisan view:clear >/dev/null

    passo 'CADASTROS DE ALUNOS REPETIDOS (so conferencia, nada e alterado)'
    php artisan astuti:alunos-repetidos || printf 'Aviso: a conferencia de repetidos nao rodou; o sistema segue normal.\n'

    passo 'PERMISSOES DO SITE (pode pedir a sua senha do Debian)'
    sudo chgrp -R www-data app bootstrap config database lang public resources routes vendor storage
    sudo chmod -R g+rX app bootstrap config database lang public resources routes vendor
    sudo chmod -R g+rwX storage bootstrap/cache
    sudo chgrp www-data .env && chmod 640 .env
    versao_php="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
    sudo systemctl reload "php${versao_php}-fpm" || erro "nao foi possivel recarregar o php${versao_php}-fpm."
    sudo -u www-data test -r vendor/autoload.php || erro 'o site (www-data) nao consegue ler vendor/autoload.php.'
    sudo -u www-data test -w storage/logs || erro 'o site (www-data) nao consegue gravar em storage/logs.'
    printf 'Permissoes conferidas.\n'

    passo 'CONFERINDO O SITE'
    codigo="$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 http://127.0.0.1/login || true)"
    [[ "$codigo" == 200 ]] || erro "a tela de login respondeu ${codigo:-sem resposta}. Veja as ultimas linhas de storage/logs/laravel.log e mande para o Claude."
    printf 'Tela de login respondendo (200).\n'

    printf '\nPronto. Versao %s publicada na VPS de desenvolvimento.\n' "$(git rev-parse --short HEAD)"
    printf 'Endereco: %s\n' "$(grep -E '^APP_URL=' .env | tail -1 | cut -d= -f2- | tr -d '"'"'"' \r')"
}

main "$@"
exit
