# Migração controlada: MariaDB local para PostgreSQL local

**Somente Monte Sereno v2 fictício**, no notebook Debian/WSL. O comando não usa dados reais e não é uma ferramenta de migração de Prefeituras contratantes. As bases MariaDB permanecem legíveis e **inalteradas**. O código está no PR de migração e deve passar pela revisão independente antes de qualquer execução com `--executar`.

## Validação no GitHub Actions

O job **Integração MariaDB para PostgreSQL** roda a suíte de transferência duas vezes nos mesmos serviços efêmeros MariaDB 11.8 e PostgreSQL 17. Cada rodada usa bases distintas com sufixo `_test` e dados gerados para Monte Sereno v2. No [run do PR #5](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44869962312), passaram 22 testes e 228 asserções em cada rodada. A suíte geral passou duas vezes, com 165 testes e 606 asserções por rodada. Pint, build Vite e os quatro testes de interface também passaram.

A integração confirmou que a origem sintética fica intacta, a cópia preserva as linhas e os hashes e as referências de auditoria e escopo escolar são válidas. O teste de falha antes do commit confirmou o rollback nos dois destinos. O teste entre commits confirmou o estado parcial, a recusa de nova tentativa no mesmo par e a retomada em outro par descartável.

Antes de autorizar a cópia no notebook, confira a revisão independente, o commit em uso, os dois destinos vazios, a simulação nesse ambiente e os backups da origem e dos destinos. Verifique os backups com restauração supervisionada em bancos isolados e mantenha a aplicação sem escritas durante a operação. O CI usa dados sintéticos e a validação dos backups, da configuração e do login do notebook depende de um ensaio local.

## 1. Preservar a origem antes de começar

No Debian, mantenha as duas bases MariaDB originais `astuti_platform_dev` e `astuti_monte_sereno_dev`. Confira os nomes e o usuário de leitura. Faça dumps separados **fora do Git**:

```bash
mkdir -p "$HOME/astuti-backups"
mariadb-dump -u aflora --single-transaction astuti_platform_dev > "$HOME/astuti-backups/platform-mariadb-antes.sql"
mariadb-dump -u aflora --single-transaction astuti_monte_sereno_dev > "$HOME/astuti-backups/monte-sereno-mariadb-antes.sql"
sha256sum "$HOME/astuti-backups/"*-mariadb-antes.sql
```

Confirme que ambos foram produzidos, têm conteúdo e podem ser restaurados em uma base isolada de teste. Proteja `~/astuti-backups` com permissões restritas; não compartilhe, compacte para GitHub nem envie ao chat. Esses comandos **não** alteram as bases MariaDB; não execute `DROP DATABASE`, `migrate:fresh` ou `db:wipe` na origem.

## 2. Preparar destino PostgreSQL vazio

Siga [instalação no Debian](instalacao-postgresql-debian.md) para criar `astuti_platform_dev` e `astuti_monte_sereno_dev` em PostgreSQL. Configure `PLATFORM_DB_*` em `.env` e rode as migrations **somente nos bancos PG novos**:

```bash
php artisan config:clear
php artisan astuti:preparar-pg-vazio
```

O comando acima prepara **ambos** os esquemas PostgreSQL sem criar Prefeituras, usuários ou alunos. **Não rode `astuti:demo-preparar` nem `astuti:municipio-provisionar` antes da cópia**.

O comando de transferência não cria bancos nem apaga registros. Se qualquer tabela de destino já contiver linhas, interrompe a operação. O PostgreSQL só deve conter a estrutura das migrations, e as tabelas escolares, de usuários e auditoria devem estar vazias.

## 3. Ler a origem com acesso somente de leitura

Crie, preferencialmente, uma conta MariaDB dedicada com privilégio `SELECT` **somente** nessas duas bases. O serviço usa conexões dedicadas, sem transações abertas pelo chamador, e inicia transações MariaDB `READ ONLY` apenas para a transferência. Se já houver conexão via socket, configure-a:

```dotenv
ASTUTI_SOURCE_MARIADB_HOST=127.0.0.1
ASTUTI_SOURCE_MARIADB_PORT=3307
ASTUTI_SOURCE_MARIADB_SOCKET=/run/mysqld/mysqld.sock
ASTUTI_SOURCE_MARIADB_USERNAME=USUARIO_SO_LEITURA
ASTUTI_SOURCE_MARIADB_PASSWORD=SENHA_LOCAL
```

Instale `pdo_mysql` **somente na máquina que executa a migração**. A aplicação PostgreSQL em produção não depende do driver MariaDB. Nenhuma dessas variáveis entra no repositório.

## 4. Simular, conferir e, depois, copiar

Primeiro, **sem gravar nada**:

```bash
php artisan config:clear
php artisan astuti:migrar-dados-postgresql
```

A simulação verifica:

- execução em `APP_ENV=local` e destinos PostgreSQL somente em `127.0.0.1`;
- nomes exatos das bases fictícias, esquema compatível e **destinos sem dados**;
- município `monte-sereno`, 3 escolas, 115 alunos fictícios e ano letivo 2027;
- escopos escolares ligados a escolas municipais e autores informados nas auditorias ligados a usuários centrais;
- contagem por tabela, sem revelar credenciais ou dados de estudantes.

Se houver erro, **pare**. Não force a cópia e não altere a origem para fazê-la passar.

### Backups PostgreSQL antes da cópia

Com a simulação aprovada e as tabelas de destino ainda vazias, faça um `pg_dump -Fc` de cada destino PostgreSQL com o esquema já preparado. O exemplo usa a conexão local do guia de instalação. Confira host, porta, usuário e os nomes dos dois bancos na configuração do notebook. Peça a senha no prompt e mantenha a aplicação sem escritas durante a preparação.

O bloco cria um diretório exclusivo para cada tentativa, fora do Git. Preserve esse diretório e todos os backups anteriores. Use permissões restritas e mantenha os arquivos no armazenamento seguro da operação.

```bash
(
    set -eu
    umask 077
    backup_root="$HOME/astuti-backups"
    mkdir -p -- "$backup_root"
    chmod 700 -- "$backup_root"
    backup_dir="$(mktemp -d -- "$backup_root/pg-pre-copia-XXXXXXXX")"
    cd -- "$backup_dir"

    pg_dump -Fc -h 127.0.0.1 -p 5432 -U astuti_app -f platform.dump astuti_platform_dev
    pg_dump -Fc -h 127.0.0.1 -p 5432 -U astuti_app -f monte-sereno.dump astuti_monte_sereno_dev
    pg_restore -l platform.dump > platform.list
    pg_restore -l monte-sereno.dump > monte-sereno.list
    sha256sum platform.dump monte-sereno.dump > SHA256SUMS
    sha256sum -c SHA256SUMS
    printf 'Backups PostgreSQL pré-cópia em %s\n' "$backup_dir"
)
```

Confira nas duas listagens o banco e o esquema esperados. Registre o horário e o caminho do par de backups. Sob supervisão, restaure cada arquivo em um novo banco isolado e vazio, com `pg_restore --exit-on-error --single-transaction --dbname` apontando para o destino isolado conferido. Verifique as migrations e confirme que as tabelas alvo continuam vazias. O ensaio usa bases distintas dos destinos `*_dev` e preserva os dumps originais.

Prossiga para a confirmação explícita somente depois de verificar os dois backups, as somas SHA-256 e o resultado do ensaio supervisionado:

```bash
php artisan astuti:migrar-dados-postgresql --executar --confirmar=MONTE-SERENO-DEV
```

A transferência copia UUIDs, relacionamentos, histórico de exclusão lógica, auditorias e hashes de senha sem renovar credenciais. Confere identidades e contagens por tabela. Não copia sessões ativas, filas ou cache, que devem ser recriados, pois sessões anteriores ao corte não devem continuar válidas.

O serviço confirma primeiro o PostgreSQL municipal e depois o central em transações separadas. Para uma falha após o commit municipal, siga o procedimento de recuperação ao final deste guia.

## 5. Conferir e decidir o corte

1. Confirme 3 escolas, 115 alunos fictícios, turmas de 2027, cadastro de usuários e vínculos na interface.
2. Teste login com **a mesma senha anterior**. O hash não é refeito na cópia.
3. Consulte auditoria e histórico, confirme permissões por escola e mensagens em português.
4. Execute testes, Pint, build e verificação visual.
5. Crie um novo backup PostgreSQL por base (`pg_dump -Fc`) após a cópia e ensaie a restauração isolada. Guarde esse par em outro diretório, identificado como pós-cópia, e preserve o par pré-cópia.
6. Documente resultado, versão Git e horário. Só depois considere a mudança de banco concluída.

O retorno temporário ao MariaDB só é viável enquanto não houver novas escritas no PostgreSQL. Não opere simultaneamente dois bancos como fontes de verdade. Em caso de falha, mantenha o sistema em manutenção e faça restauração supervisionada.

## 6. Recuperar com supervisão após uma falha

Quando a falha ocorre após o commit municipal, o comando retorna falha e informa **commit parcial**. Isso confirma a gravação no destino municipal e indica que o commit central ficou sem confirmação. O diagnóstico da CLI omite credenciais e detalhes de SQL. A conferência de destino vazio recusa uma nova tentativa se encontrar registros.

1. Mantenha a aplicação em manutenção, suspenda escritas e interrompa a tentativa de corte. Registre a etapa, a versão Git, o horário, os nomes das bases envolvidas e a saída da CLI. Proteja os backups e os registros operacionais.
2. Inspecione **ambos** os destinos PostgreSQL em leitura. Compare esquema, contagens e identificadores nas tabelas migradas com as referências da simulação e do backup. Confira escolas, usuários, vínculos, escopos e auditorias. Na falha simulada do CI, as linhas municipais ficaram gravadas e as tabelas centrais continuaram vazias. Se a conexão falhar durante um commit real, confirme o estado de cada base antes de decidir como recuperá-la.
3. Identifique o par de cópias PostgreSQL **de antes da transferência** preparado na seção 4. Confira o horário, a origem e as somas SHA-256 registradas. Confira o conteúdo de cada arquivo no formato customizado com `pg_restore --list`. Ensaiar a restauração em dois destinos isolados exige verificar o esquema e o estado esperado de cada cópia. Valide também a disponibilidade dos dois dumps da origem MariaDB, mantendo as bases de origem intactas.
4. Com o responsável pela operação, aprove o estado recuperado antes de retomar o corte. Preserve o par parcial para inspeção. Use um novo par de destinos verificados para o ensaio, confirme que as tabelas alvo estão vazias e faça nova simulação antes de autorizar qualquer cópia. Após uma cópia autorizada, confira as referências interbancos, o login e a auditoria.
5. No GitHub Actions, o teste ensaia a retomada em outro par de bancos descartáveis com esquema novo, usando a origem sintética preservada. Os serviços efêmeros removem os bancos ao encerrar o job. Qualquer limpeza manual desse ensaio fica restrita aos bancos `*_test` identificados na execução, após conferir o servidor CI e os nomes exatos. O ensaio deixa intocados os bancos e as tabelas do notebook e não roda `--executar` em `*_dev`. A restauração dos backups do notebook precisa de ensaio supervisionado separado.
