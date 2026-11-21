<?php

namespace Tests\Feature\Console;

use App\Support\DatabaseMigration\FictitiousDataTransfer;
use App\Support\DatabaseMigration\PartialCommitException;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\DatabaseMigration\DisposableMigrationDatabases;
use Tests\Support\DatabaseMigration\FictitiousMigrationFixture;
use Tests\TestCase;

class MigrateFictitiousDataToPostgresTest extends TestCase
{
    /** Colunas criadas depois da era MariaDB: existem só no PostgreSQL, com valor padrão. */
    private const TARGET_ONLY_COLUMNS = [
        'users' => ['two_factor_failed_attempts', 'two_factor_locked_until'],
    ];

    private const EXPECTED_COUNTS = [
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
        $connectionNames = array_keys(DB::getConnections());
        putenv($variable.'='.$value);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        try {
            DisposableMigrationDatabases::create();
        } finally {
            putenv($previous === false ? $variable : $variable.'='.$previous);
            $this->assertSame($connectionNames, array_keys(DB::getConnections()));
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
            [$sourcePlatform, $targetPlatform, self::EXPECTED_COUNTS['platform']],
            [$sourceTenant, $targetTenant, self::EXPECTED_COUNTS['tenant']],
        ] as [$source, $target, $counts]) {
            foreach ($counts as $table => $count) {
                $this->assertEqualsCanonicalizing(
                    array_merge($source->getSchemaBuilder()->getColumnListing($table), self::TARGET_ONLY_COLUMNS[$table] ?? []),
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

    public function test_copia_normaliza_email_misto_sem_alterar_a_origem_nem_o_hash(): void
    {
        ['sourcePlatform' => $sourcePlatform, 'sourceTenant' => $sourceTenant,
            'targetPlatform' => $targetPlatform, 'targetTenant' => $targetTenant] = DisposableMigrationDatabases::create();

        FictitiousMigrationFixture::seed($sourcePlatform, $sourceTenant);
        $sourcePlatform->table('users')->update(['email' => 'Fixture.Monte-Sereno@astuti.invalid']);
        $originalHash = $sourcePlatform->table('users')->sole()->password;

        (new FictitiousDataTransfer)->run(
            $sourcePlatform, $sourceTenant, $targetPlatform, $targetTenant, true,
        );

        $this->assertSame('Fixture.Monte-Sereno@astuti.invalid', $sourcePlatform->table('users')->sole()->email);
        $this->assertSame('fixture.monte-sereno@astuti.invalid', $targetPlatform->table('users')->sole()->email);
        $this->assertSame($originalHash, $targetPlatform->table('users')->sole()->password);
    }

    public function test_simulacao_nao_escreve_e_copia_preserva_conteudo_uuid_hash_e_contagens(): void
    {
        ['sourcePlatform' => $sourcePlatform, 'sourceTenant' => $sourceTenant,
            'targetPlatform' => $targetPlatform, 'targetTenant' => $targetTenant] = DisposableMigrationDatabases::create();

        FictitiousMigrationFixture::seed($sourcePlatform, $sourceTenant);

        $sourceBefore = $this->snapshot($sourcePlatform, $sourceTenant);
        $empty = $this->snapshot($targetPlatform, $targetTenant);
        $transfer = new FictitiousDataTransfer;

        $this->assertSame(self::EXPECTED_COUNTS, $transfer->run($sourcePlatform, $sourceTenant, $targetPlatform, $targetTenant, false));
        $this->assertSame($empty, $this->snapshot($targetPlatform, $targetTenant));
        $this->assertSame($sourceBefore, $this->snapshot($sourcePlatform, $sourceTenant));

        $this->assertSame(self::EXPECTED_COUNTS, $transfer->run($sourcePlatform, $sourceTenant, $targetPlatform, $targetTenant, true));

        $copied = $this->snapshot($targetPlatform, $targetTenant);
        $this->assertTargetOnlyDefaults($copied);
        $copied = $this->withoutTargetOnlyColumns($copied);

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

    public function test_sete_auditorias_preservam_microssegundos_ao_migrar_de_mariadb_para_postgresql(): void
    {
        ['sourcePlatform' => $sourcePlatform, 'sourceTenant' => $sourceTenant,
            'targetPlatform' => $targetPlatform, 'targetTenant' => $targetTenant] = DisposableMigrationDatabases::create();

        FictitiousMigrationFixture::seed($sourcePlatform, $sourceTenant);
        $base = (array) $sourcePlatform->table('platform_audit_logs')->sole();
        $expected = [
            2 => '2027-02-01 12:00:00.123400',
            3 => '2027-02-01 12:00:00.120000',
            4 => '2027-02-01 12:00:00.100000',
            5 => '2027-02-01 12:00:00.000100',
            6 => '2027-02-01 12:00:00.000000',
            7 => '2027-02-01 12:00:00.123450',
        ];

        foreach ($expected as $number => $occurredAt) {
            $record = $base;
            $record['id'] = sprintf('90000000-0000-4000-8000-%012d', $number);
            $record['request_id'] = sprintf('a0000000-0000-4000-8000-%012d', $number);
            $record['occurred_at'] = $occurredAt;
            $sourcePlatform->table('platform_audit_logs')->insert($record);
        }

        $counts = (new FictitiousDataTransfer)->run(
            $sourcePlatform, $sourceTenant, $targetPlatform, $targetTenant, true,
        );

        $this->assertSame(7, $counts['platform']['platform_audit_logs']);
        $this->assertSame(7, $targetPlatform->table('platform_audit_logs')->count());

        foreach ($expected as $number => $occurredAt) {
            $id = sprintf('90000000-0000-4000-8000-%012d', $number);
            $rendered = $targetPlatform->selectOne(
                "SELECT to_char(occurred_at, 'YYYY-MM-DD HH24:MI:SS.US') AS value FROM platform_audit_logs WHERE id = ?",
                [$id],
            )->value;

            $this->assertSame($occurredAt, $rendered);
        }
    }

    public function test_diagnostico_informa_etapa_e_tabela_sem_expor_alunos(): void
    {
        ['sourcePlatform' => $sourcePlatform, 'sourceTenant' => $sourceTenant,
            'targetPlatform' => $targetPlatform, 'targetTenant' => $targetTenant] = DisposableMigrationDatabases::create();

        FictitiousMigrationFixture::seed($sourcePlatform, $sourceTenant);

        $events = [];
        $onStage = static function (string $stage, string $table) use (&$events): void {
            $events[] = "{$stage}:{$table}";
        };

        (new FictitiousDataTransfer)->run(
            $sourcePlatform, $sourceTenant, $targetPlatform, $targetTenant, true, null, $onStage,
        );

        $this->assertContains('inserindo:municipalities', $events);
        $this->assertContains('conferindo:students', $events);
        $this->assertContains('conferindo:audit_logs', $events);
        $this->assertContains('ajustando:municipality_provision_attempts', $events);
        $this->assertStringNotContainsString('Estudante fictício', implode(' ', $events));
        $this->assertStringNotContainsString('senha', implode(' ', $events));
    }

    #[DataProvider('obstaculosDaCopia')]
    public function test_destino_nao_vazio_e_fk_invalida_bloqueiam_sem_escrever(bool $preencherDestino): void
    {
        ['sourcePlatform' => $sourcePlatform, 'sourceTenant' => $sourceTenant,
            'targetPlatform' => $targetPlatform, 'targetTenant' => $targetTenant] = DisposableMigrationDatabases::create();

        FictitiousMigrationFixture::seed($sourcePlatform, $sourceTenant);

        if ($preencherDestino) {
            $targetTenant->table('schools')->insert([
                'id' => '40000000-0000-4000-8000-000000000099',
                'name' => 'Escola fictícia já presente',
                'status' => 'active',
            ]);
        } else {
            $sourcePlatform->statement('SET SESSION FOREIGN_KEY_CHECKS = 0');

            try {
                $this->assertSame(1, $sourcePlatform->table('memberships')->update([
                    'user_id' => '20000000-0000-4000-8000-000000000099',
                ]));
            } finally {
                $sourcePlatform->statement('SET SESSION FOREIGN_KEY_CHECKS = 1');
            }
        }

        $sources = [$sourcePlatform, $sourceTenant];

        foreach ($sources as $source) {
            $source->statement('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
        }

        $sessionBefore = array_map($this->sessionSettings(...), $sources);
        $sourceBefore = $this->snapshot($sourcePlatform, $sourceTenant);
        $targetBefore = $this->snapshot($targetPlatform, $targetTenant);
        $failure = null;

        try {
            (new FictitiousDataTransfer)->run($sourcePlatform, $sourceTenant, $targetPlatform, $targetTenant, true);
        } catch (RuntimeException $exception) {
            $failure = $exception;
        }

        if ($preencherDestino) {
            $this->assertInstanceOf(RuntimeException::class, $failure);
            $this->assertStringContainsString('Destino contem registros', $failure->getMessage());
        } else {
            $this->assertInstanceOf(QueryException::class, $failure);
            $this->assertSame('23503', $failure->getCode());
        }

        foreach ([$sourcePlatform, $sourceTenant, $targetPlatform, $targetTenant] as $connection) {
            $this->assertSame(0, $connection->transactionLevel());
        }

        foreach (['platform' => $targetPlatform, 'tenant' => $targetTenant] as $database => $connection) {
            foreach ($targetBefore[$database] as $table => $rows) {
                $this->assertSame(count($rows), $connection->table($table)->count(), $table);
            }
        }

        $this->assertSame($targetBefore, $this->snapshot($targetPlatform, $targetTenant));
        $this->assertSame($sourceBefore, $this->snapshot($sourcePlatform, $sourceTenant));
        $this->assertSame($sourceBefore['platform']['users'][0]['password'], $sourcePlatform->table('users')->sole()->password);
        $this->assertSame($sessionBefore, array_map($this->sessionSettings(...), $sources));
    }

    public function test_falha_antes_do_commit_reverte_ambos_destinos(): void
    {
        ['sourcePlatform' => $sourcePlatform, 'sourceTenant' => $sourceTenant,
            'targetPlatform' => $targetPlatform, 'targetTenant' => $targetTenant] = DisposableMigrationDatabases::create();

        FictitiousMigrationFixture::seed($sourcePlatform, $sourceTenant);

        $sourceTenant->statement('SET SESSION FOREIGN_KEY_CHECKS = 0');

        try {
            $this->assertSame(1, $sourceTenant->table('student_enrollments')
                ->where('id', '70000000-0000-4000-8000-000000000115')
                ->update(['school_year_id' => '80000000-0000-4000-8000-000000000099']));
        } finally {
            $sourceTenant->statement('SET SESSION FOREIGN_KEY_CHECKS = 1');
        }

        $sourceBefore = $this->snapshot($sourcePlatform, $sourceTenant);
        $targetBefore = $this->snapshot($targetPlatform, $targetTenant);
        $centralInserted = false;
        $municipalInserted = false;

        DB::listen(static function (QueryExecuted $query) use ($targetPlatform, $targetTenant, &$centralInserted, &$municipalInserted): void {
            if ($query->connection === $targetPlatform && str_starts_with($query->sql, 'insert into "platform_audit_logs"')) {
                $centralInserted = true;
            }

            if ($query->connection === $targetTenant && str_starts_with($query->sql, 'insert into "students"')) {
                $municipalInserted = true;
            }
        });

        try {
            (new FictitiousDataTransfer)->run($sourcePlatform, $sourceTenant, $targetPlatform, $targetTenant, true);
            $this->fail('A chave estrangeira inválida deveria interromper a cópia municipal.');
        } catch (QueryException $exception) {
            $this->assertSame('23503', $exception->getCode());
        }

        $this->assertTrue($centralInserted);
        $this->assertTrue($municipalInserted);
        $this->assertSame($targetBefore, $this->snapshot($targetPlatform, $targetTenant));
        $this->assertSame($sourceBefore, $this->snapshot($sourcePlatform, $sourceTenant));

        foreach ([$sourcePlatform, $sourceTenant, $targetPlatform, $targetTenant] as $connection) {
            $this->assertSame(0, $connection->transactionLevel());
        }
    }

    public function test_falha_entre_commits_explica_estado_parcial_e_recusa_retry(): void
    {
        ['sourcePlatform' => $sourcePlatform, 'sourceTenant' => $sourceTenant,
            'targetPlatform' => $targetPlatform, 'targetTenant' => $targetTenant] = DisposableMigrationDatabases::create();

        FictitiousMigrationFixture::seed($sourcePlatform, $sourceTenant);

        $sourceBefore = $this->snapshot($sourcePlatform, $sourceTenant);
        $targetBefore = $this->snapshot($targetPlatform, $targetTenant);
        $transfer = new FictitiousDataTransfer;
        $cause = new RuntimeException('Detalhe interno da falha simulada.');
        $failure = null;

        try {
            $transfer->run($sourcePlatform, $sourceTenant, $targetPlatform, $targetTenant, true, static fn () => throw $cause);
        } catch (RuntimeException $exception) {
            $failure = $exception;
        }

        foreach ([$sourcePlatform, $sourceTenant, $targetPlatform, $targetTenant] as $connection) {
            $this->assertSame(0, $connection->transactionLevel());
        }

        $partial = $this->snapshot($targetPlatform, $targetTenant);
        $this->assertSame($targetBefore['platform'], $partial['platform']);
        $this->assertEquals($sourceBefore['tenant'], $partial['tenant']);
        $this->assertSame($sourceBefore, $this->snapshot($sourcePlatform, $sourceTenant));

        try {
            $transfer->run($sourcePlatform, $sourceTenant, $targetPlatform, $targetTenant, true);
            $this->fail('A tentativa seguinte deveria recusar o destino municipal preenchido.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Destino contem registros', $exception->getMessage());
        }

        $this->assertSame($partial, $this->snapshot($targetPlatform, $targetTenant));
        $this->assertSame($sourceBefore, $this->snapshot($sourcePlatform, $sourceTenant));

        $this->assertInstanceOf(PartialCommitException::class, $failure);
        $this->assertSame($cause, $failure->getPrevious());
        $this->assertStringContainsString('destino municipal', $failure->getMessage());
        $this->assertStringContainsString('destino central', $failure->getMessage());
        $this->assertStringNotContainsString($cause->getMessage(), $failure->getMessage());

        // Ensaia a recuperação usando destinos novos. O par parcial fica preservado para inspeção.
        ['targetPlatform' => $recoveryPlatform, 'targetTenant' => $recoveryTenant] = DisposableMigrationDatabases::create();

        $this->assertSame(self::EXPECTED_COUNTS, $transfer->run($sourcePlatform, $sourceTenant, $recoveryPlatform, $recoveryTenant, false));
        $this->assertSame(self::EXPECTED_COUNTS, $transfer->run($sourcePlatform, $sourceTenant, $recoveryPlatform, $recoveryTenant, true));
        $this->assertEquals($sourceBefore, $this->withoutTargetOnlyColumns($this->snapshot($recoveryPlatform, $recoveryTenant)));
        $this->assertSame($partial, $this->snapshot($targetPlatform, $targetTenant));
        $this->assertSame($sourceBefore, $this->snapshot($sourcePlatform, $sourceTenant));
    }

    public function test_escopo_escolar_e_usuarios_auditados_referenciam_registros_existentes(): void
    {
        ['sourcePlatform' => $sourcePlatform, 'sourceTenant' => $sourceTenant,
            'targetPlatform' => $targetPlatform, 'targetTenant' => $targetTenant] = DisposableMigrationDatabases::create();

        FictitiousMigrationFixture::seed($sourcePlatform, $sourceTenant);

        foreach ([[$sourcePlatform, 'platform_audit_logs'], [$sourceTenant, 'audit_logs']] as [$source, $table]) {
            $audit = (array) $source->table($table)->sole();
            $audit['id'] = '90000000-0000-4000-8000-000000000099';
            $audit['user_id'] = null;
            $audit['role'] = null;
            $source->table($table)->insert($audit);
        }

        (new FictitiousDataTransfer)->run($sourcePlatform, $sourceTenant, $targetPlatform, $targetTenant, true);

        $scope = $targetPlatform->table('school_scopes')->sole();
        $user = $targetPlatform->table('users')->sole();

        $this->assertTrue($targetTenant->table('schools')->where('id', $scope->school_id)->exists());
        $this->assertSame($user->id, $targetPlatform->table('platform_audit_logs')->whereNotNull('user_id')->sole()->user_id);
        $this->assertSame($user->id, $targetTenant->table('audit_logs')->whereNotNull('user_id')->sole()->user_id);
        $this->assertSame(1, $targetPlatform->table('platform_audit_logs')->whereNull('user_id')->count());
        $this->assertSame(1, $targetTenant->table('audit_logs')->whereNull('user_id')->count());
    }

    #[DataProvider('referenciasOrfas')]
    public function test_referencia_orfa_e_recusada_sem_escrever(string $database, string $table, string $column, bool $execute): void
    {
        $databases = DisposableMigrationDatabases::create();
        ['sourcePlatform' => $sourcePlatform, 'sourceTenant' => $sourceTenant,
            'targetPlatform' => $targetPlatform, 'targetTenant' => $targetTenant] = $databases;

        FictitiousMigrationFixture::seed($sourcePlatform, $sourceTenant);

        $this->assertSame(1, $databases[$database]->table($table)->update([$column => 'f0000000-0000-4000-8000-000000000099']));

        $sourceBefore = $this->snapshot($sourcePlatform, $sourceTenant);
        $targetBefore = $this->snapshot($targetPlatform, $targetTenant);
        $failure = null;

        try {
            (new FictitiousDataTransfer)->run($sourcePlatform, $sourceTenant, $targetPlatform, $targetTenant, $execute);
        } catch (RuntimeException $exception) {
            $failure = $exception;
        }

        $this->assertInstanceOf(RuntimeException::class, $failure);
        $this->assertStringContainsString("{$table}.{$column}", $failure->getMessage());
        $this->assertStringNotContainsString('f0000000-0000-4000-8000-000000000099', $failure->getMessage());
        $this->assertSame($targetBefore, $this->snapshot($targetPlatform, $targetTenant));
        $this->assertSame($sourceBefore, $this->snapshot($sourcePlatform, $sourceTenant));
    }

    public static function referenciasOrfas(): array
    {
        return [
            'escopo escolar na simulação' => ['sourcePlatform', 'school_scopes', 'school_id', false],
            'autor da auditoria central na simulação' => ['sourcePlatform', 'platform_audit_logs', 'user_id', false],
            'autor da auditoria municipal na simulação' => ['sourceTenant', 'audit_logs', 'user_id', false],
            'escopo escolar na cópia' => ['sourcePlatform', 'school_scopes', 'school_id', true],
            'autor da auditoria central na cópia' => ['sourcePlatform', 'platform_audit_logs', 'user_id', true],
            'autor da auditoria municipal na cópia' => ['sourceTenant', 'audit_logs', 'user_id', true],
        ];
    }

    public static function obstaculosDaCopia(): array
    {
        return [
            'destino municipal com registro prévio' => [true],
            'chave estrangeira inválida na origem' => [false],
        ];
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
            'destinos duplicados' => ['ASTUTI_MIGRATION_TARGET_TENANT_DB', (string) getenv('ASTUTI_MIGRATION_TARGET_PLATFORM_DB'), 'distintos'],
            'integração desativada' => ['ASTUTI_MIGRATION_INTEGRATION', '0', 'ASTUTI_MIGRATION_INTEGRATION=1'],
            'fora do GitHub Actions' => ['GITHUB_ACTIONS', 'false', 'GitHub Actions'],
        ];
    }

    private function sessionSettings(Connection $connection): array
    {
        return (array) $connection->selectOne('SELECT @@SESSION.transaction_read_only AS read_only, @@SESSION.transaction_isolation AS isolation_level');
    }

    private function withoutTargetOnlyColumns(array $snapshot): array
    {
        foreach (self::TARGET_ONLY_COLUMNS as $table => $columns) {
            $snapshot['platform'][$table] = array_map(
                static fn (array $row): array => array_diff_key($row, array_flip($columns)),
                $snapshot['platform'][$table],
            );
        }

        return $snapshot;
    }

    private function assertTargetOnlyDefaults(array $snapshot): void
    {
        foreach ($snapshot['platform']['users'] as $row) {
            $this->assertSame(0, (int) $row['two_factor_failed_attempts']);
            $this->assertNull($row['two_factor_locked_until']);
        }
    }

    private function snapshot(Connection $platform, Connection $tenant): array
    {
        $snapshot = [];

        foreach (['platform' => $platform, 'tenant' => $tenant] as $database => $connection) {
            foreach (array_keys(self::EXPECTED_COUNTS[$database]) as $table) {
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
}
