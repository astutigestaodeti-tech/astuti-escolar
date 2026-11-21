# Migração controlada: MariaDB local para PostgreSQL local

**Somente Monte Sereno v2 fictício**, no notebook Debian/WSL. O comando não usa dados reais e não é uma ferramenta de migração de Prefeituras contratantes. As bases MariaDB permanecem legíveis e **inalteradas**. O código está no PR de migração e deve passar pela revisão independente antes de qualquer execução com `--executar`.

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
php artisan migrate --database=platform --path=database/migrations/platform
```

O banco municipal deve receber as migrations com o provisionamento controlado, sem semear a demonstração. Verifique o comando disponível com `php artisan help astuti:municipio-provisionar` e siga o procedimento documentado de provisionamento. **Não rode `astuti:demo-preparar` antes da cópia**.

O comando de transferência não cria bancos nem apaga registros. Se qualquer tabela de destino já contiver linhas, interrompe a operação. O PostgreSQL só deve conter a estrutura das migrations, e as tabelas escolares, de usuários e auditoria devem estar vazias.

## 3. Ler a origem com acesso somente de leitura

Crie, preferencialmente, uma conta MariaDB dedicada com privilégio `SELECT` **somente** nessas duas bases. O comando ainda marca as sessões de origem como `TRANSACTION READ ONLY`. Se já houver conexão via socket, configure-a:

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
- contagem por tabela, sem revelar credenciais ou dados de estudantes.

Se houver erro, **pare**. Não force a cópia e não altere a origem para fazê-la passar. Depois do backup testado e da simulação aprovada, a confirmação explícita é:

```bash
php artisan astuti:migrar-dados-postgresql --executar --confirmar=MONTE-SERENO-DEV
```

A transferência copia UUIDs, relacionamentos, histórico de exclusão lógica, auditorias e hashes de senha sem renovar credenciais. Confere identidades e contagens por tabela. Não copia sessões ativas, filas ou cache, que devem ser recriados, pois sessões anteriores ao corte não devem continuar válidas.

**Atenção:** PostgreSQL não fornece commit atômico automático entre os dois bancos. Uma falha entre os commits pode deixar um destino copiado e outro pendente; nesse caso, pare, verifique as duas bases e restaure os destinos com supervisão a partir do backup. Nunca apague a origem MariaDB. O comando falha fechado caso encontre destino já preenchido.

## 5. Conferir e decidir o corte

1. Confirme 3 escolas, 115 alunos fictícios, turmas de 2027, cadastro de usuários e vínculos na interface.
2. Teste login com **a mesma senha anterior**. O hash não é refeito na cópia.
3. Consulte auditoria e histórico, confirme permissões por escola e mensagens em português.
4. Execute testes, Pint, build e verificação visual.
5. Crie backup PG por base (`pg_dump -Fc`) e ensaie uma restauração isolada.
6. Documente resultado, versão Git e horário. Só depois considere a mudança de banco concluída.

O retorno temporário ao MariaDB só é viável enquanto não houver novas escritas no PostgreSQL. Não opere simultaneamente dois bancos como fontes de verdade. Em caso de falha, mantenha o sistema em manutenção e faça restauração supervisionada.
