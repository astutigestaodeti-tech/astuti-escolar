# Uma instalação de produção por Prefeitura, em VPS exclusivo

**Arquitetura aprovada:** cada Prefeitura contratante recebe um VPS virtual exclusivo em datacenter, **uma aplicação Astuti**, **dois bancos PostgreSQL locais** (`platform` e `tenant`), suas próprias contas, domínio, arquivos, logs e backups. Outro cliente nunca é cadastrado no mesmo VPS. Hardware físico dedicado fica reservado aos editais que o exigirem. Contratar uma VPS não foi autorizado e nenhuma foi criada nesta etapa.

## Versão do banco e extensões

- **PostgreSQL 17 ou superior é obrigatório.** As verificações de localização ICU pt-BR consultam `pg_database.datlocale`. Debian 12 oferece PostgreSQL 15 por padrão e Debian 13 oferece PostgreSQL 17; confira a versão do servidor com `SHOW server_version` antes de provisionar a VPS.
- Criar os dois bancos com `TEMPLATE template0 LOCALE_PROVIDER icu ICU_LOCALE 'pt-BR'`.
- Instalar `unaccent` e `pg_trgm` e executar todas as migrations **pela conta exclusiva de migração**, dona do esquema e dos objetos. A conta web não deve instalar extensões nem ser proprietária de tabelas ou funções.

## Provisionamento versionado dos dois papeis PostgreSQL

Antes de receber **qualquer dado real**, executar em VPS dedicada, com janela de manutencao e backup verificado. Este passo nao instala nem altera o notebook de desenvolvimento.

1. Instale PostgreSQL 17+; crie **somente** os bancos da Prefeitura local como administrador PostgreSQL, usando `CREATE DATABASE nome WITH TEMPLATE template0 LOCALE_PROVIDER icu ICU_LOCALE 'pt-BR';`. Configure `APP_ENV=production`, uma unica `ASTUTI_MUNICIPALITY_DB_KEY`, `PLATFORM_DB_*` e `TENANT_DB_*` apontando para essas duas bases locais distintas. Nunca disponibilize a porta 5432 publicamente.
2. Como usuario do sistema autorizado para o deploy, injete o acesso PostgreSQL administrativo **apenas no processo CLI**. Nao coloque `ASTUTI_PG_ADMIN_PASSWORD` no `.env` do PHP-FPM, no Git ou em `config:cache`. Exemplo em shell interativo:

   ```bash
   export ASTUTI_PG_ADMIN_HOST=127.0.0.1
   export ASTUTI_PG_ADMIN_PORT=5432
   export ASTUTI_PG_ADMIN_USER=postgres
   read -rs -p 'Senha PostgreSQL do administrador: ' ASTUTI_PG_ADMIN_PASSWORD
   export ASTUTI_PG_ADMIN_PASSWORD
   php artisan astuti:permissoes-pg --confirmar=PERMISSOES-PG
   ```

3. O comando cria `astuti_migracao` e `astuti_aplicacao` com LOGIN, sem superusuario, sem CREATE DATABASE e sem heranca. **Nao altera senhas em reexecucoes.** Uma conta nova fica sem senha ate o administrador executar interativamente `\\password astuti_migracao` e `\\password astuti_aplicacao` no `psql`. Gere duas senhas fortes e distintas, armazenadas em cofre. O comando transfere a titularidade de bancos, schema, tabelas, sequencias e funcoes da aplicacao para `astuti_migracao`, mas nao altera membros internos de extensoes.
4. Durante a janela, conecte as migrations usando `PLATFORM_DB_USERNAME=astuti_migracao` e `TENANT_DB_USERNAME=astuti_migracao` e suas respectivas senhas privadas. Instale `unaccent` e `pg_trgm` como este usuario, por meio das migrations. A plataforma pode ser migrada por:

   ```bash
   php artisan migrate --database=platform --path=database/migrations/platform --force
   ```

   A conexao municipal exige ativar o municipio configurado. O deploy pode executar:

   ```bash
   php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); app(App\Tenancy\TenantConnectionManager::class)->activate(new App\Models\Platform\Municipality(["db_key" => config("tenancy.deployed_db_key")])); exit(Illuminate\Support\Facades\Artisan::call("migrate", ["--database" => "tenant", "--path" => database_path("migrations/tenant"), "--realpath" => true, "--force" => true]));'
   ```

