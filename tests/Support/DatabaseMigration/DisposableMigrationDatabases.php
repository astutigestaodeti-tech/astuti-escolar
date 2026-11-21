<?php

namespace Tests\Support\DatabaseMigration;

use Illuminate\Database\Connection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class DisposableMigrationDatabases
{
    private const SOURCE_MIGRATIONS = [
        'platform' => [
            '2026_11_20_000001_create_municipalities_table.php',
            '2026_11_20_000002_create_municipality_provision_attempts_table.php',
            '2026_11_20_000003_create_platform_users_table.php',
            '2026_11_20_000004_create_memberships_table.php',
            '2026_11_20_000005_create_school_scopes_table.php',
            '2026_11_20_000008_create_platform_audit_logs_table.php',
            '2026_11_20_000010_add_two_factor_to_platform_users.php',
        ],
        'tenant' => [
            '2026_11_20_000001_create_schools_table.php',
            '2026_11_20_000002_create_school_years_table.php',
            '2026_11_20_000003_create_school_classes_table.php',
            '2026_11_20_000004_create_students_table.php',
            '2026_11_20_000005_create_student_enrollments_table.php',
            '2026_11_20_000006_create_audit_logs_table.php',
            '2026_11_20_000007_add_soft_deletes_to_school_records.php',
        ],
    ];

    /**
     * @return array{sourcePlatform: Connection, sourceTenant: Connection, targetPlatform: Connection, targetTenant: Connection}
     */
    public static function create(): array
    {
        self::assertIntegrationEnvironment();

        $plans = [
            'sourcePlatform' => [self::testDatabaseName('ASTUTI_MIGRATION_SOURCE_PLATFORM_DB'), 'mariadb', 'platform'],
            'sourceTenant' => [self::testDatabaseName('ASTUTI_MIGRATION_SOURCE_TENANT_DB'), 'mariadb', 'tenant'],
            'targetPlatform' => [self::testDatabaseName('ASTUTI_MIGRATION_TARGET_PLATFORM_DB'), 'pgsql', 'platform'],
            'targetTenant' => [self::testDatabaseName('ASTUTI_MIGRATION_TARGET_TENANT_DB'), 'pgsql', 'tenant'],
        ];
        $servers = [
            'mariadb' => self::server('mariadb', 'ASTUTI_MIGRATION_MARIADB'),
            'pgsql' => self::server('pgsql', 'ASTUTI_MIGRATION_POSTGRES'),
        ];

        $suffix = '_'.bin2hex(random_bytes(8)).'_test';
        $databases = array_map(static fn (array $plan): string => substr($plan[0], 0, -5).$suffix, $plans);
        $names = [...array_values($databases), ...array_column($servers, 'database')];

        if (count(array_unique($names)) !== count($names)) {
            throw new InvalidArgumentException('Os bancos de migração devem ser distintos.');
        }

        $admins = [];

        foreach ($servers as $driver => $settings) {
            $admins[$driver] = DB::build($settings);
            $admins[$driver]->getPdo();
        }

        $connections = [];

        foreach ($plans as $key => [, $driver, $schema]) {
            $database = $databases[$key];
            $admin = $admins[$driver];
            $sql = 'CREATE DATABASE '.$admin->getQueryGrammar()->wrap($database);

            if ($driver === 'pgsql') {
                $sql .= " WITH TEMPLATE template0 LOCALE_PROVIDER icu ICU_LOCALE 'pt-BR'";
            }

            $admin->statement($sql);

            if ($driver === 'pgsql') {
                $locale = $admin->selectOne('SELECT datlocprovider, datlocale FROM pg_database WHERE datname = ?', [$database]);

                if ($locale?->datlocprovider !== 'i' || strtolower((string) $locale->datlocale) !== 'pt-br') {
                    throw new RuntimeException("Banco de teste PostgreSQL sem ordenação ICU pt-BR: {$database}.");
                }
            }

            $settings = array_replace($servers[$driver], ['database' => $database]);
            self::migrate($schema, $settings);
            config()->set("database.connections.{$database}", $settings);
            $connections[$key] = DB::connection($database);
        }

        return $connections;
    }

    public static function assertIntegrationEnvironment(): void
    {
        if (! app()->environment('testing') || getenv('ASTUTI_MIGRATION_INTEGRATION') !== '1') {
            throw new InvalidArgumentException('Exige APP_ENV=testing e ASTUTI_MIGRATION_INTEGRATION=1.');
        }

        if (getenv('GITHUB_ACTIONS') !== 'true') {
            throw new InvalidArgumentException('Os bancos descartáveis exigem os serviços do GitHub Actions.');
        }
    }

    private static function testDatabaseName(string $variable): string
    {
        $name = (string) getenv($variable);

        // Reserva 17 caracteres para o identificador da execução no limite de 63 bytes do PostgreSQL.
        if (preg_match('/\A[a-z][a-z0-9_]{0,40}_test\z/', $name) !== 1) {
            throw new InvalidArgumentException("{$variable} deve conter um nome de banco válido terminado em _test.");
        }

        return $name;
    }

    private static function server(string $driver, string $prefix): array
    {
        $host = (string) getenv($prefix.'_HOST');
        $port = filter_var(getenv($prefix.'_PORT'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 65535],
        ]);
        $username = (string) getenv($prefix.'_USER');
        $password = (string) getenv($prefix.'_PASSWORD');

        // Endereços numéricos evitam resolução de nomes e o socket padrão do cliente MariaDB.
        if (! in_array($host, ['127.0.0.1', '::1'], true)) {
            throw new InvalidArgumentException("{$prefix}_HOST deve ser um endereço de loopback.");
        }

        if ($port === false || $username === '' || $password === '') {
            throw new InvalidArgumentException("Configuração incompleta do serviço {$prefix}.");
        }

        // Reaproveita apenas os padrões de esquema, sem URLs, sockets ou hosts da aplicação.
        return array_replace(
            Arr::only(config("database.connections.{$driver}"), ['charset', 'collation', 'prefix_indexes', 'strict', 'search_path']),
            [
                'driver' => $driver,
                'url' => null,
                'host' => $host,
                'port' => $port,
                'database' => self::testDatabaseName($prefix.'_ADMIN_DB'),
                'username' => $username,
                'password' => $password,
                'unix_socket' => '',
                'prefix' => '',
            ],
        );
    }

    private static function migrate(string $schema, array $settings): void
    {
        $configKey = "database.connections.{$schema}";
        $original = config($configKey);
        $directory = database_path("migrations/{$schema}");
        $paths = $settings['driver'] === 'mariadb'
            ? array_map(static fn (string $file): string => "{$directory}/{$file}", self::SOURCE_MIGRATIONS[$schema])
            : [$directory];

        // As migrations referenciam platform e tenant por nome.
        DB::purge($schema);
        config()->set($configKey, $settings);

        try {
            $status = Artisan::call('migrate', [
                '--database' => $schema,
                '--path' => $paths,
                '--realpath' => true,
                '--force' => true,
            ]);

            if ($status !== 0) {
                throw new RuntimeException("Falha ao preparar o esquema {$schema} para {$settings['driver']}.");
            }
        } finally {
            DB::purge($schema);
            config()->set($configKey, $original);
        }
    }
}
