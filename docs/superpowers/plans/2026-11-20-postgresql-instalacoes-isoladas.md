# PostgreSQL e instalações isoladas - Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrar o Astuti Gestão Escolar para PostgreSQL com uma instalação independente por Prefeitura, preservar os dados fictícios do notebook e comprovar o isolamento e a auditoria em testes.

**Architecture:** Cada VPS de produção executa uma única instância Laravel e duas bases PostgreSQL locais, `platform` e `tenant`, ambas exclusivas da Prefeitura. O MariaDB permanece somente como origem de migração/backup, jamais como dependência da aplicação de produção. Novas alterações ficarão na branch `feature/migracao-postgresql-instalacoes-isoladas`, baseada na cabeça do Incremento 03.

**Tech Stack:** Laravel 13, PHP 8.4.1+, PostgreSQL, PDO_PGSQL, PHPUnit, GitHub Actions, Vite, Blade, Pint.

**Spec:** `docs/superpowers/specs/2026-11-20-postgresql-instalacoes-isoladas-design.md`

## Global Constraints

- Preservar dados fictícios Monte Sereno v2: **3 escolas, 115 alunos, 2027**.
- Uma instalação de produção não pode ter acesso a bancos, arquivos ou sessões de outra Prefeitura.
- Uma conexão municipal não existe fora de um `TenantContext` autorizado.
- Nenhuma senha, dump, token ou registro real é enviado ao GitHub.
- PRs #3 e #4 continuam abertos, sem merge automático.
- Bancos MariaDB originais não podem ser destruídos, recriados, modificados ou usados como alvos de testes.
- Cada teste de integração utiliza somente bancos descartáveis nomeados `*_test`.
- Toda mudança com comportamento novo inicia com teste falhando e é verificada novamente após a correção.

## Review Focus

1. `PLATFORM_DB_URL` ou destino equivalente malicioso tenta apontar para outra base: teste deve rejeitar sem conectar.
2. Duas Prefeituras têm mesmo host/porta e bancos homônimos: teste detecta destino físico duplicado; A/B/A continua isolado.
3. Falha durante a importação deixa linhas parciais ou hash de senha alterado: teste mantém MariaDB intacto, bloqueia corte e produz diagnóstico sem segredo.
4. UPDATE/DELETE/TRUNCATE da auditoria via SQL bruto tenta adulterar trilha: teste PostgreSQL retorna erro e os registros permanecem.
5. Testes são repetidos no mesmo trio de bancos `*_test`: segunda execução passa sem resíduo, inclusive triggers e contadores.

---

### Task 1: Conexões PostgreSQL e limites de instância

**Files:**
- Modify: `config/database.php`, `config/tenancy.php`, `app/Tenancy/TenantConnectionManager.php`
- Create: `tests/Feature/Tenancy/PostgresTenantConfigurationTest.php`
- Update: `tests/Feature/Tenancy/MunicipalityIsolationTest.php`

**Interfaces:**
- Consumes: configuração Laravel `database.connections.platform`, `tenancy.connections` e `TenantContext`.
- Produces: `TenantConnectionManager::activate(Municipality $municipality): void`, `reset(): void`; banco central PostgreSQL explícito e catálogo contendo apenas a Prefeitura autorizada em produção.

- [ ] Escrever testes: central e municipal exigem `pgsql`; rejeitar `url`, alvo central idêntico, conexão municipal ausente, dois municípios no mesmo destino e troca de município fora de contexto. Incluir A/B/A.
- [ ] Executar `php artisan test --filter=PostgresTenantConfigurationTest` no runtime PHP/PG; confirmar falha específica antes da alteração.
- [ ] Adaptar os três arquivos com propriedades PostgreSQL (host, porta 5432, database, username, password, sslmode) e identidade física consistente, sem fallback silencioso ao MariaDB.
- [ ] Repetir o teste direcionado e `php artisan test`; verificar que passam.
- [ ] Commit `feat: usar PostgreSQL nas conexoes central e municipal`.

### Task 2: Triggers, migrations e auditoria em PostgreSQL

**Files:**
- Modify: `database/migrations/platform/2026_11_20_000009_protect_platform_audit_logs.php`
- Modify: `database/migrations/tenant/2026_11_20_000008_protect_audit_logs.php`
- Check and modify only as needed: `database/migrations/platform/*`, `database/migrations/tenant/*`, `app/Models/Tenant/Concerns/HasSchoolHistory.php`
- Modify: `tests/Feature/Audit/PlatformAuditTest.php`, `tests/Feature/Audit/TenantAuditTest.php`
- Create: `tests/Feature/Audit/PostgresImmutableAuditTest.php`

**Interfaces:**
- Consumes: Laravel Schema, `DB::connection('platform'|'tenant')`; PostgreSQL trigger functions.
- Produces: migrações repetíveis, auditoria append-only, bloqueio de UPDATE/DELETE/TRUNCATE em ambas as bases.

