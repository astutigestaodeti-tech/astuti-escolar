<?php

namespace Tests\Support;

use App\Models\Platform\Membership;
use App\Models\Platform\Municipality;
use App\Models\Tenant\School;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

abstract class TwoMunicipalityTestCase extends TestCase
{
    protected Municipality $municipioA;

    protected Municipality $municipioB;

    protected const ESCOLA_A1 = '11111111-1111-4111-8111-111111111111';

    protected const ESCOLA_A2 = '22222222-2222-4222-8222-222222222222';

    protected const ESCOLA_B1 = '33333333-3333-4333-8333-333333333333';

    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('TENANCY_INTEGRATION') !== '1') {
            $this->markTestSkipped('Exige tres bases MariaDB descartaveis de teste.');
        }

        $platformName = (string) getenv('PLATFORM_DB_DATABASE');
        $dbA = (string) getenv('TENANCY_TEST_DB_A');
        $dbB = (string) getenv('TENANCY_TEST_DB_B');

        foreach ([$platformName, $dbA, $dbB] as $name) {
            if (! preg_match('/\A[a-z0-9_]+_test\z/D', $name)) {
                throw new RuntimeException('Banco de testes deve terminar em _test.');
            }
        }

        if (count(array_unique([$platformName, $dbA, $dbB])) !== 3) {
            throw new RuntimeException('Bancos de testes devem ser distintos.');
        }

        $base = config('database.connections.mariadb');
        $settings = [
            'host' => (string) getenv('TENANCY_TEST_DB_HOST'),
            'port' => (string) getenv('TENANCY_TEST_DB_PORT'),
            'username' => (string) getenv('TENANCY_TEST_DB_USER'),
            'password' => (string) getenv('TENANCY_TEST_DB_PASSWORD'),
            'unix_socket' => '',
            'url' => null,
        ];

        config()->set('tenancy.connections', [
            'municipio_a' => array_replace($base, $settings, ['database' => $dbA]),
            'municipio_b' => array_replace($base, $settings, ['database' => $dbB]),
        ]);

        Artisan::call('migrate', [
            '--database' => 'platform',
            '--path' => database_path('migrations/platform'),
            '--realpath' => true,
            '--force' => true,
        ]);

        // Os bancos exclusivos de teste sobrevivem entre execucoes consecutivas
        // no CI; cada cenario precisa comecar sem sessoes ou tentativas antigas.
        foreach (['platform_audit_logs', 'sessions', 'school_scopes', 'memberships', 'municipality_provision_attempts', 'users'] as $table) {
            if (Schema::connection('platform')->hasTable($table)) {
                if ($table === 'platform_audit_logs') {
                    // Só os bancos efêmeros de teste podem usar TRUNCATE;
                    // os gatilhos bloqueiam DELETE, como devem em produção.
                    DB::connection('platform')->table($table)->truncate();
                } else {
                    DB::connection('platform')->table($table)->delete();
                }
            }
        }

        $this->municipioA = Municipality::query()->updateOrCreate(
            ['slug' => 'municipio-a'],
            ['id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'legal_name' => 'Municipio A', 'db_key' => 'municipio_a', 'status' => 'active'],
        );

        $this->municipioB = Municipality::query()->updateOrCreate(
            ['slug' => 'municipio-b'],
            ['id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'legal_name' => 'Municipio B', 'db_key' => 'municipio_b', 'status' => 'active'],
        );

        $context = app(TenantContext::class);

        foreach ([
            [$this->municipioA, [self::ESCOLA_A1 => 'Unidade A', self::ESCOLA_A2 => 'Outra Unidade A']],
            [$this->municipioB, [self::ESCOLA_B1 => 'Unidade B']],
        ] as [$municipio, $escolas]) {
            $context->enter($municipio);

            try {
                Artisan::call('migrate', [
                    '--database' => 'tenant',
                    '--path' => database_path('migrations/tenant'),
                    '--realpath' => true,
                    '--force' => true,
                ]);

                // Apagar primeiro os vinculos de teste, antes das FKs de
                // escolas e anos, sem desativar a integridade referencial.
                foreach (['student_enrollments', 'students', 'school_classes', 'school_years', 'schools', 'audit_logs'] as $table) {
                    if (Schema::connection('tenant')->hasTable($table)) {
                        if ($table === 'audit_logs') {
                            DB::connection('tenant')->table($table)->truncate();
                        } else {
                            DB::connection('tenant')->table($table)->delete();
                        }
                    }
                }

                foreach ($escolas as $id => $name) {
                    School::query()->create(['id' => $id, 'name' => $name, 'status' => 'active']);
                }
            } finally {
                $context->leave();
            }
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->municipioA)) {
            app(TenantContext::class)->leave();
        }

        parent::tearDown();
    }

    protected function criarUsuario(string $email, string $status = 'active'): User
    {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Pessoa sintética',
            'email' => $email,
            'password' => Hash::make('SenhaForteDeTeste123!'),
            'status' => $status,
        ]);
    }

    protected function vincular(User $user, Municipality $municipio, string $role, array $schoolIds = [], string $status = 'active'): Membership
    {
        $membership = Membership::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'municipality_id' => $municipio->id,
            'role' => $role,
            'status' => $status,
        ]);

        foreach ($schoolIds as $schoolId) {
            DB::connection('platform')->table('school_scopes')->insert([
                'membership_id' => $membership->id,
                'school_id' => $schoolId,
            ]);
        }

        return $membership;
    }
}
