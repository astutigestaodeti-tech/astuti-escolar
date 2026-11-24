#!/usr/bin/env bash
# Atualiza o notebook para a versao de teste mais recente e abre o sistema
# no ensaio isolado (PostgreSQL 55436). Nao toca no PostgreSQL principal
# (5432), no MariaDB, nos backups nem no .env.
#
# Uso, na pasta do projeto:
#   bash scripts/operacao/atualizar-notebook.sh
# Opcoes repassadas ao teste: ASTUTI_DEMO_PASSWORD=123456 e ASTUTI_TROCAR_DEMO_KIT=1.
#
# Todo o corpo fica dentro de main(): o bash le o arquivo inteiro antes de
# executar, entao trocar de versao no meio nao embaralha o proprio script.

main() {
    set -Eeuo pipefail

    erro() { printf 'ERRO: %s\n' "$*" >&2; exit 1; }
    passo() { printf '\n===== %s =====\n' "$*"; }

    local raiz ramo cluster porta antes depois
    raiz="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
    cd "$raiz"
    ramo='teste/notebook'
    cluster='astuti_ens_20261121_112332_22176'
    porta='55436'

    [[ $# -eq 0 ]] || erro 'uso: bash scripts/operacao/atualizar-notebook.sh'
    git rev-parse --is-inside-work-tree >/dev/null 2>&1 || erro 'rode dentro da pasta do projeto astuti-escolar.'

    passo 'CONFERINDO ALTERACOES LOCAIS'
    if [[ -n "$(git status --porcelain)" ]]; then
        git status --short >&2
        erro 'ha arquivos alterados no notebook. Nada foi atualizado; mande esta lista para o Claude.'
    fi

    passo 'BAIXANDO A VERSAO DE TESTE'
    antes="$(git rev-parse HEAD)"
    git fetch origin "$ramo" || erro 'nao foi possivel baixar do GitHub; confira a internet.'
    # Ramo so de teste: e sempre igual ao do GitHub, sem historico local proprio.
    git switch -C "$ramo" "origin/$ramo" || erro 'nao foi possivel trocar para a versao de teste.'
    depois="$(git rev-parse HEAD)"
    printf 'Versao: %s\n' "$(git log --oneline -1)"

    passo 'DEPENDENCIAS'
    if [[ "$antes" != "$depois" ]] && ! git diff --quiet "$antes" "$depois" -- composer.json composer.lock 2>/dev/null \
        || [[ ! -d vendor ]]; then
        composer install --no-interaction || erro 'falha no composer install.'
    else
        printf 'PHP: sem mudancas.\n'
    fi
    if [[ "$antes" != "$depois" ]] && ! git diff --quiet "$antes" "$depois" -- package.json package-lock.json vite.config.js resources/js resources/css 2>/dev/null \
        || [[ ! -d node_modules ]]; then
        { npm ci && npm run build; } || erro 'falha ao compilar a interface.'
    else
        printf 'Interface: sem mudancas.\n'
    fi

    passo 'LEITOR DE DOCUMENTOS (OCR)'
    if command -v tesseract >/dev/null 2>&1 && tesseract --list-langs 2>/dev/null | grep -qx por && command -v pdftoppm >/dev/null 2>&1; then
        printf 'Tesseract com portugues e Poppler instalados.\n'
    else
        printf 'Instalando o leitor de documentos (pode pedir a sua senha do Debian)...\n'
        sudo apt-get install -y tesseract-ocr tesseract-ocr-por poppler-utils || erro 'nao foi possivel instalar o leitor de documentos.'
    fi

    passo 'FOTOS (EXTENSAO GD DO PHP)'
    if php -m 2>/dev/null | grep -qix gd; then
        printf 'Extensao GD do PHP instalada.\n'
    else
        versao_php="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
        printf 'Instalando a extensao de fotos do PHP %s (pode pedir a sua senha do Debian)...\n' "$versao_php"
        sudo apt-get install -y "php${versao_php}-gd" || erro 'nao foi possivel instalar a extensao GD do PHP.'
    fi

    passo 'CONFERINDO A COPIA DE TESTE 55436'
    if ! pg_lsclusters 2>/dev/null | awk -v n="$cluster" -v p="$porta" '$1 == 17 && $2 == n && $3 == p && $4 == "online" { ok = 1 } END { exit !ok }'; then
        printf 'A copia de teste esta desligada. Ligando (pode pedir a sua senha do Debian)...\n'
        sudo pg_ctlcluster --skip-systemctl-redirect 17 "$cluster" start || erro 'nao foi possivel ligar a copia de teste 55436.'
    fi

    passo 'ATUALIZANDO A ESTRUTURA E ABRINDO O SISTEMA'
    ASTUTI_ATUALIZAR_ESTRUTURA=1 exec bash scripts/operacao/testar-login-isolado-postgresql.sh
}

main "$@"
exit
