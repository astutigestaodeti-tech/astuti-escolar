<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PDO;
use RuntimeException;
use Throwable;

class ProvisionPostgresProductionRoles extends Command
{
    protected $signature = 'astuti:permissoes-pg {--confirmar= : Informe PERMISSOES-PG para autorizar o provisionamento}';

    protected $description = 'Configura as contas PostgreSQL de migracao e aplicacao, com auditoria somente de insercao.';

    private const MIGRATION_ROLE = 'astuti_migracao';

    private const APPLICATION_ROLE = 'astuti_aplicacao';

    public function handle(): int
    {
        if ($this->option('confirmar') !== 'PERMISSOES-PG') {
            $this->error('Confirmacao obrigatoria: --confirmar=PERMISSOES-PG.');

            return self::FAILURE;
        }

        $isIntegration = app()->environment('testing') && getenv('ASTUTI_PG_ROLE_INTEGRATION') === '1';
        if (! app()->environment('production') && ! $isIntegration) {
            $this->error('Comando exclusivo de producao ou do ensaio PostgreSQL descartavel de CI.');

            return self::FAILURE;
        }

        try {
            [$host, $port, $platform, $tenant] = $this->targets($isIntegration);
            $adminUser = getenv('ASTUTI_PG_ADMIN_USER');
            $adminPassword = getenv('ASTUTI_PG_ADMIN_PASSWORD');

            // O segredo administrativo so e fornecido ao processo CLI de deploy.
            // Nunca o gravar no .env consumido pelo PHP-FPM ou no config:cache.
            if (! is_string($adminUser) || $adminUser === '' || ! is_string($adminPassword) || $adminPassword === '') {
                throw new RuntimeException('Informe as credenciais administrativas exclusivamente no processo de provisionamento.');
            }

            $maintenance = $this->connect($host, $port, 'postgres', $adminUser, $adminPassword);
            $version = (int) $maintenance->query('SHOW server_version_num')->fetchColumn();
            if ($version < 170000) {
                throw new RuntimeException('O provisionamento exige PostgreSQL 17 ou superior.');
            }

            if ((int) $maintenance->query('SELECT CASE WHEN rolsuper THEN 1 ELSE 0 END FROM pg_roles WHERE rolname = current_user')->fetchColumn() !== 1) {
                throw new RuntimeException('A etapa inicial requer uma conta administrativa PostgreSQL superusuaria, nunca a conta web.');
            }

            $this->createRestrictedRole($maintenance, self::MIGRATION_ROLE);
            $this->createRestrictedRole($maintenance, self::APPLICATION_ROLE);

            $membership = $maintenance->prepare('SELECT 1 FROM pg_auth_members JOIN pg_roles ON pg_roles.oid = pg_auth_members.member WHERE pg_roles.rolname = ? LIMIT 1');
            $membership->execute([self::APPLICATION_ROLE]);
            if ($membership->fetchColumn()) {
                throw new RuntimeException('A conta web nao pode pertencer a outros papeis PostgreSQL.');
            }

            foreach ([[$platform, 'platform_audit_logs'], [$tenant, 'audit_logs']] as [$database, $auditTable]) {
                $this->provisionDatabase($maintenance, $host, $port, $adminUser, $adminPassword, $database, $auditTable);
            }

            $this->info('Papeis configurados nas duas bases. Reexecute este comando apos cada migracao, antes de reabrir a aplicacao.');
            $this->info('Senhas existentes preservadas; para contas novas, defina-as interativamente com psql \\password.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            // Nao incluir SQL, DSN nem credenciais administrativas nos logs.
            $this->error('Provisionamento interrompido (codigo '.(string) $exception->getCode().'): confira destinos, versao e direitos do administrador PostgreSQL.');

            return self::FAILURE;
        }
    }

    /** @return array{string, string, string, string} */
    private function targets(bool $isIntegration): array
    {
        $platform = config('database.connections.platform', []);
        $configuredTenants = config('tenancy.connections', []);
        $configuredTenant = is_array($configuredTenants) && count($configuredTenants) === 1
            ? reset($configuredTenants)
            : null;

        $host = (string) ($platform['host'] ?? '');
        $port = (string) ($platform['port'] ?? '');
        $platformDb = (string) ($platform['database'] ?? '');
        $tenantDb = (string) (is_array($configuredTenant)
            ? ($configuredTenant['database'] ?? '')
            : (getenv('TENANT_DB_DATABASE') ?: ''));

        if (! $isIntegration && (! is_array($configuredTenants) || count($configuredTenants) !== 1)) {
            throw new RuntimeException('Em producao, configure exatamente um municipio no VPS.');
        }

        if (($platform['driver'] ?? null) !== 'pgsql' || ! empty($platform['url'])
            || ! in_array(strtolower($host), ['127.0.0.1', 'localhost', '::1'], true)
            || ! ctype_digit($port) || (int) $port < 1 || (int) $port > 65535
            || ! $this->validDatabase($platformDb) || ! $this->validDatabase($tenantDb)
            || $platformDb === $tenantDb
            || ! in_array(strtolower((string) getenv('ASTUTI_PG_ADMIN_HOST')), [$host, '127.0.0.1', 'localhost', '::1'], true)
            || (string) getenv('ASTUTI_PG_ADMIN_PORT') !== $port) {
            throw new RuntimeException('Destinos PostgreSQL locais invalidos ou coincidentes.');
        }

        if (is_array($configuredTenant) && (
            ($configuredTenant['driver'] ?? null) !== 'pgsql'
            || (string) ($configuredTenant['host'] ?? '') !== $host
            || (string) ($configuredTenant['port'] ?? '') !== $port
        )) {
            throw new RuntimeException('Os dois bancos devem pertencer ao mesmo PostgreSQL local.');
        }

        if ($isIntegration && (! str_ends_with($platformDb, '_test') || ! str_ends_with($tenantDb, '_test'))) {
            throw new RuntimeException('O ensaio so pode usar bancos de teste descartaveis.');
        }

        return [$host, $port, $platformDb, $tenantDb];
    }

    private function validDatabase(string $name): bool
    {
        return preg_match('/\A[a-z][a-z0-9_]{0,62}\z/D', $name) === 1
            && ! in_array($name, ['postgres', 'template0', 'template1'], true);
    }

    private function connect(string $host, string $port, string $db, string $user, string $password): PDO
    {
        return new PDO("pgsql:host={$host};port={$port};dbname={$db}", $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    private function createRestrictedRole(PDO $admin, string $name): void
    {
        $lookup = $admin->prepare('SELECT 1 FROM pg_roles WHERE rolname = ?');
        $lookup->execute([$name]);

        if (! $lookup->fetchColumn()) {
            $admin->exec('CREATE ROLE '.$this->ident($name).' LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOREPLICATION PASSWORD NULL');
        } else {
            // Reaplicacao nao revoga nem troca a senha que o operador definiu.
            $admin->exec('ALTER ROLE '.$this->ident($name).' LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOREPLICATION');
        }
    }

    private function provisionDatabase(PDO $maintenance, string $host, string $port, string $adminUser, string $adminPassword, string $database, string $auditTable): void
    {
        $migration = $this->ident(self::MIGRATION_ROLE);
        $application = $this->ident(self::APPLICATION_ROLE);
        $dbName = $this->ident($database);

        $maintenance->exec("ALTER DATABASE {$dbName} OWNER TO {$migration}");
        $maintenance->exec("REVOKE CREATE, TEMPORARY ON DATABASE {$dbName} FROM PUBLIC, {$application}");
        $maintenance->exec("GRANT CONNECT ON DATABASE {$dbName} TO {$migration}, {$application}");

        $db = $this->connect($host, $port, $database, $adminUser, $adminPassword);
        $db->beginTransaction();

        try {
            $db->exec("ALTER SCHEMA public OWNER TO {$migration}");
            $db->exec('REVOKE ALL ON SCHEMA public FROM PUBLIC');
            $db->exec("REVOKE ALL ON SCHEMA public FROM {$application}");
            $db->exec("GRANT USAGE ON SCHEMA public TO {$application}");

            // Objetos antigos criados pela conta DBA tambem passam a pertencer
            // ao papel de deploy. Nao tocar em membros internos de extensoes.
            $relations = $db->query(<<<'SQL'
                SELECT c.relname, c.relkind
                FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
                WHERE n.nspname = 'public' AND c.relkind IN ('r', 'p', 'v', 'm', 'S')
                  AND NOT EXISTS (SELECT 1 FROM pg_depend d WHERE d.classid = 'pg_class'::regclass AND d.objid = c.oid AND d.deptype = 'e')
                  AND NOT EXISTS (SELECT 1 FROM pg_depend d WHERE d.classid = 'pg_class'::regclass AND d.objid = c.oid AND d.deptype IN ('a', 'i') AND d.refclassid = 'pg_class'::regclass)
                ORDER BY c.relkind, c.relname
                SQL)->fetchAll(PDO::FETCH_ASSOC);

            foreach ($relations as $relation) {
                $object = $this->ident($relation['relname']);
                $type = match ($relation['relkind']) {
                    'S' => 'SEQUENCE',
                    'v' => 'VIEW',
                    'm' => 'MATERIALIZED VIEW',
                    default => 'TABLE',
                };
                $db->exec("ALTER {$type} public.{$object} OWNER TO {$migration}");
            }

            $functions = $db->query(<<<'SQL'
                SELECT p.proname, pg_get_function_identity_arguments(p.oid) AS args
                FROM pg_proc p JOIN pg_namespace n ON n.oid = p.pronamespace
                WHERE n.nspname = 'public' AND p.prokind = 'f'
                  AND NOT EXISTS (SELECT 1 FROM pg_depend d WHERE d.classid = 'pg_proc'::regclass AND d.objid = p.oid AND d.deptype = 'e')
                SQL)->fetchAll(PDO::FETCH_ASSOC);

            foreach ($functions as $function) {
                $name = $this->ident($function['proname']);
                $db->exec("ALTER FUNCTION public.{$name}({$function['args']}) OWNER TO {$migration}");
            }

            $db->exec('REVOKE ALL ON ALL TABLES IN SCHEMA public FROM PUBLIC');
            $db->exec("REVOKE ALL ON ALL TABLES IN SCHEMA public FROM {$application}");
            $db->exec("GRANT SELECT, INSERT, UPDATE ON ALL TABLES IN SCHEMA public TO {$application}");

            $check = $db->prepare('SELECT to_regclass(?)');
            $check->execute(['public.'.$auditTable]);
            if ($check->fetchColumn() !== null) {
                $audit = $this->ident($auditTable);
                $db->exec("REVOKE ALL ON TABLE public.{$audit} FROM {$application}, PUBLIC");
                $db->exec("GRANT SELECT, INSERT ON TABLE public.{$audit} TO {$application}");
            }

            // Apenas a base central mantem as tabelas efemeras do Laravel.
            // Logout, limpeza de sessoes, cache e filas precisam de DELETE,
            // mas isso nunca se estende a usuarios, matriculas ou auditoria.
            if ($auditTable === 'platform_audit_logs') {
                foreach (['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens'] as $table) {
                    $check->execute(['public.'.$table]);
                    if ($check->fetchColumn() !== null) {
                        $db->exec('GRANT DELETE ON TABLE public.'.$this->ident($table)." TO {$application}");
                    }
                }
            }

            $db->exec('REVOKE ALL ON ALL SEQUENCES IN SCHEMA public FROM PUBLIC');
            $db->exec("REVOKE ALL ON ALL SEQUENCES IN SCHEMA public FROM {$application}");
            $db->exec("GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO {$application}");

            // Futuras tabelas, inclusive novas tabelas de auditoria, nao
            // recebem UPDATE por padrao. Depois de migrar, rode novamente
            // para liberar UPDATE somente nas tabelas operacionais existentes.
            $db->exec("ALTER DEFAULT PRIVILEGES FOR ROLE {$migration} REVOKE ALL ON TABLES FROM {$application}");
            $db->exec("ALTER DEFAULT PRIVILEGES FOR ROLE {$migration} IN SCHEMA public REVOKE ALL ON TABLES FROM {$application}");
            $db->exec("ALTER DEFAULT PRIVILEGES FOR ROLE {$migration} IN SCHEMA public GRANT SELECT, INSERT ON TABLES TO {$application}");
            $db->exec("ALTER DEFAULT PRIVILEGES FOR ROLE {$migration} IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO {$application}");

            $db->commit();
        } catch (Throwable $exception) {
            $db->rollBack();

            throw $exception;
        }
    }

    private function ident(string $value): string
    {
        return '"'.str_replace('"', '""', $value).'"';
    }
}
