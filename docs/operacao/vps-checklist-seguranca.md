# Checklist de segurança da VPS

Use antes de entregar cada servidor, depois de qualquer mudança de acesso e uma vez por mês. Os itens marcados com **(auto)** são conferidos pelo `verificar-ambiente.sh`; os outros dependem de conferência humana.

```bash
cd /srv/astuti-escolar
sudo bash scripts/operacao/vps/verificar-ambiente.sh
```

O resultado mostra cada item como **OK**, **ATENCAO** ou **FALHA** e pode ser colado na issue, porque não imprime senhas, `.env`, IP, nome do servidor, usuários nem endereços do provedor. Se algo der errado, `sudo bash scripts/operacao/vps/diagnostico.sh` mostra os mesmos itens com os detalhes. Nenhum dos dois grava ou altera nada.

**Regra de entrega:** servidor com FALHA não recebe dados reais. Cada ATENCAO que ficar precisa de justificativa escrita no relatório de entrega.

## Acesso

- [ ] **(auto)** SSH só por chave: `PasswordAuthentication no` e `KbdInteractiveAuthentication no`.
- [ ] **(auto)** Root não entra por SSH (`PermitRootLogin no`); usa-se o próprio usuário com `sudo`.
- [ ] Uma chave por equipamento, com nome que identifique o equipamento. Ao perder ou trocar o equipamento, a chave dele sai do `authorized_keys` no mesmo dia.
- [ ] Mudanças em SSH e firewall só com uma **segunda sessão SSH aberta** de reserva e `sshd -t` sem erro antes do reload.
- [ ] Código de segundo fator e senhas são digitados pela própria pessoa, nunca enviados em chat.

## Firewall e portas

- [ ] **(auto)** UFW ativo, com a regra da 22 testada **antes** de ativar.
- [ ] **(auto)** A 5432 (PostgreSQL) não é liberada no UFW e o banco escuta só em `localhost`.
- [ ] **(auto)** 80 e 443 só abertas com domínio próprio e HTTPS válido. Com o Cloudflare Tunnel, ficam fechadas e o Nginx escuta só em `127.0.0.1` e `::1`.

## Sistema

- [ ] **(auto)** `unattended-upgrades` e `fail2ban` ativos.
- [ ] **(auto)** Swap configurada, disco abaixo de 80%.
- [ ] **(auto)** Fuso `America/Sao_Paulo` e locale `pt_BR.UTF-8`.
- [ ] O `cloudflared` vem de repositório próprio e **não** é atualizado sozinho: `sudo apt-get update && sudo apt-get install --only-upgrade cloudflared` uma vez por mês.
- [ ] Token do túnel nunca em chat, e-mail ou relatório. Se vazar: "Rotacionar token" no painel, `cloudflared service uninstall` e novo `service install`.

## Aplicação

- [ ] **(auto)** `.env` com modo 640 e grupo `www-data`; nunca no Git.
- [ ] **(auto)** Produção com `APP_ENV=production` e `APP_DEBUG=false`.
- [ ] **(auto)** `APP_URL` com https e `SESSION_SECURE_COOKIE=true`.
- [ ] **(auto)** O site lê as dependências, grava em `storage/logs` e a tela de login responde 200.
- [ ] Contas do PostgreSQL separadas (migração e aplicação, pelo `astuti:permissoes-pg`), com senhas fortes e distintas guardadas em cofre. Veja `vps-prefeitura.md`.
- [ ] A VPS de desenvolvimento só tem dados fictícios. Dados reais só no servidor da Prefeitura contratante.

## Backup

- [ ] Backup diário do `platform` e do `tenant`, criptografado e copiado para **fora** da VPS.
- [ ] Ensaio de restauração em banco separado feito e registrado (data, tamanho, tempo, contagens conferidas) antes da entrega e a cada trimestre.
- [ ] Senha da criptografia do backup guardada em cofre, fora da VPS.

## Monitoramento

- [ ] Alguém recebe aviso se a tela de login deixar de responder.
- [ ] Alerta de disco acima de 80%.
- [ ] Revisão mensal da auditoria de acessos (entradas com segundo fator recusado e exclusões).
