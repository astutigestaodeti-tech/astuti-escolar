# VPS de desenvolvimento e homologação

A VPS de desenvolvimento é um espelho do notebook: roda a `main` aprovada, em modo `local`, com o município fictício Monte Sereno. Nunca recebe dados reais. A produção de cada Prefeitura segue `vps-prefeitura.md`, em outra VPS.

## Como está montada

- O projeto fica em `/srv/astuti-escolar`, com dono o usuário do deploy e grupo `www-data`. Ele não fica na home, porque o PHP-FPM não atravessa uma pasta com modo 700.
- Os bancos `astuti_platform_dev` e `astuti_monte_sereno_dev` foram criados com ICU pt-BR e pertencem a uma conta própria, sem superusuário. O `REVOKE ALL ... FROM PUBLIC` foi aplicado nos dois.
- O `.env` tem modo 640, grupo `www-data`, e usa `APP_ENV=local`, `APP_DEBUG=false`, `APP_URL` com https, `SESSION_SECURE_COOKIE=true` e `TRUSTED_PROXIES=127.0.0.1,::1`.
- O Nginx só escuta em `127.0.0.1` e `::1`. O UFW libera apenas a porta 22.
- O acesso pelo navegador é feito por um subdomínio próprio, via Cloudflare Tunnel: o `cloudflared` faz só conexão de saída e não abre porta nenhuma. O túnel SSH continua como acesso de reserva.

## Atualizar para a versão aprovada

Na pasta do projeto, sem `sudo` (o script pede a senha quando precisa):

```bash
cd /srv/astuti-escolar
bash scripts/operacao/vps/atualizar-desenvolvimento.sh
```

O script:

1. recusa rodar fora de `APP_ENV=local`, com arquivos do projeto alterados à mão ou fora da `main`;
2. só avança a versão, com `git merge --ff-only`;
3. instala as dependências e compila a interface apenas quando elas mudaram;
4. aplica as migrations novas pelo `astuti:demo-preparar`, que preserva escolas, alunos e a senha da conta fictícia;
5. reaplica as permissões de leitura e de gravação do `www-data` e recarrega o PHP-FPM;
6. confere que a tela de login responde com 200.

Ele não mexe em `.env`, senhas, firewall, SSH, Nginx nem no túnel.

## Conferir o servidor

Somente leitura, sem alterar nada, e com resultado que pode ser colado na issue:

```bash
cd /srv/astuti-escolar
sudo bash scripts/operacao/vps/verificar-ambiente.sh   # OK / ATENCAO / FALHA por item
sudo bash scripts/operacao/vps/diagnostico.sh          # o mesmo, com os detalhes
```

A lista completa, com os itens que dependem de conferência humana (backup externo, ensaio de restauração, chaves por equipamento), está em `vps-checklist-seguranca.md`. Soluções de instalação já testadas estão em `vps-problemas-e-solucoes.md`.

## Problemas já vistos

- **HTTP 500 com `Permission denied` em `vendor/autoload.php`:** os arquivos foram criados com `umask 077`. Para segredos, use `(umask 077; comando)` só naquele comando e nunca deixe a umask restrita na sessão. O script de atualização reaplica as permissões.
- **Mensagens em inglês:** a pasta `lang` ficou sem leitura para o `www-data`.
- **`reload` do Nginx falha com `bind() failed (98)`** depois de trocar o endereço de escuta: rode `nginx -t` e depois `systemctl restart nginx`.
- **Token do Cloudflare Tunnel:** o painel mostra o token mascarado. Copie sempre pelo botão de copiar. Se o token aparecer em chat ou relatório, use "Rotacionar token" e reinstale o serviço com `cloudflared service uninstall` e o novo `service install`.
- **O GitHub ficou inalcançável por um dos endereços:** use o contorno só comando a comando, nunca gravado em configuração, e abra um chamado no provedor.

## Remover as fotos dos alunos (ambiente fictício)

Para trocar as fotos dos alunos, remova primeiro as atuais. O comando sem `--confirmar` só lista; com a confirmação, limpa a foto de cada aluno ativo pela mesma via da ficha (com autoria na auditoria) e apaga os arquivos que ficaram sem cadastro na pasta de fotos de alunos. Fotos de responsáveis e autorizados não são tocadas; aluno arquivado mantém o histórico.

```
cd /srv/astuti-escolar
php artisan astuti:alunos-remover-fotos
php artisan astuti:alunos-remover-fotos --confirmar=REMOVER-FOTOS-DOS-ALUNOS --responsavel=demo.monte-sereno@astuti.invalid
```

## Colocar fotos fictícias nos alunos (ambiente fictício)

As fotos fictícias ficam fora do repositório (que é público). Copie o .zip para a VPS e rode o comando. Sem `--confirmar`, ele só mostra quantos alunos sem foto receberão uma e quantas fotos faltam, por menino ou menina e típica ou atípica. Foto de criança atípica só vai para aluno com educação especial cadastrada, de preferência com a mesma condição. Quem já tem foto não muda.

```
cd /srv/astuti-escolar
php artisan astuti:demo-fotos-alunos /tmp/fotos-ficticias-alunos.zip
php artisan astuti:demo-fotos-alunos /tmp/fotos-ficticias-alunos.zip --confirmar=COLOCAR-FOTOS-FICTICIAS --responsavel=demo.monte-sereno@astuti.invalid
```
