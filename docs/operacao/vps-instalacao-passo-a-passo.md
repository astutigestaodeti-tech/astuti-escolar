# Instalação de uma VPS, passo a passo

Sequência oficial para preparar um servidor novo (Debian 13). Vale para a VPS de desenvolvimento e para a de cada Prefeitura; as diferenças estão marcadas. Use sempre o `tmux` (`tmux new -s astuti`), para uma queda do SSH não interromper nada.

Cada fase tem objetivo, comando, verificação e como voltar atrás. Os scripts que alteram o servidor **só simulam** sem a palavra de confirmação: rode primeiro sem ela, leia o que faria e só então confirme.

## Fase 1. Conferir o ponto de partida

- **Objetivo:** saber o que já existe antes de mudar qualquer coisa.
- **Comando:** `sudo bash scripts/operacao/vps/diagnostico.sh`
- **Verificação:** o resultado vai para a issue da instalação (não expõe segredos nem endereços).
- **Voltar atrás:** nada a desfazer; o script só lê.

## Fase 2. Programas

- **Objetivo:** PHP 8.4, PostgreSQL 17, Nginx, Composer, Node, OCR, swap, fuso, locale pt-BR, limites de envio, atualizações automáticas e fail2ban, só com pacotes oficiais do Debian 13.
- **Comando:**
  ```bash
  sudo bash scripts/operacao/vps/preparar-base.sh                           # simulação
  sudo bash scripts/operacao/vps/preparar-base.sh --confirmar=PREPARAR-BASE
  ```
- **Verificação:** `sudo bash scripts/operacao/vps/verificar-ambiente.sh` sem FALHA nas seções Programas e Serviços. O registro fica em `/var/log/astuti-preparar-base.log`.
- **Voltar atrás:** os arquivos próprios são `/etc/apt/apt.conf.d/99astuti-rede`, `/etc/php/8.4/fpm/conf.d/99-astuti.ini` e `/etc/nginx/conf.d/astuti-limites.conf`. Apague e recarregue o serviço. Pacotes saem com `apt-get remove`.
- O script **não** mexe em SSH, firewall, bancos nem na configuração da aplicação.

## Fase 3. Acesso (SSH e firewall)

- **Objetivo:** SSH só por chave e sem root, UFW só com a porta do SSH.
- **Antes:** abra uma **segunda sessão SSH** com o seu usuário e deixe-a aberta. O script recusa rodar sem ela e sem a sua chave em `~/.ssh/authorized_keys`.
- **Comando:**
  ```bash
  sudo bash scripts/operacao/vps/ajustar-acesso.sh                            # simulação
  sudo bash scripts/operacao/vps/ajustar-acesso.sh --confirmar=AJUSTAR-ACESSO
  ```
- **Verificação:** abra uma **terceira** sessão SSH e confirme que entra. Só depois feche as outras. Na seção "Portas e acesso" do `verificar-ambiente.sh`, tudo deve sair OK.
- **Voltar atrás (pela sessão de reserva):** `sudo rm /etc/ssh/sshd_config.d/10-astuti.conf && sudo systemctl reload ssh` e, se preciso, `sudo ufw disable`.

## Fase 4. Endereço pelo navegador

- **Desenvolvimento:** Cloudflare Tunnel, com o Nginx só em `127.0.0.1` e `::1` e nenhuma porta nova aberta. Veja `vps-desenvolvimento.md`.
- **Prefeitura:** domínio da Prefeitura ou nosso, com HTTPS válido. Abrir 80 e 443 no UFW só depois do certificado, ou usar o túnel do mesmo jeito.

## Fase 5. Bancos, contas e aplicação

Fora dos scripts, de propósito: siga `vps-prefeitura.md` (bancos `platform` e `tenant` com ICU pt-BR, contas pelo `astuti:permissoes-pg`, senhas digitadas por quem instala e guardadas em cofre, `.env` com modo 640). Na VPS de desenvolvimento, só o Monte Sereno fictício.

## Fase 6. Conferência final e entrega

- `sudo bash scripts/operacao/vps/verificar-ambiente.sh` **sem FALHA**; cada ATENCAO restante justificada por escrito.
- Itens humanos do `vps-checklist-seguranca.md`: backup externo criptografado, ensaio de restauração registrado, chaves por equipamento e monitoramento.
- O resultado vai para a issue da instalação. **Servidor com FALHA não recebe dados reais.**

Problemas comuns durante a instalação: `vps-problemas-e-solucoes.md`.