- [ ] Criar testes de escrita legítima e recusa SQL bruto, UPDATE, DELETE e TRUNCATE, incluindo remoção de histórico e rollback de transação.
- [ ] Executar testes dirigidos e observar falha por gatilhos MariaDB.
- [ ] Substituir `SIGNAL SQLSTATE` e `CREATE TRIGGER IF NOT EXISTS` por funções `plpgsql` + gatilhos PostgreSQL seguros/reexecutáveis; evitar privilégios desnecessários.
- [ ] Executar testes dirigidos e toda a suíte; limpar fixtures apenas por mecanismo exclusivo de integração, nunca em produção.
- [ ] Commit `feat: proteger auditoria imutavel no PostgreSQL`.

### Task 3: Provisionamento e cenário de demonstração

**Files:**
- Modify: `app/Console/Commands/PrepareMonteSerenoDemo.php`, `config/tenancy.php`
- Modify: `tests/Support/TwoMunicipalityTestCase.php`
- Modify: `tests/Feature/Tenancy/MunicipalityIsolationTest.php`
- Create: `tests/Feature/Console/PostgresDemoPreparationTest.php`

**Interfaces:**
- Consumes: migrations `platform` e `tenant`, `TenantContext::runFor`, comando `astuti:municipio-provisionar`.
- Produces: `astuti:demo-preparar` somente no desenvolvimento, com 3/115/2027 e login fictício funcional.

- [ ] Testar bloqueio fora de `local`/`testing`, nomes de bancos inesperados e preservação de usuário e senha existente.
- [ ] Executar teste para verificar falha do código que exige `mariadb`.
- [ ] Adaptar verificação de segurança e seeds sem apagar dados existentes.
- [ ] Executar teste dirigido, isolamento A/B/A e suíte completa duas vezes.
- [ ] Commit `feat: preparar Monte Sereno no PostgreSQL`.

### Task 4: Migração verificada dos dados fictícios MariaDB para PostgreSQL

**Files:**
- Create: `app/Console/Commands/MigrateFictitiousDataToPostgres.php`
- Create: `app/Support/DatabaseMigration/MigrationVerifier.php`
- Create: `tests/Feature/Console/MigrateFictitiousDataToPostgresTest.php`
- Create: `docs/operacao/migracao-mariadb-postgresql.md`

**Interfaces:**
- Produces: comando `astuti:migrar-dados-postgresql` com modo de simulação, comparação de contagens/relacionamentos e erro seguro; a origem MariaDB é conexão somente leitura e os alvos precisam ser bases de desenvolvimento vazias e explicitamente permitidas.

- [ ] Escrever testes que comprovem: recusa de alvo não vazio ou inesperado, hashes de senha preservados, FKs e UUIDs fiéis, interrupção em divergência, nenhuma escrita na origem e nenhum segredo no log.
- [ ] Rodar testes e observar falha por comando ainda não definido.
- [ ] Implementar cópia ordenada por dependências, com pré-validação, transação no destino, conferência pós-cópia e proibição de executar sobre bases reais; não transportar sessões ativas, cache ou filas de trabalho (sessões devem expirar após corte por segurança).
- [ ] Rodar testes com MariaDB de origem e PostgreSQL de destino **descartáveis**, repetindo cenário de erro e sucesso.
- [ ] Documentar backup, corte sob manutenção, consulta comparativa, restauração de teste e retorno sem destruir a origem.
- [ ] Commit `feat: migrar dados ficticios com verificacao e sem apagar origem`.

### Task 5: CI, manual do Debian e VPS por Prefeitura

**Files:**
- Modify: `.github/workflows/testes.yml`, `.env.example`
- Modify: `docs/arquitetura/isolamento-municipal.md`
- Create: `docs/operacao/instalacao-postgresql-debian.md`, `docs/operacao/vps-prefeitura.md`
- Modify: `tests/Support/TwoMunicipalityTestCase.php` se necessário

**Interfaces:**
- Produces: workflow usando 3 bases PostgreSQL `*_test`, `pdo_pgsql`, dois ciclos de testes, Pint, compilação Vite e verificação visual; guias sem credenciais.

- [ ] Preparar um job PostgreSQL e rodar CI inicialmente para observar a falha da suíte antes do corte definitivo do driver.
- [ ] Substituir serviço MariaDB do workflow por PostgreSQL, provisionar central e dois tenants descartáveis, testar nomes e privilégios.
- [ ] Executar `php artisan test` duas vezes, `vendor/bin/pint --test`, `npm run build` e `npm run test:ui`; investigar qualquer falha observada.
- [ ] Documentar migrações, contas individuais, HTTPS, firewall, backups externos, recuperação, atualizações por cliente e operação por SSH/VPN, sem criar VPS real.
- [ ] Commit `ci: validar PostgreSQL e documentar VPS exclusivo`.
- [ ] Abrir PR draft sobre `feature/incremento-03-cadastro-importacao` apenas após verificar o resultado do CI; solicitar revisão independente antes de merge.

## Self-review

- Critérios do design: escopo coberto pelas Tasks 1 a 5; CI, migração real no notebook e operação VPS explicitamente documentados.
- Dependências: Tasks 2 e 3 consomem a conexão PG de Task 1; Task 4 depende das migrações e da demonstração; Task 5 é o portão de qualidade.
- Risco operacional: execução no notebook depende de acesso local ao Debian do Jefferson; a branch/PR pode ser concluída antes de rodar a migração dos dados do notebook.
- Não alegar testes verdes sem saída verificável do runtime ou do GitHub Actions.
