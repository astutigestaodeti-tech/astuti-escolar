# Migração controlada: MariaDB local para PostgreSQL local

> **Correção de e-mails antes de um novo ensaio (21/11/2026):** o PostgreSQL distingue maiúsculas em `users.email`. A migration `database/migrations/platform/2026_11_21_000011_normalize_user_emails.php` converte e-mails existentes para minúsculas e cria índice único em `lower(email)`. Ela aborta com aviso e preserva os registros se já houver e-mails duplicados ignorando maiúsculas e espaços. Em novos destinos de ensaio, o preparo normal das migrations deve incluí-la antes da cópia. A transferência normaliza e-mails de origem para o destino sem alterar o MariaDB ou os hashes. **Não execute migrations diretamente no PostgreSQL original 5432 nem altere o `.env` do notebook por causa desta nota.** A conferência local do cluster isolado será orientada separadamente após os testes do PR.

**Somente Monte Sereno v2 fictício**, no notebook Debian/WSL. O comando não usa dados reais e não é uma ferramenta de migração de Prefeituras contratantes. As bases MariaDB permanecem legíveis e **inalteradas**. O código está no PR de migração e deve passar pela revisão independente antes de qualquer execução com `--executar`.

## Validação no GitHub Actions

O job **Integração MariaDB para PostgreSQL** roda a suíte de transferência duas vezes nos mesmos serviços efêmeros MariaDB 11.8 e PostgreSQL 17. Cada rodada usa bases distintas com sufixo `_test` e dados gerados para Monte Sereno v2. No [run do PR #5](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44869962312), passaram 22 testes e 228 asserções em cada rodada. A suíte geral passou duas vezes, com 165 testes e 606 asserções por rodada. Pint, build Vite e os quatro testes de interface também passaram.

A integração confirmou que a origem sintética fica intacta, a cópia preserva as linhas e os hashes e as referências de auditoria e escopo escolar são válidas. O teste de falha antes do commit confirmou o rollback nos dois destinos. O teste entre commits confirmou o estado parcial, a recusa de nova tentativa no mesmo par e a retomada em outro par descartável.

Antes de autorizar a cópia no notebook, confira a revisão independente, o commit em uso, os dois destinos vazios, a simulação nesse ambiente e os backups da origem e dos destinos. Verifique os backups com restauração supervisionada em bancos isolados e mantenha a aplicação sem escritas durante a operação. O CI usa dados sintéticos e a validação dos backups, da configuração e do login do notebook depende de um ensaio local.

## 1. Preservar a origem antes de começar

No Debian, mantenha as duas bases MariaDB originais `astuti_platform_dev` e `astuti_monte_sereno_dev`. Confira os nomes e o usuário de leitura. Guarde os dois dumps em um diretório novo para cada tentativa, fora do Git e com permissões restritas:

```bash
(
    set -eu
    umask 077
    backup_root="$HOME/astuti-backups"
    mkdir -p -- "$backup_root"
    chmod 700 -- "$backup_root"
    backup_dir="$(mktemp -d -- "$backup_root/mariadb-pre-copia-XXXXXXXX")"
    cd -- "$backup_dir"

    mariadb-dump -u aflora --single-transaction astuti_platform_dev > platform-mariadb-antes.sql
    mariadb-dump -u aflora --single-transaction astuti_monte_sereno_dev > monte-sereno-mariadb-antes.sql
    test -s platform-mariadb-antes.sql
    test -s monte-sereno-mariadb-antes.sql
    sha256sum platform-mariadb-antes.sql monte-sereno-mariadb-antes.sql > SHA256SUMS
    sha256sum -c SHA256SUMS
    printf 'Backups MariaDB pré-cópia em %s\n' "$backup_dir"
)
```

Se o bloco falhar, trate o diretório da tentativa como incompleto e preserve os backups anteriores. Após verificar as duas somas, ensaie a restauração supervisionada de cada dump em uma base MariaDB isolada e vazia. Confira as tabelas e as contagens esperadas. Mantenha os arquivos no armazenamento restrito da operação, fora do GitHub e do chat.

Esses comandos **não** alteram as bases MariaDB; não execute `DROP DATABASE`, `migrate:fresh` ou `db:wipe` na origem.

## 2. Preparar destino PostgreSQL vazio

**Antes de copiar, crie os destinos novos com `TEMPLATE template0 LOCALE_PROVIDER icu ICU_LOCALE 'pt-BR'`.** Confira em `pg_database` que os dois bancos possuem `datlocprovider = i` e `datlocale = pt-BR`. Se já existirem sem ICU pt-BR, não tente alterar a collation por migration nem mexa no PostgreSQL original: interrompa o ensaio e prepare uma restauração supervisionada em novos bancos após preservar os backups.

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

**Rotina automatizada do notebook:** depois de confirmar que o sistema nao esta gravando dados, rode `bash scripts/operacao/criar-backups-pre-copia-postgresql.sh` na branch PostgreSQL atualizada. O script exige a tabela `jobs`, valida que as 12 tabelas-alvo de migracao ainda estao vazias, usa apenas o PostgreSQL local por socket, cria uma pasta nova `~/astuti-backups/pgsql-pre-copia-*` fora do Git, grava dois dumps **completos (estrutura e dados)** em formato customizado, valida indices e checksums e ensaia automaticamente a restauracao em mais dois bancos novos `astuti_restore_pg_*`. As restauracoes anteriores sao preservadas. Se qualquer etapa falhar, o script interrompe sem remover os arquivos ou bancos de teste.

O script recusa guardar dumps dentro de repositorio Git. **A aplicacao deve estar sem escritas durante toda a operacao**, porque dois `pg_dump` em bancos separados nao formam uma transacao global. Este procedimento e especifico do notebook ficticio e nao substitui um backup operacional de cliente.


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

### Comando automatizado para ensaiar as restauracoes do notebook

Na branch `feature/migracao-postgresql-instalacoes-isoladas`, o roteiro versionado `scripts/operacao/ensaiar-restauracao-postgresql.sh` localiza o **unico par completo** de backups em `~/astuti-backups/pgsql-antes-jobs-*` e restaura os dois arquivos em **novos** bancos PostgreSQL com prefixos `astuti_restore_pg_platform_` e `astuti_restore_pg_monte_`. Nunca e apontado para os bancos `*_dev`. Se falhar, interrompe, preserva os bancos de teste ja criados e informa os nomes. As restauracoes nao sao eliminadas automaticamente.

Depois de conferir que a arvore Git esta limpa e sincronizar a branch no notebook:

```bash
bash scripts/operacao/ensaiar-restauracao-postgresql.sh
```

O teste de simulacao de falhas do roteiro pode ser executado sem PostgreSQL nem `sudo` reais:

```bash
bash tests/Shell/EnsaiarRestauracaoPostgresqlTest.sh
```

Aprovacao em testes simulados/CI nao e evidencia de uma restauracao real: registre a saida do notebook, verifique as contagens e preserve todos os bancos e dumps. Os backups usados nessa demonstracao foram criados antes da migration de `jobs`; sua ausencia nos bancos restaurados e esperada.

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


## Ensaio local de cópia em cluster PostgreSQL independente

Depois do backup pré-cópia e da restauração validados na captura 81 (21/11/2026), a etapa seguinte é **testar a transferência MariaDB para um cluster PostgreSQL exclusivo de ensaio**, sem modificar os dois destinos PostgreSQL originais. O roteiro abaixo **usa a opção `--executar` somente no cluster separado**, com porta própria e bancos novos, depois de verificar a conexão efetiva do Laravel.

No Debian do notebook, na branch `feature/migracao-postgresql-instalacoes-isoladas`, execute:

```bash
cd ~/astuti-escolar && git pull --ff-only origin feature/migracao-postgresql-instalacoes-isoladas && bash scripts/operacao/ensaiar-copia-isolada-postgresql.sh
```

O roteiro automatizado:

1. Localiza a única pasta `~/astuti-backups/pgsql-pre-copia-*` (ou aceita `--backup-dir PASTA` se houver múltiplas), confere os **dois** dumps e os checksums, e verifica que as 12 tabelas de dados dos bancos PostgreSQL **originais** estão vazias.
2. Cria um novo cluster PostgreSQL 17 com nome e porta próprios (na faixa 55432 a 55531), autenticação temporária e escuta TCP limitada a `localhost` (somente loopback). A identidade física do cluster deve diferir da original. Ele não inicia automaticamente na reinicialização.
3. Restaura os dois backups em `astuti_platform_dev` e `astuti_monte_sereno_dev` **dentro do novo cluster**. Os nomes coincidem porque a CLI original possui proteções de segurança por nome; o cluster e a porta são distintos e validados.
4. Redireciona temporariamente `PLATFORM_DB_*` e `APP_CONFIG_CACHE` somente para os processos deste ensaio, sem editar `.env` ou limpar o cache global. Valida com conexões SQL reais o banco, usuário e porta usados pelo Laravel.
5. Executa a simulação; somente se ela passar copia a demo fictícia, confere 1 município, 3 escolas e 115 alunos e revalida os bancos originais.
6. Para o cluster ao sair, preservando suas bases para auditoria. Se houver erro, **não continue manualmente com `--executar`**: verifique a mensagem e as evidências.

O usuário MariaDB padrão usado pelo roteiro é `aflora`, via socket local, conforme o cenário fictício anteriormente conferido. O serviço Laravel abre a origem em transações `READ ONLY` e desfaz as transações de leitura ao final. O usuário `aflora` ainda possui outros privilégios locais; para processos reais deverá ser substituído por uma conta com somente `SELECT`, sem replicar dados de estudantes reais. O ensaio **não altera permissões ou usuários MariaDB**.

**Situação do processo:** a inclusão do roteiro e os testes no GitHub não provam a cópia real na máquina. A confirmação dependerá da saída do comando no Debian. Não executar `php artisan astuti:migrar-dados-postgresql --executar` diretamente nos PostgreSQL originais, não fazer corte e não mesclar PR #5 enquanto a revisão integral não estiver concluída.

**Correção verificada em 21/11/2026 (captura 82):** a primeira versão criava o cluster, mas o `pg_conftool` gravava `listen_addresses = 127.0.0.1` sem aspas, o que causou erro de sintaxe no `postgresql.conf` e impediu a inicialização. Isso ocorreu **antes de qualquer cópia**. O roteiro foi corrigido para `listen_addresses = localhost`, aceito como identificador simples e limitado ao loopback, com teste de regressão que falha no caso anterior. O cluster anterior `astuti_ens_20261121_103015_6876`, porta 55432, permaneceu **parado e preservado**. Uma nova execução criará outro cluster na próxima porta livre, sem reutilizar ou sobrescrever o anterior; sua limpeza será considerada separadamente depois da conferência.

## Testar login e consultas reais no notebook, sem corte (21/11/2026)

**Contexto validado na captura 91:** o cluster de ensaio `17/astuti_ens_20261121_112332_22176`, porta **55436**, foi iniciado no Debian; consultas de leitura confirmaram **1 usuario central, 3 escolas e 115 alunos ficticios**. Essa confirmacao **nao** prova login funcional nem navegacao no banco copiado.

Com o cluster **55436 online**, em um segundo terminal Debian que nao esteja executando o servidor da demo 8001, rode apenas:

```bash
cd ~/astuti-escolar && git pull --ff-only origin feature/migracao-postgresql-instalacoes-isoladas && bash scripts/operacao/testar-login-isolado-postgresql.sh
```

O script de seguranca:

1. Exige o PostgreSQL `17/main` na porta 5432 e o cluster de ensaio de nome **exato** na porta 55436, ambos online; consulta os identificadores fisicos e recusa qualquer coincidencia.
2. Antes de escrever, confirma no **55436** 1 municipio, uma unica conta ficticia ativa `demo.monte-sereno@astuti.invalid` com vinculo ativo e 3 escolas, 115 alunos e 115 matriculas.
3. Gera senhas aleatorias temporarias, valida a configuracao real do Laravel e **somente no 55436** troca a senha do papel PostgreSQL `astuti_ensaio` e da **conta ficticia copiada**. Nao executa `migrate`, `astuti:demo-preparar` nem outra transferencia.
4. Usa `PLATFORM_DB_* ` e cache de configuracao apenas no processo, com `SESSION_COOKIE=astuti_ensaio_55436`; nao altera `.env` nem remove caches globais; confere as conexoes SQL efetivas dos dois bancos antes de atualizar a conta.
5. Mostra a senha **somente no terminal local** e inicia `http://127.0.0.1:8002/login`. Deixe o terminal aberto durante o teste; encerre com Ctrl+C. Nao envie prints da senha para o chat ou GitHub.

Se o cluster estiver `down`, **nao** execute o script ainda: primeiro confira `pg_lsclusters` e inicie apenas o cluster **55436** por `sudo pg_ctlcluster --skip-systemctl-redirect 17 astuti_ens_20261121_112332_22176 start`. Nao use o teste na porta 8001: a tela `/demonstracao` usa numeros ilustrativos (1.284 alunos, 4 escolas); as listas reais protegidas dependem de login e do cluster 55436 (115 alunos, 3 escolas).

**Escopo das garantias:** cinco cenarios de seguranca do roteiro foram aprovados em teste Bash com dependencias simuladas; o uso contra o Debian/PostgreSQL **ainda requer execucao e captura do usuario**. Os bancos originais 5432, MariaDB e backups devem permanecer intactos. A branch permanece isolada; PR #5 continua rascunho.
