<?php

namespace Tests\Support\DatabaseMigration;

use App\Models\Platform\Municipality;
use App\Models\Tenant\School;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

final class FictitiousMigrationFixture
{
    private const MUNICIPALITY_ID = '10000000-0000-4000-8000-000000000001';

    private const USER_ID = '20000000-0000-4000-8000-000000000001';

    private const MEMBERSHIP_ID = '30000000-0000-4000-8000-000000000001';

    private const SCHOOL_YEAR_ID = '80000000-0000-4000-8000-000000000001';

    private const ROLE = 'school_secretary';

    private const TIMESTAMP = '2027-02-01 12:00:00';

    private const TIMESTAMPS = ['created_at' => self::TIMESTAMP, 'updated_at' => self::TIMESTAMP];

    public static function seed(Connection $sourcePlatform, Connection $sourceTenant): void
    {
        DisposableMigrationDatabases::assertIntegrationEnvironment();

        foreach ([$sourcePlatform, $sourceTenant] as $source) {
            if ($source->getDriverName() !== 'mariadb'
                || ! in_array($source->getConfig('host'), ['127.0.0.1', '::1'], true)
                || $source->getConfig('url') !== null
                || $source->getConfig('unix_socket') !== ''
                || preg_match('/\A[a-z][a-z0-9_]{0,57}_test\z/', $source->getDatabaseName()) !== 1) {
                throw new InvalidArgumentException('A fixture exige origens MariaDB descartáveis em loopback.');
            }
        }

        if ($sourcePlatform->getDatabaseName() === $sourceTenant->getDatabaseName()) {
            throw new InvalidArgumentException('A fixture exige duas origens distintas.');
        }

        $sourcePlatform->transaction(static fn () => self::seedPlatform($sourcePlatform));
        $sourceTenant->transaction(static fn () => self::seedTenant($sourceTenant));
    }

    private static function seedPlatform(Connection $source): void
    {
        $source->table('municipalities')->insert([
            'id' => self::MUNICIPALITY_ID,
            'slug' => 'monte-sereno',
            'legal_name' => 'Prefeitura Fictícia de Monte Sereno',
            'db_key' => 'monte_sereno',
            'status' => 'active',
            ...self::TIMESTAMPS,
        ]);
        $source->table('users')->insert([
            'id' => self::USER_ID,
            'name' => 'Usuário fictício de Monte Sereno',
            'email' => 'fixture.monte-sereno@astuti.invalid',
            'password' => Hash::make('senha-sintetica-apenas-fixture'),
            'status' => 'active',
            ...self::TIMESTAMPS,
        ]);
        $source->table('municipality_provision_attempts')->insert([
            'id' => 27,
            'municipality_id' => self::MUNICIPALITY_ID,
            'status' => 'completed',
            'started_at' => self::TIMESTAMP,
            'finished_at' => self::TIMESTAMP,
            ...self::TIMESTAMPS,
        ]);
        $source->table('memberships')->insert([
            'id' => self::MEMBERSHIP_ID,
            'user_id' => self::USER_ID,
            'municipality_id' => self::MUNICIPALITY_ID,
            'role' => self::ROLE,
            'status' => 'active',
            ...self::TIMESTAMPS,
        ]);
        $source->table('school_scopes')->insert([
            'membership_id' => self::MEMBERSHIP_ID,
            'school_id' => self::uuid('40000000', 1),
        ]);
        self::audit($source, 'platform_audit_logs', 1, Municipality::class, self::MUNICIPALITY_ID, ['slug' => 'monte-sereno']);
    }

    private static function seedTenant(Connection $source): void
    {
        $source->table('school_years')->insert([
            'id' => self::SCHOOL_YEAR_ID,
            'year' => 2027,
            'start_date' => '2027-02-01',
            'end_date' => '2027-12-17',
            'status' => 'active',
            ...self::TIMESTAMPS,
        ]);

        $studentNumber = 0;
        $students = [];
        $enrollments = [];

        foreach ([1 => 45, 2 => 40, 3 => 30] as $schoolNumber => $count) {
            $schoolId = self::uuid('40000000', $schoolNumber);
            $source->table('schools')->insert([
                'id' => $schoolId,
                'name' => sprintf('Escola fictícia %02d', $schoolNumber),
                'status' => 'active',
                ...self::TIMESTAMPS,
            ]);
            $source->table('school_classes')->insert([
                'id' => self::uuid('50000000', $schoolNumber),
                'school_id' => $schoolId,
                'school_year_id' => self::SCHOOL_YEAR_ID,
                'name' => sprintf('Turma fictícia %02d', $schoolNumber),
                'stage' => 'Ensino Fundamental',
                'modality' => 'Regular',
                'shift' => 'Manhã',
                'capacity' => $count,
                'status' => 'active',
                ...self::TIMESTAMPS,
            ]);

            for ($index = 0; $index < $count; $index++) {
                $studentNumber++;
                $studentId = self::uuid('60000000', $studentNumber);
                $students[] = [
                    'id' => $studentId,
                    'full_name' => sprintf('Estudante fictício %03d', $studentNumber),
                    ...self::TIMESTAMPS,
                ];
                $enrollments[] = [
                    'id' => self::uuid('70000000', $studentNumber),
                    'student_id' => $studentId,
                    'school_id' => $schoolId,
                    'school_year_id' => self::SCHOOL_YEAR_ID,
                    'status' => 'active',
                    'started_at' => '2027-02-01',
                    ...self::TIMESTAMPS,
                ];
            }
        }

        $source->table('students')->insert($students);
        $source->table('student_enrollments')->insert($enrollments);
        self::audit($source, 'audit_logs', 2, School::class, self::uuid('40000000', 1), ['name' => 'Escola fictícia 01']);
    }

    private static function audit(Connection $source, string $table, int $number, string $type, string $id, array $after): void
    {
        $source->table($table)->insert([
            'id' => self::uuid('90000000', $number),
            'occurred_at' => self::TIMESTAMP.'.123456',
            'user_id' => self::USER_ID,
            'role' => self::ROLE,
            'action' => 'fixture.created',
            'auditable_type' => $type,
            'auditable_id' => $id,
            'before' => null,
            'after' => json_encode($after, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'ip' => '127.0.0.1',
            'user_agent' => 'Astuti CI fixture',
            'request_id' => self::uuid('a0000000', $number),
        ]);
    }

    private static function uuid(string $prefix, int $number): string
    {
        return sprintf('%s-0000-4000-8000-%012d', $prefix, $number);
    }
}
