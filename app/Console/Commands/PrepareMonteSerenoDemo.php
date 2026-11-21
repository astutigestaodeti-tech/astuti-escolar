<?php

namespace App\Console\Commands;

use App\Audit\AuditActor;
use App\Models\Platform\Membership;
use App\Models\Platform\Municipality;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\User;
use App\Tenancy\TenantConnectionManager;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

class PrepareMonteSerenoDemo extends Command
{
    protected $signature = 'astuti:demo-preparar {--renovar-senha : Gera outra senha para a conta ficticia existente}';

    protected $description = 'Prepara um municipio ficticio de testes, exclusivamente em bancos locais de desenvolvimento.';

    private const EMAIL = 'demo.monte-sereno@astuti.invalid';

    private const SCHOOL_YEAR_ID = '88888888-8888-4888-8888-888888888888';

    private const SCHOOLS = [
        ['id' => '44444444-4444-4444-8444-000000000001', 'name' => 'EMEF Caminhos do Saber', 'count' => 45, 'class' => '1º Ano A', 'stage' => 'Ensino Fundamental', 'shift' => 'Manhã'],
        ['id' => '44444444-4444-4444-8444-000000000002', 'name' => 'EMEI Jardim das Flores', 'count' => 40, 'class' => 'Infantil IV A', 'stage' => 'Educação Infantil', 'shift' => 'Tarde'],
        ['id' => '44444444-4444-4444-8444-000000000003', 'name' => 'EMEF Rio Claro', 'count' => 30, 'class' => '2º Ano A', 'stage' => 'Ensino Fundamental', 'shift' => 'Integral'],
    ];

    public function handle(TenantConnectionManager $connections, TenantContext $context): int
    {
        if (! $this->safeDevelopmentDatabases()) {
            $this->error('A demonstracao e permitida somente desenvolvimento em bancos locais autorizados. Nenhuma alteracao foi feita.');

            return self::FAILURE;
        }

        // Verificar as DUAS conexoes antes de executar qualquer migracao.
        try {
            DB::connection('platform')->getPdo();
            $connections->activate(new Municipality(['db_key' => 'monte_sereno']));
        } catch (Throwable) {
            $this->error('Nao foi possivel conectar as duas bases locais ja existentes. Nenhuma migracao foi iniciada.');

            return self::FAILURE;
        } finally {
            $connections->reset();
        }

        try {
            $exitCode = Artisan::call('migrate', [
                '--database' => 'platform',
                '--path' => database_path('migrations/platform'),
                '--realpath' => true,
                '--force' => true,
            ]);

            if ($exitCode !== self::SUCCESS) {
                $this->error('Nao foi possivel atualizar o esquema central de desenvolvimento.');

                return self::FAILURE;
            }

            $municipality = Municipality::query()->where('slug', 'monte-sereno')->first();

            if (($municipality !== null && $municipality->db_key !== 'monte_sereno')
                || Municipality::query()->where('db_key', 'monte_sereno')->where('slug', '!=', 'monte-sereno')->exists()) {
                $this->error('Ja existe um cadastro municipal conflitante. Nenhum registro sera sobrescrito.');

                return self::FAILURE;
            }

            if ($municipality !== null && ! in_array($municipality->status, ['active', 'failed', 'provisioning'], true)) {
                $this->error('Monte Sereno esta desativado ou em estado administrativo nao permitido. Nenhum acesso sera reaberto.');

                return self::FAILURE;
            }

            $user = User::query()->where('email', self::EMAIL)->first();

            if ($user !== null && $user->status !== 'active') {
                $this->error('A conta ficticia esta inativa. Nenhuma permissao sera alterada automaticamente.');

                return self::FAILURE;
            }

            if ($user !== null) {
                // Um e-mail reservado nao e prova de que uma conta preexistente
                // pertence a demonstracao. Nunca elevar permissao escolar.
                $memberships = Membership::query()->where('user_id', $user->id)->get();
                $only = $memberships->first();

                if ($municipality === null || $memberships->count() !== 1
                    || $only->municipality_id !== $municipality->id
                    || $only->role !== 'municipal_manager' || $only->status !== 'active') {
                    $this->error('A conta reservada ja possui vinculos diferentes ou inativos. Nenhum acesso sera ampliado.');

                    return self::FAILURE;
                }
            }

            $municipality ??= Municipality::query()->create([
                'id' => (string) Str::uuid(),
                'slug' => 'monte-sereno',
                'legal_name' => 'Prefeitura Municipal de Monte Sereno',
                'db_key' => 'monte_sereno',
                'status' => 'provisioning',
            ]);

            if (Artisan::call('astuti:municipio-provisionar', ['slug' => 'monte-sereno']) !== self::SUCCESS) {
                $this->error('Nao foi possivel preparar o banco municipal ficticio.');

                return self::FAILURE;
            }

            $municipality->refresh();
            // Reservamos a identidade antes de semear, mas criamos a conta só
            // depois de a transação municipal concluir. Assim uma falha não
            // deixa usuário sem vínculo nem auditoria com autoria desconhecida.
            $demoActorId = $user?->id ?? (string) Str::uuid();
            $context->runFor($municipality, function () use ($demoActorId): void {
                app(AuditActor::class)->runAs($demoActorId, function (): void {
                    DB::connection('tenant')->transaction(fn () => $this->createSyntheticSchoolRecords());
                });
            });

            $password = null;

            if ($user === null || $this->option('renovar-senha')) {
                $password = Str::random(24);
            }

            DB::connection('platform')->transaction(function () use ($municipality, $user, $password, $demoActorId): void {
                if ($user === null) {
                    $user = User::query()->create([
                        'id' => $demoActorId,
                        'name' => 'Secretaria Escolar Fictícia',
                        'email' => self::EMAIL,
                        'status' => 'active',
                        'password' => Hash::make($password),
                    ]);
                } elseif ($password !== null) {
                    $user->password = Hash::make($password);
                    $user->save();
                }

                Membership::query()->firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'municipality_id' => $municipality->id,
                        'role' => 'municipal_manager',
                    ],
                    ['id' => (string) Str::uuid(), 'status' => 'active'],
                );
            });

