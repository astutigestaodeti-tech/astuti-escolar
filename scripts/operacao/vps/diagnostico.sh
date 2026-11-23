#!/usr/bin/env bash
# Diagnostico SOMENTE LEITURA da VPS: a mesma conferencia do
# verificar-ambiente.sh, com os detalhes de cada item (versoes, cada endereco
# do GitHub e dos espelhos Debian, repositorios APT, portas e versao do codigo).
#
# Uso (na VPS):
#   sudo bash scripts/operacao/vps/diagnostico.sh
#
# Use quando algo der errado ou antes de pedir ajuda: o resultado pode ser
# colado na issue, porque nao mostra senhas, .env, IP, nome do servidor,
# usuarios nem enderecos do provedor. Nao grava nem altera nada.

exec bash "$(dirname "${BASH_SOURCE[0]}")/verificar-ambiente.sh" --detalhes "$@"
