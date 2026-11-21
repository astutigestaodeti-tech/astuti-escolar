<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\DatabaseMigration\DisposableMigrationDatabases;
use Tests\TestCase;

class MigrateFictitiousDataToPostgresTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('ASTUTI_MIGRATION_INTEGRATION') !== '1') {
            $this->markTestSkipped('Exige os serviços descartáveis MariaDB e PostgreSQL do CI.');
        }
    }

    #[DataProvider('configuracoesInseguras')]
    public function test_ambientes_descartaveis_exigem_quatro_bancos_test_e_loopback(string $variable, string $value, string $message): void
    {
        $previous = getenv($variable);
        $connections = DB::getConnections();
        putenv($variable.'='.$value);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        try {
            DisposableMigrationDatabases::create();
        } finally {
            putenv($previous === false ? $variable : $variable.'='.$previous);
            $this->assertSame($connections, DB::getConnections());
        }
    }

    public static function configuracoesInseguras(): array
    {
        return [
            'origem central fora de teste' => ['ASTUTI_MIGRATION_SOURCE_PLATFORM_DB', 'astuti_platform_dev', '_test'],
            'origem municipal fora de teste' => ['ASTUTI_MIGRATION_SOURCE_TENANT_DB', 'astuti_monte_sereno_dev', '_test'],
            'destino central fora de teste' => ['ASTUTI_MIGRATION_TARGET_PLATFORM_DB', 'astuti_platform_dev', '_test'],
            'destino municipal fora de teste' => ['ASTUTI_MIGRATION_TARGET_TENANT_DB', 'astuti_monte_sereno_dev', '_test'],
            'origem fora de loopback' => ['ASTUTI_MIGRATION_MARIADB_HOST', 'mariadb.example.invalid', 'loopback'],
            'destino fora de loopback' => ['ASTUTI_MIGRATION_POSTGRES_HOST', 'postgres.example.invalid', 'loopback'],
            'destinos duplicados' => ['ASTUTI_MIGRATION_TARGET_TENANT_DB', 'astuti_migration_target_platform_test', 'distintos'],
            'integração desativada' => ['ASTUTI_MIGRATION_INTEGRATION', '0', 'ASTUTI_MIGRATION_INTEGRATION=1'],
            'fora do GitHub Actions' => ['GITHUB_ACTIONS', 'false', 'GitHub Actions'],
        ];
    }
}
