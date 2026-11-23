# Problemas e soluções na preparação da VPS

Soluções testadas na primeira VPS de desenvolvimento (Debian 13). Antes de pedir ajuda, rode `sudo bash scripts/operacao/vps/diagnostico.sh` e anexe o resultado, que não expõe segredos nem endereços do servidor.

## SSH cai no meio de uma instalação

- No computador de onde você acessa, use `ServerAliveInterval 30` e `ServerAliveCountMax 4` no `~/.ssh/config` do servidor.
- Trabalhe sempre dentro do `tmux`: `tmux new -s astuti` para abrir e `tmux attach -t astuti` para voltar depois da queda. O comando continua rodando no servidor.

## APT interrompido ou travado

1. Veja se ainda há instalação em andamento: `ps aux | grep -E 'apt|dpkg' | grep -v grep`. Se houver, **espere**. Nunca mate um `dpkg` ativo.
2. Sem processo ativo: `sudo dpkg --audit` e depois `sudo dpkg --configure -a`.
3. Só então `sudo apt-get update` e repita a instalação.

## Servidor sem rota IPv6

Sintoma: `apt-get update` demora e falha em endereços IPv6. O `diagnostico.sh` mostra "Rota IPv6 padrao: ausente".

```bash
printf 'Acquire::ForceIPv4 "true";\nAcquire::Retries "3";\nAcquire::http::Timeout "30";\n' | sudo tee /etc/apt/apt.conf.d/99astuti-rede
```

## Espelho Debian instável

- Antes de adotar um espelho, teste certificado e tempo: `curl -sS -o /dev/null -w '%{http_code} %{time_total}s\n' https://deb.debian.org/debian/dists/trixie/Release`, e depois `sudo apt-get update`.
- Para o `cloud-init` não reescrever a lista na próxima inicialização, use `apt: preserve_sources_list: true` na configuração dele.

## GitHub inalcançável por um dos endereços

Sintoma: `git fetch` falha às vezes. O `diagnostico.sh` testa cada endereço resolvido e mostra qual não responde.

- Contorno **temporário**, comando a comando: `git -c http.curloptResolve=github.com:443:ENDERECO_QUE_RESPONDE fetch origin`.
- Nunca grave esse contorno no `.gitconfig`: o endereço do GitHub muda e o servidor ficaria preso a um endereço antigo.
- Abra chamado no provedor com a saída do diagnóstico.

## Atualização pergunta sobre o `sshd_config`

Escolha **manter a versão local** (a instalada). A versão do pacote volta a permitir senha e pode reabrir o acesso por senha.

## Site com erro 500 depois da instalação

Veja "Problemas já vistos" em `vps-desenvolvimento.md` (permissões criadas com `umask 077`, pasta `lang` sem leitura e `bind()` do Nginx).
