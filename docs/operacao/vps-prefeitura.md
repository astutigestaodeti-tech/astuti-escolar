# Uma instalação de produção por Prefeitura, em VPS exclusivo

**Arquitetura aprovada:** cada Prefeitura contratante recebe um VPS virtual exclusivo em datacenter, **uma aplicação Astuti**, **dois bancos PostgreSQL locais** (`platform` e `tenant`), suas próprias contas, domínio, arquivos, logs e backups. Outro cliente nunca é cadastrado no mesmo VPS. Hardware físico dedicado fica reservado aos editais que o exigirem. Contratar uma VPS não foi autorizado e nenhuma foi criada nesta etapa.

## Versão do banco e extensões

- **PostgreSQL 17 ou superior é obrigatório.** As verificações de localização ICU pt-BR consultam `pg_database.datlocale`. Debian 12 oferece PostgreSQL 15 por padrão e Debian 13 oferece PostgreSQL 17; confira a versão do servidor com `SHOW server_version` antes de provisionar a VPS.
- Criar os dois bancos com `TEMPLATE template0 LOCALE_PROVIDER icu ICU_LOCALE 'pt-BR'`.
- Instalar `unaccent` e `pg_trgm` e executar todas as migrations **pela conta exclusiva de migração**, dona do esquema e dos objetos. A conta web não deve instalar extensões nem ser proprietária de tabelas ou funções.

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