5. **Depois de cada migration, ainda em manutencao**, execute novamente `php artisan astuti:permissoes-pg --confirmar=PERMISSOES-PG`, preservando as duas credenciais administrativas apenas ate terminar o comando. Isso concede `SELECT/INSERT/UPDATE` nas tabelas operacionais; `SELECT/INSERT` nas auditorias, nunca `UPDATE/DELETE/TRUNCATE/TRIGGER`. As `ALTER DEFAULT PRIVILEGES` concedem apenas `SELECT/INSERT` para tabelas futuras ate a reconciliacao. A conta web nao tem `CREATE` no schema, nao e dona de objetos e nao pode alterar ou desativar os gatilhos.
6. Troque `PLATFORM_DB_USERNAME/PASSWORD` e `TENANT_DB_USERNAME/PASSWORD` para `astuti_aplicacao` no ambiente privado do PHP-FPM. Mantenha `astuti_migracao` e as credenciais de administrador **fora do processo web**. Remova as variaveis administrativas do shell com `unset ASTUTI_PG_ADMIN_PASSWORD ASTUTI_PG_ADMIN_USER ASTUTI_PG_ADMIN_HOST ASTUTI_PG_ADMIN_PORT` antes de aquecer caches ou iniciar o site.
7. Com a conta web limitada a SELECT/INSERT/UPDATE em tabelas operacionais, use `SESSION_DRIVER=file` (instancia unica, diretorio protegido) ou Redis para sessoes, `CACHE_STORE=file`/Redis para cache e um backend de fila que nao exija DELETE nas tabelas PostgreSQL, como Redis. O padrao de exemplo `SESSION_DRIVER=database` nao atende a restricao de DELETE no ciclo de login/logout. Valide login, persistencia de sessao e gravacao auditada antes de liberar acesso.

O CI possui job isolado PostgreSQL 17 que conecta efetivamente com `astuti_aplicacao`: tentativas de UPDATE, DELETE, TRUNCATE, ALTER TABLE, DROP TRIGGER e CREATE TABLE devem receber `SQLSTATE 42501`; login, insercao auditada e reexecucao dos grants devem funcionar. Nao use `SET ROLE` como substituto desse ensaio. O provisionamento e revisado pelo Claude antes da aprovacao de qualquer Prefeitura.

## Separar desenvolvimento e produção

- Notebook Debian/WSL: desenvolvimento com dados fictícios, Git, testes.
- Opcional: **VPS de desenvolvimento Astuti**, acessado por VS Code Remote SSH via VPN, sem dados reais nem domínio público. Não é o mesmo VPS da Prefeitura.
- Produção: um VPS por Prefeitura, com implantação automatizada da versão revisada. Alterações são desenvolvidas e testadas **fora da produção**; para disponibilizar uma versão, faz-se deploy controlado.

## Requisitos de segurança antes do primeiro contrato

