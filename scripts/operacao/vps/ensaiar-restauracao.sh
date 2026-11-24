#!/usr/bin/env bash
# Ensaio de restauracao de um backup feito por backup.sh.
#
# Uso (de preferencia numa VPS de teste, nunca com usuarios trabalhando nela):
#   sudo bash scripts/operacao/vps/ensaiar-restauracao.sh                       # so mostra o que faria (ultimo backup)
#   sudo bash scripts/operacao/vps/ensaiar-restauracao.sh --pasta=/var/backups/astuti/2026-11-24_023700
#   sudo bash scripts/operacao/vps/ensaiar-restauracao.sh --confirmar=ENSAIAR-RESTAURACAO [--pasta=...]
#   ... --chave-privada=/caminho/chave.txt   (backup criptografado)
#   ... --manter-bancos                      (nao apaga os bancos de ensaio no final)
#
# O que faz:
#   1. confere as somas SHA-256 do backup;
#   2. decifra numa pasta temporaria, se for criptografado;
#   3. restaura cada banco num banco NOVO, astuti_ensaio_<numero>_<n>, criado
#      com ICU pt-BR; nunca usa nem altera os bancos da instalacao;
#   4. compara as linhas de cada tabela com as contagens do momento do backup;
#   5. confere o pacote de arquivos privados;
#   6. apaga apenas os bancos de ensaio criados nesta execucao (a nao ser com
#      --manter-bancos) e a pasta temporaria.
#
# Diferencas de contagem aparecem como ATENCAO: o sistema pode ter gravado
# algo entre a contagem e a copia. Diferenca grande merece investigacao.

# Globais: a limpeza no final precisa delas mesmo depois de main() terminar.
CRIADOS=()
TRABALHO=''
MANTER_BANCOS=0

