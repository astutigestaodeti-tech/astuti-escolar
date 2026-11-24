# Backup da VPS e ensaio de restauração

Dois scripts, ambos em `scripts/operacao/vps/`:

| Script | O que faz | Confirmação |
|---|---|---|
| `backup.sh` | Copia os bancos desta instalação e os arquivos privados (fotos, anexos), confere cada cópia e grava um manifesto com somas SHA-256. | `--confirmar=FAZER-BACKUP` |
| `ensaiar-restauracao.sh` | Restaura um backup em bancos **novos** (`astuti_ensaio_*`), compara as linhas de cada tabela com as do momento do backup e apaga só os bancos de ensaio que criou. | `--confirmar=ENSAIAR-RESTAURACAO` |

Sem a confirmação, os dois só mostram o que fariam. Nenhum deles altera os bancos da instalação, o `.env`, senhas, SSH ou firewall. O `.env` não entra no backup: guarde-o à parte, no cofre de senhas da equipe.

## 1. Chave de criptografia (uma vez)

O backup deve ser cifrado. A chave **privada** fica fora da VPS, no notebook ou no cofre da equipe; na VPS fica só a chave **pública**. Sem a privada, ninguém lê o backup, nem quem invadir o servidor.

No notebook:

```bash
sudo apt install age
mkdir -p ~/.astuti && age-keygen -o ~/.astuti/backup-chave-privada.txt
grep -o 'age1.*' ~/.astuti/backup-chave-privada.txt      # esta é a chave pública
```

Guarde uma cópia da chave privada no cofre de senhas. **Se ela se perder, os backups cifrados não podem ser abertos.**

Na VPS (`sudo apt install age` antes):

```bash
sudo install -d -m 755 /etc/astuti
echo 'age1...a chave pública...' | sudo tee /etc/astuti/backup-chave-publica.txt
```

O script recusa o arquivo se encontrar nele uma chave privada.

## 2. Primeiro backup

```bash
cd /srv/astuti-escolar
sudo bash scripts/operacao/vps/backup.sh --criptografar=/etc/astuti/backup-chave-publica.txt
sudo bash scripts/operacao/vps/backup.sh --confirmar=FAZER-BACKUP --criptografar=/etc/astuti/backup-chave-publica.txt
```

O primeiro comando mostra os bancos, o tamanho e o espaço livre. O segundo faz o backup em `/var/backups/astuti/AAAA-MM-DD_HHMMSS/`:

- um `.dump` por banco, conferido com `pg_restore --list`;
- `arquivos-privados.tar.gz`, conferido com `tar -t`;
- `CONTAGENS-*.txt`, as linhas por tabela;
- `MANIFESTO.txt` e `SHA256SUMS`.

Com criptografia, cada arquivo vira `.age`. Se algo falhar, a pasta fica com o final `.incompleto`, o script termina com erro e o último backup bom continua marcado em `/var/backups/astuti/ULTIMO-BACKUP-OK`.

## 3. Backup diário

```bash
sudo bash scripts/operacao/vps/backup.sh --agendar=AGENDAR-BACKUP --manter=14 \
    --criptografar=/etc/astuti/backup-chave-publica.txt --grupo-leitura=SEU_GRUPO
```

- Roda todo dia às 02:37 e registra em `/var/log/astuti-backup.log`.
- Mantém os 14 mais novos. A retenção apaga só pastas completas criadas pelo próprio script e nunca menos de 3.
- `--grupo-leitura` (só com criptografia) deixa os backups legíveis pelo grupo do seu usuário, para baixar sem `sudo`.

Para desligar: `sudo bash scripts/operacao/vps/backup.sh --desagendar=AGENDAR-BACKUP`. O `verificar-ambiente.sh` passa a conferir se há agendamento, criptografia e backup com menos de 26 horas.

## 4. Cópia para fora da VPS

Um backup que só existe na própria VPS se perde junto com ela. Do notebook, baixe com regularidade (sem apagar nada do destino):

```bash
rsync -av USUARIO@ENDERECO_DO_SERVIDOR:/var/backups/astuti/ ~/astuti-backups-vps/
```

Os arquivos chegam cifrados. Guarde também uma cópia em mídia ou serviço externo, conforme o contrato com a Prefeitura.

## 5. Ensaio de restauração

Uma cópia nunca restaurada não vale como backup. Faça o ensaio ao menos uma vez por mês e depois de cada mudança de versão do PostgreSQL.

Onde ensaiar:

- **VPS de desenvolvimento**: no próprio servidor, com dados fictícios;
- **Prefeitura**: numa VPS de teste isolada, para onde o backup cifrado é copiado.

Nunca ensaie no PostgreSQL original do notebook.

```bash
sudo bash scripts/operacao/vps/ensaiar-restauracao.sh --chave-privada=/caminho/backup-chave-privada.txt
sudo bash scripts/operacao/vps/ensaiar-restauracao.sh --confirmar=ENSAIAR-RESTAURACAO --chave-privada=/caminho/backup-chave-privada.txt
```

Sem `--pasta=`, o script usa o backup completo mais recente. Ele:

1. confere as somas SHA-256;
2. decifra numa pasta temporária;
3. cria bancos novos com ICU pt-BR e restaura neles;
4. compara as contagens;
5. confere o pacote de arquivos;
6. apaga apenas os bancos `astuti_ensaio_*` desta execução e a pasta temporária. Use `--manter-bancos` para inspecioná-los antes de apagar.

Leve a chave privada ao servidor de teste só durante o ensaio e apague-a depois (`shred -u`).

Como ler o resultado:

| Resultado | Significado |
|---|---|
| **OK** | Todas as tabelas voltaram com as mesmas linhas. |
| **OK COM ATENÇÃO** | Alguma tabela recebeu gravações entre a contagem e a cópia. Normal em horário de uso; diferenças grandes merecem investigação. |
| **FALHA** | Uma tabela não voltou: este backup não serve. Faça outro e ensaie de novo. |

Anote data, pasta e resultado na issue de implantação da VPS.

## Restauração de verdade

Restaurar sobre os bancos em uso não é feito por script. É uma operação planejada, com janela de manutenção e autorização expressa. A sequência é:

1. ensaiar o backup escolhido;
2. parar o site;
3. restaurar em bancos novos;
4. apontar o `.env` para eles;
5. conferir;
6. preservar os bancos antigos até a decisão de removê-los.

Veja também `vps-prefeitura.md` (seção Backup e ensaio de restauração).