1. Nomear o cliente e implantar um único `ASTUTI_MUNICIPALITY_DB_KEY` fixo no `.env` privado. Configurar `TENANT_DB_HOST=127.0.0.1`, `TENANT_DB_PORT=5432`, `TENANT_DB_DATABASE`, `TENANT_DB_USERNAME`, `TENANT_DB_PASSWORD`. A configuração recusa nomes inválidos e destinos remotos; a chave de outra Prefeitura é recusada pela aplicação.
2. Criar `PLATFORM_DB_*` para um **segundo banco PostgreSQL distinto**, no mesmo VPS. **Nunca publicar a porta 5432** na internet. Em `pg_hba.conf`, permitir somente o acesso local necessário e privilégios mínimos.
3. Usar **duas contas diferentes**: conta de deploy/migração (dona das tabelas e funções de auditoria, sem acesso web) e conta web com somente as permissões essenciais. A conta web não pode alterar estrutura, desativar gatilhos, executar `TRUNCATE`, usar superusuário ou possuir as tabelas de auditoria. Conceder SELECT/INSERT para auditoria, nunca UPDATE/DELETE. Aplicar grants após cada migration.
4. Restringir SSH a chaves, preferencialmente via VPN e lista de IPs; desabilitar autenticação SSH por senha, login direto de root, e proteger contas administrativas com MFA. Firewall libera ao público apenas 443 (e redirecionamento 80 quando exigido para TLS). TLS/HTTPS renovado automaticamente.
5. Definir `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://... `, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true` e `SESSION_SAME_SITE=lax`. Segredos ficam fora do Git, protegidos por permissão e backup criptografado. Não usar senha de demonstração `123456` em produção.
6. Configurar Nginx + PHP-FPM, executar `php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache` somente após validar variáveis, permissões, migrações e tarefas agendadas.
7. Ter monitoramento de espaço, memória, aplicação, TLS, logs de segurança, banco e término de backup. A trilha escolar é imutável para a aplicação; a LGPD exige política de acesso e de retenção, além de resposta a incidentes.


## HTTPS, proxy reverso e IP de origem

O Laravel recebe apenas os cabecalhos de um proxy explicitamente autorizado. Em cada VPS, configure `TRUSTED_PROXIES` com o **IP real do ultimo salto** que se conecta a aplicacao, ou sua sub-rede CIDR estritamente necessaria, separados por virgula. Deixe vazio se o Nginx entrega diretamente ao PHP-FPM sem proxy HTTP adicional. Nunca use `*` nem libere a origem da aplicacao diretamente para a internet.

Exemplo, apenas quando o proxy local realmente conecta a aplicacao por loopback: `TRUSTED_PROXIES=127.0.0.1,::1`. Nao copie esse valor cegamente entre Prefeituras. Use `php artisan config:cache` depois de configurar o `.env` de producao e confira o IP remoto observado pelo aplicativo. Valores malformados ou curinga impedem a inicializacao com essa configuracao.

O proxy deve remover ou **sobrescrever** os cabecalhos de encaminhamento recebidos do cliente e enviar somente seus valores reais de `X-Forwarded-For`, `X-Forwarded-Proto` e `X-Forwarded-Port`; a aplicacao nao confia em `X-Forwarded-Host`, `Forwarded` nem `X-Forwarded-Prefix`. Configure o dominio valido no proprio servidor web e `APP_URL=https://...` no `.env` privado, com HTTP publico redirecionado para HTTPS. Confirme o cookie de sessao `Secure` e o HSTS na resposta HTTPS de producao; conexoes HTTP ou origens nao confiaveis nao devem ativar HSTS por cabecalho falsificado.

## Implantação segura por Prefeitura

- Revisar o código no GitHub, CI verde, revisão independente e aprovação de release.
- Preparar VPS e instalar dependências suportadas. Criar banco central e municipal, executar migrations com **conta de deploy** e registrar a versão exata implantada. Não colocar dois municípios no catálogo.
- Antes de cada atualização: janela de manutenção, backup das duas bases, backup dos arquivos privados, checagem de integridade e plano de reversão. Nunca copiar o `.env` de outro contrato.
- Publicar e executar testes de fumaça: login da conta autorizada, 403/404 para fora de escopo, leitura de escolas e alunos, falhas de login em português e consultas à auditoria. Retirar o modo manutenção só depois da conferência.
- Fazer atualizações **uma Prefeitura por vez**. Se uma falhar, manter as demais isoladas e intactas.
- Reversão de código não garante reversão automática de esquema. Antes de migrations irreversíveis, planejar rollback por restauração e preservar a versão anterior.

## Backup e ensaio de restauração

- Guardar dumps **separados** de `platform` e `tenant`, mais arquivos privados, com criptografia e cópias externas a esse VPS. Nunca guardar só no mesmo volume.
- Usar `pg_dump -Fc` pela conta de backup; conferir códigos de saída e manifestos sem incluir senhas; guardar com retenção definida em contrato.
- Periodicamente restaurar **em VPS de teste isolado** usando `pg_restore`, conferir contagens, relacionamentos, hashes e acesso por papel. Uma cópia nunca verificada não equivale a backup operacional.
- Seguir as regras de minimização de dados: nunca misturar backups de duas Prefeituras, encaminhar dados escolares por e-mail nem replicar registros reais no ambiente de desenvolvimento.

## Conexão de trabalho remoto

Para trabalhar de qualquer lugar, conecte seu notebook via VPN, abra o projeto do **VPS de desenvolvimento** no VS Code com Remote SSH, edite em branch Git, execute testes e envie um PR. Depois de aprovado, o processo de implantação coloca a versão no VPS de cada Prefeitura. **Evite editar código diretamente em produção**.

Este guia é uma arquitetura e checklist operacional. Um VPS não foi contratado nem configurado e a implantação deve passar por revisão de segurança, restauração e aceite formal antes de receber dados reais.

## Leitor de documentos (OCR)

A importação de listas em PDF e foto precisa de dois pacotes do sistema, instalados uma vez na VPS (e no computador de testes):

```bash
sudo apt-get install -y tesseract-ocr tesseract-ocr-por poppler-utils
tesseract --list-langs | grep -x por
```

A leitura é feita só na própria VPS; nenhum documento é enviado para serviço externo.