main() {
    set -Eeuo pipefail
    export LC_ALL=C
    umask 077

    local real=0 pasta='' destino=/var/backups/astuti chave='' arg
    for arg in "$@"; do
        case "$arg" in
            --confirmar=ENSAIAR-RESTAURACAO) real=1 ;;
            --simular) real=0 ;;
            --pasta=*) pasta="${arg#--pasta=}" ;;
            --destino=*) destino="${arg#--destino=}" ;;
            --chave-privada=*) chave="${arg#--chave-privada=}" ;;
            --manter-bancos) MANTER_BANCOS=1 ;;
            *) printf 'uso: sudo bash %s [--confirmar=ENSAIAR-RESTAURACAO] [--pasta=PASTA] [--chave-privada=ARQUIVO] [--manter-bancos]\n' "$0" >&2; return 2 ;;
        esac
    done

    erro() { printf '\nERRO: %s\n' "$*" >&2; exit 1; }
    passo() { printf '\n===== %s =====\n' "$*"; }

    (( real )) || printf 'SIMULACAO: nada sera alterado.\n'
    [[ "$(id -u)" -eq 0 ]] || erro 'rode com sudo (o ensaio usa a conta postgres).'

    if [[ -z "$pasta" ]]; then
        pasta="$(find "$destino" -mindepth 1 -maxdepth 1 -type d -regextype posix-extended \
            -regex '.*/[0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{6}' 2>/dev/null | sort | tail -1)"
        [[ -n "$pasta" ]] || erro "nenhum backup completo em $destino."
    fi
    [[ -f "$pasta/MANIFESTO.txt" && -f "$pasta/SHA256SUMS" ]] || erro "$pasta nao parece um backup do Astuti (falta MANIFESTO.txt ou SHA256SUMS)."
    head -1 "$pasta/MANIFESTO.txt" | grep -qx 'astuti-backup 1' || erro 'versao de backup desconhecida.'

    passo 'BACKUP ESCOLHIDO'
    sed 's/^/  /' "$pasta/MANIFESTO.txt"

    passo 'INTEGRIDADE (SHA-256)'
    (cd "$pasta" && sha256sum --quiet -c SHA256SUMS) || erro 'algum arquivo do backup foi alterado ou esta corrompido.'
    printf '  ok\n'

    local criptografado=0
    grep -qx 'criptografado: sim' "$pasta/MANIFESTO.txt" && criptografado=1
    if (( criptografado )); then
        command -v age >/dev/null || erro 'age nao instalado (sudo apt install age).'
        [[ -n "$chave" ]] || erro 'backup criptografado: informe --chave-privada=ARQUIVO (a chave nunca fica na VPS de producao).'
        [[ -f "$chave" ]] || erro "chave nao encontrada: $chave"
    fi

    local -a rotulos=() bancos=()
    local rotulo banco
    while read -r rotulo banco; do
        [[ "$rotulo" =~ ^[a-z0-9-]+$ ]] || erro "rotulo inesperado no manifesto: $rotulo"
        rotulos+=("$rotulo")
        bancos+=("$banco")
    done < <(sed -n 's/^banco: //p' "$pasta/MANIFESTO.txt")
    (( ${#rotulos[@]} > 0 )) || erro 'o manifesto nao lista bancos.'

    local sufixo
    sufixo="$(date +%s)"
    if (( ! real )); then
        passo 'O QUE SERIA FEITO'
        local i
        for i in "${!rotulos[@]}"; do
            printf '  restaurar %s em astuti_ensaio_%s_%s (banco novo)\n' "${rotulos[$i]}" "$sufixo" "$i"
        done
        printf '  comparar as linhas de cada tabela com as contagens do backup\n'
        printf '  %s\n' "$( (( MANTER_BANCOS )) && echo 'manter os bancos de ensaio' || echo 'apagar so os bancos de ensaio criados agora')"
        printf '\nFim da simulacao. Nada foi alterado.\n'
        return 0
    fi

    command -v pg_restore >/dev/null || erro 'pg_restore nao encontrado.'
    TRABALHO="$(mktemp -d /var/tmp/astuti-ensaio.XXXXXX)"
    trap 'limpar' EXIT

    local i origem contagem alvo diferencas=0 falhas=0
    for i in "${!rotulos[@]}"; do
        rotulo="${rotulos[$i]}"
        alvo="astuti_ensaio_${sufixo}_${i}"
        passo "RESTAURANDO ${rotulo}"

        origem="$pasta/$rotulo.dump"
        contagem="$pasta/CONTAGENS-$rotulo.txt"
        if (( criptografado )); then
            age -d -i "$chave" -o "$TRABALHO/$rotulo.dump" "$pasta/$rotulo.dump.age"
            age -d -i "$chave" -o "$TRABALHO/CONTAGENS-$rotulo.txt" "$pasta/CONTAGENS-$rotulo.txt.age"
            origem="$TRABALHO/$rotulo.dump"
            contagem="$TRABALHO/CONTAGENS-$rotulo.txt"
        fi
        [[ -f "$origem" ]] || erro "arquivo ausente: $origem"

        runuser -u postgres -- psql -X -q -v ON_ERROR_STOP=1 -d postgres \
            -c "CREATE DATABASE ${alvo} TEMPLATE template0 LOCALE_PROVIDER icu ICU_LOCALE 'pt-BR' ENCODING 'UTF8'"
        CRIADOS+=("$alvo")
        # O root le o arquivo e o entrega ao pg_restore: a conta postgres nao precisa ler a pasta do backup.
        runuser -u postgres -- pg_restore --no-owner --no-acl --exit-on-error -d "$alvo" < "$origem"
        printf '  restaurado em %s\n' "$alvo"

        local tabela esperado obtido tabelas=0
        while read -r tabela esperado; do
            tabelas=$(( tabelas + 1 ))
            obtido="$(runuser -u postgres -- psql -X -At -v ON_ERROR_STOP=1 -d "$alvo" \
                -c "SELECT count(*) FROM public.\"${tabela//\"/\"\"}\"" 2>/dev/null)" || { obtido='ausente'; }
            if [[ "$obtido" == ausente ]]; then
                printf '  FALHA: tabela %s nao foi restaurada\n' "$tabela"
                falhas=$(( falhas + 1 ))
            elif [[ "$obtido" != "$esperado" ]]; then
                printf '  ATENCAO: %s tem %s linhas; no backup eram %s\n' "$tabela" "$obtido" "$esperado"
                diferencas=$(( diferencas + 1 ))
            fi
        done < "$contagem"
        printf '  %s tabelas conferidas\n' "$tabelas"
    done

    passo 'ARQUIVOS PRIVADOS'
    if [[ -f "$pasta/arquivos-privados.tar.gz.age" ]]; then
        age -d -i "$chave" "$pasta/arquivos-privados.tar.gz.age" | tar -tzf - > "$TRABALHO/lista.txt"
    elif [[ -f "$pasta/arquivos-privados.tar.gz" ]]; then
        tar -tzf "$pasta/arquivos-privados.tar.gz" > "$TRABALHO/lista.txt"
    else
        : > "$TRABALHO/lista.txt"
    fi
    printf '  %s arquivos legiveis no pacote\n' "$(grep -vc '/$' "$TRABALHO/lista.txt" || true)"

    passo 'RESULTADO'
    if (( falhas > 0 )); then
        printf '  FALHA: %s tabela(s) nao voltaram. Este backup NAO serve para restaurar.\n' "$falhas"
        exit 1
    fi
    if (( diferencas > 0 )); then
        printf '  OK COM ATENCAO: %s tabela(s) com contagem diferente (gravacoes durante o backup).\n' "$diferencas"
    else
        printf '  OK: todas as tabelas voltaram com as mesmas linhas do backup.\n'
    fi
}

# Apaga somente o que este ensaio criou: bancos astuti_ensaio_* desta execucao e a pasta temporaria.
limpar() {
    local status=$? alvo
    if (( MANTER_BANCOS )); then
        (( ${#CRIADOS[@]} == 0 )) || printf '\nBancos de ensaio mantidos: %s\n' "${CRIADOS[*]}"
    else
        for alvo in "${CRIADOS[@]}"; do
            [[ "$alvo" =~ ^astuti_ensaio_[0-9]+_[0-9]+$ ]] || continue
            runuser -u postgres -- dropdb --if-exists "$alvo" || printf 'ATENCAO: nao consegui apagar o banco de ensaio %s\n' "$alvo" >&2
        done
        (( ${#CRIADOS[@]} == 0 )) || printf '\nBancos de ensaio apagados: %s\n' "${CRIADOS[*]}"
    fi
    if [[ "$TRABALHO" == /var/tmp/astuti-ensaio.* ]]; then rm -rf -- "$TRABALHO"; fi
    return "$status"
}

main "$@"