            $this->info('Monte Sereno pronto: 3 escolas, 115 alunos ficticios e ano letivo 2027.');
            $this->line('Acesse: http://127.0.0.1:8001/login');
            $this->line('Usuario de teste: '.self::EMAIL);

            if ($password !== null) {
                $this->line('Senha de teste: '.$password);
                $this->warn('Guarde a senha no seu notebook; nao compartilhe prints com ela.');
            } else {
                $this->info('Senha preservada. Use --renovar-senha somente se precisar de uma nova.');
            }

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('A preparacao ficticia nao foi concluida. Os registros existentes nao foram apagados.');

            return self::FAILURE;
        } finally {
            $connections->reset();
        }
    }

    private function safeDevelopmentDatabases(): bool
    {
        $local = app()->environment('local');
        $testing = app()->environment('testing') && getenv('TENANCY_INTEGRATION') === '1';

        if (! $local && ! $testing) {
            return false;
        }

        $platform = config('database.connections.platform', []);
        $tenant = config('tenancy.connections.monte_sereno');

        if (! is_array($platform) || ! is_array($tenant)
            || ($platform['driver'] ?? null) !== 'pgsql'
            || ($tenant['driver'] ?? null) !== 'pgsql'
            || ! empty($platform['url']) || ! empty($tenant['url'])
            || ! in_array(strtolower((string) ($platform['host'] ?? '')), ['localhost', '127.0.0.1', '::1'], true)
            || ! in_array(strtolower((string) ($tenant['host'] ?? '')), ['localhost', '127.0.0.1', '::1'], true)) {
            return false;
        }

        $platformDb = (string) ($platform['database'] ?? '');
        $tenantDb = (string) ($tenant['database'] ?? '');

        if ($platformDb === $tenantDb) {
            return false;
        }

        if ($local) {
            return $platformDb === 'astuti_platform_dev' && $tenantDb === 'astuti_monte_sereno_dev';
        }

        return preg_match('/\A[a-z0-9_]+_test\z/D', $platformDb) === 1
            && preg_match('/\A[a-z0-9_]+_test\z/D', $tenantDb) === 1;
    }

    private function createSyntheticSchoolRecords(): void
    {
        $year = SchoolYear::query()->firstOrCreate(['year' => 2027], [
            'id' => self::SCHOOL_YEAR_ID,
            'start_date' => '2027-02-01',
            'end_date' => '2027-12-17',
            'status' => 'active',
        ]);

        $studentNumber = 0;

        foreach (self::SCHOOLS as $schoolNumber => $schoolData) {
            $school = School::query()->firstOrCreate(['id' => $schoolData['id']], [
                'name' => $schoolData['name'],
                'status' => 'active',
            ]);

            SchoolClass::query()->firstOrCreate([
                'id' => sprintf('50000000-0000-4000-8000-%012d', $schoolNumber + 1),
            ], [
                'school_id' => $school->id,
                'school_year_id' => $year->id,
                'name' => $schoolData['class'],
                'stage' => $schoolData['stage'],
                'modality' => 'Regular',
                'shift' => $schoolData['shift'],
                'capacity' => $schoolData['count'],
                'status' => 'active',
            ]);

            for ($index = 0; $index < $schoolData['count']; $index++) {
                $studentNumber++;
                $student = Student::query()->firstOrCreate([
                    'id' => sprintf('60000000-0000-4000-8000-%012d', $studentNumber),
                ], [
                    'full_name' => sprintf('Estudante fictício %03d', $studentNumber),
                    'birth_date' => sprintf('%d-%02d-%02d', 2016 + ($studentNumber % 4), 1 + ($studentNumber % 12), 1 + ($studentNumber % 28)),
                ]);

                StudentEnrollment::query()->firstOrCreate([
                    'id' => sprintf('70000000-0000-4000-8000-%012d', $studentNumber),
                ], [
                    'student_id' => $student->id,
                    'school_id' => $school->id,
                    'school_year_id' => $year->id,
                    'status' => 'active',
                    'started_at' => '2027-02-01',
                ]);
            }
        }
    }
}
