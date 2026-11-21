<?php

namespace Tests\Feature\Console;

use App\Support\DatabaseMigration\FictitiousDataTransfer;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\DatabaseMigration\DisposableMigrationDatabases;
use Tests\Support\DatabaseMigration\FictitiousMigrationFixture;
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

    public function test_fixture_tem_3_escolas_115_alunos_2027_sem_dados_reais(): void
    {
        $databases = DisposableMigrationDatabases::create();
        ['sourcePlatform' => $sourcePlatform, 'sourceTenant' => $sourceTenant,
            'targetPlatform' => $targetPlatform, 'targetTenant' => $targetTenant] = $databases;

        FictitiousMigrationFixture::seed($sourcePlatform, $sourceTenant);

        $names = [];

        foreach ($databases as $key => $connection) {
            $isSource = str_starts_with($key, 'source');
            $this->assertSame($isSource ? 'mariadb' : 'pgsql', $connection->getDriverName());

            $name = $connection->selectOne($isSource
                ? 'SELECT DATABASE() AS name'
                : 'SELECT current_database() AS name')->name;
            $this->assertStringEndsWith('_test', $name);
            $this->assertSame($connection->getDatabaseName(), $name);
            $names[] = $name;
        }

        $this->assertCount(4, array_unique($names));

        foreach ([
            [$sourcePlatform, $targetPlatform, [
                'municipalities' => 1,
                'users' => 1,
                'municipality_provision_attempts' => 1,
                'memberships' => 1,
                'school_scopes' => 1,
                'platform_audit_logs' => 1,
            ]],
            [$sourceTenant, $targetTenant, [
                'schools' => 3,
                'school_years' => 1,
                'students' => 115,
                'school_classes' => 3,
                'student_enrollments' => 115,
                'audit_logs' => 1,
            ]],
        ] as [$source, $target, $counts]) {
            foreach ($counts as $table => $count) {
                $this->assertEqualsCanonicalizing(
                    $source->getSchemaBuilder()->getColumnListing($table),
                    $target->getSchemaBuilder()->getColumnListing($table),
                    $table,
                );
                $this->assertSame($count, $source->table($table)->count(), $table);
                $this->assertSame(0, $target->table($table)->count(), $table);
            }
        }

        $municipality = $sourcePlatform->table('municipalities')->sole();
        $user = $sourcePlatform->table('users')->sole();
        $membership = $sourcePlatform->table('memberships')->sole();
        $scope = $sourcePlatform->table('school_scopes')->sole();

        $this->assertSame('monte-sereno', $municipality->slug);
        $this->assertSame('monte_sereno', $municipality->db_key);
        $this->assertSame('fixture.monte-sereno@astuti.invalid', $user->email);
        $this->assertTrue(Hash::check('senha-sintetica-apenas-fixture', $user->password));
        $this->assertSame($user->id, $membership->user_id);
        $this->assertSame($municipality->id, $membership->municipality_id);
        $this->assertSame($membership->id, $scope->membership_id);
        $this->assertTrue($sourceTenant->table('schools')->where('id', $scope->school_id)->exists());
        $this->assertSame($user->id, $sourcePlatform->table('platform_audit_logs')->sole()->user_id);
        $this->assertSame($user->id, $sourceTenant->table('audit_logs')->sole()->user_id);

        $this->assertSame(2027, (int) $sourceTenant->table('school_years')->sole()->year);
        $this->assertSame(0, $sourceTenant->table('students')->where('full_name', 'not like', 'Estudante fictício %')->count());
        $this->assertSame(115, $sourceTenant->table('student_enrollments')->distinct()->count('student_id'));
        $this->assertTrue($targetPlatform->getSchemaBuilder()->hasTable('jobs'));
    }

    public function test_simulacao_nao_escreve_e_copia_preserva_conteudo_uuid_hash_e_contagens(): void
    {
        ['sourcePlatform' => $sourcePlatform, 'sourceTenant' => $sourceTenant,
            'targetPlatform' => $targetPlatform, 'targetTenant' => $targetTenant] = DisposableMigrationDatabases::create();

        FictitiousMigrationFixture::seed($sourcePlatform, $sourceTenant);

        $sourceBefore = $this->snapshot($sourcePlatform, $sourceTenant);
        $empty = $this->snapshot($targetPlatform, $targetTenant);
        $transfer = new FictitiousDataTransfer;
        $counts = [
            'platform' => [
                'municipalities' => 1,
                'users' => 1,
                'municipality_provision_attempts' => 1,
                'memberships' => 1,
                'school_scopes' => 1,
                'platform_audit_logs' => 1,
            ],
            'tenant' => [
                'schools' => 3,
                'school_years' => 1,
                'students' => 115,
                'school_classes' => 3,
                'student_enrollments' => 115,
                'audit_logs' => 1,
            ],
        ];

        $this->assertSame($counts, $transfer->run($sourcePlatform, $sourceTenant, $targetPlatform, $targetTenant, false));
        $this->assertSame($empty, $this->snapshot($targetPlatform, $targetTenant));
        $this->assertSame($sourceBefore, $this->snapshot($sourcePlatform, $sourceTenant));

        $this->assertSame($counts, $transfer->run($sourcePlatform, $sourceTenant, $targetPlatform, $targetTenant, true));

        $copied = $this->snapshot($targetPlatform, $targetTenant);

        foreach ($sourceBefore as $database => $tables) {
            foreach ($tables as $table => $rows) {
                $this->assertEquals($rows, $copied[$database][$table], $table);
            }
        }

        $this->assertSame(array_column($sourceBefore['tenant']['students'], 'id'), array_column($copied['tenant']['students'], 'id'));
        $this->assertSame($sourceBefore['platform']['users'][0]['password'], $copied['platform']['users'][0]['password']);
        $this->assertTrue(Hash::check('senha-sintetica-apenas-fixture', $copied['platform']['users'][0]['password']));
        $this->assertSame(2027, (int) $copied['tenant']['school_years'][0]['year']);

        $sequence = $targetPlatform->selectOne("SELECT currval(pg_get_serial_sequence('municipality_provision_attempts', 'id')) AS id");
        $this->assertSame(27, (int) $sequence->id);
        $this->assertSame($sourceBefore, $this->snapshot($sourcePlatform, $sourceTenant));
    }

    private function snapshot(Connection $platform, Connection $tenant): array
    {
        $snapshot = [];

        foreach ([
            'platform' => [$platform, ['municipalities', 'users', 'municipality_provision_attempts', 'memberships', 'school_scopes', 'platform_audit_logs']],
            'tenant' => [$tenant, ['schools', 'school_years', 'students', 'school_classes', 'student_enrollments', 'audit_logs']],
        ] as $database => [$connection, $tables]) {
            foreach ($tables as $table) {
                $query = $connection->table($table);

                if ($table === 'school_scopes') {
                    $query->orderBy('membership_id')->orderBy('school_id');
                } else {
                    $query->orderBy('id');
                }

                $snapshot[$database][$table] = $query->get()->map(static function (object $row): array {
                    $values = (array) $row;

                    foreach (['before', 'after'] as $column) {
                        if (isset($values[$column])) {
                            $values[$column] = json_decode($values[$column], true, 512, JSON_THROW_ON_ERROR);
                        }
                    }

                    return $values;
                })->all();
            }
        }

        return $snapshot;
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
