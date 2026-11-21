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
    protected $signature = 'astuti:demo-preparar
        {--renovar-senha : Gera outra senha para a conta ficticia existente}
        {--trocar-pelo-kit : Arquiva as escolas antigas da demonstracao e cria as do kit Monte Sereno (somente no ensaio isolado 55436)}';

    protected $description = 'Prepara um municipio ficticio de testes, exclusivamente em bancos locais de desenvolvimento.';

    private const EMAIL = 'demo.monte-sereno@astuti.invalid';

    private const SCHOOL_YEAR_ID = '88888888-8888-4888-8888-888888888888';

    /** Escolas inventadas da primeira demonstracao; so existem em copias antigas. */
    private const LEGACY_SCHOOL_IDS = [
        '44444444-4444-4444-8444-000000000001',
        '44444444-4444-4444-8444-000000000002',
        '44444444-4444-4444-8444-000000000003',
    ];

    private const LEGACY_REASON = 'Troca da demonstracao pelas escolas do kit ficticio Monte Sereno (pedido do Jefferson em 21/11/2026).';

    /** Escolas do kit ficticio Monte Sereno v2 (01_gabarito_dados_limpos/escolas.csv). */
    public const KIT_SCHOOLS = [
        'E01' => ['id' => '45000000-0000-4000-8000-000000000001', 'name' => 'EMEI Profª Aurora Benedita Lemes', 'inep' => '99000101'],
        'E02' => ['id' => '45000000-0000-4000-8000-000000000002', 'name' => 'EMEF Prof. Sebastião Arantes', 'inep' => '99000102'],
        'E03' => ['id' => '45000000-0000-4000-8000-000000000003', 'name' => 'Escola Municipal Rural Bairro das Palmeiras', 'inep' => '99000103'],
    ];

    /** Turmas do kit (turmas.csv): o nome e o codigo que as escolas usam nas planilhas. */
    public const KIT_CLASSES = [
        ['id' => '51000000-0000-4000-8000-000000000001', 'school' => 'E01', 'name' => 'MII-A', 'stage' => 'Creche - Maternal II', 'shift' => 'Integral', 'capacity' => 16],
        ['id' => '51000000-0000-4000-8000-000000000002', 'school' => 'E01', 'name' => 'PI-A', 'stage' => 'Pré-escola - Pré I', 'shift' => 'Manhã', 'capacity' => 20],
        ['id' => '51000000-0000-4000-8000-000000000003', 'school' => 'E01', 'name' => 'PII-A', 'stage' => 'Pré-escola - Pré II', 'shift' => 'Tarde', 'capacity' => 20],
        ['id' => '51000000-0000-4000-8000-000000000004', 'school' => 'E02', 'name' => '1A', 'stage' => 'Fundamental Anos Iniciais - 1º ano', 'shift' => 'Manhã', 'capacity' => 25],
        ['id' => '51000000-0000-4000-8000-000000000005', 'school' => 'E02', 'name' => '2A', 'stage' => 'Fundamental Anos Iniciais - 2º ano', 'shift' => 'Manhã', 'capacity' => 25],
        ['id' => '51000000-0000-4000-8000-000000000006', 'school' => 'E02', 'name' => '3A', 'stage' => 'Fundamental Anos Iniciais - 3º ano', 'shift' => 'Manhã', 'capacity' => 25],
        ['id' => '51000000-0000-4000-8000-000000000007', 'school' => 'E02', 'name' => '4A', 'stage' => 'Fundamental Anos Iniciais - 4º ano', 'shift' => 'Tarde', 'capacity' => 25],
        ['id' => '51000000-0000-4000-8000-000000000008', 'school' => 'E02', 'name' => '5A', 'stage' => 'Fundamental Anos Iniciais - 5º ano', 'shift' => 'Tarde', 'capacity' => 25],
        ['id' => '51000000-0000-4000-8000-000000000009', 'school' => 'E03', 'name' => 'MULTI-1', 'stage' => 'Fundamental Anos Iniciais - 1º, 2º e 3º anos (multisseriada)', 'shift' => 'Manhã', 'capacity' => 15],
        ['id' => '51000000-0000-4000-8000-000000000010', 'school' => 'E03', 'name' => 'MULTI-2', 'stage' => 'Fundamental Anos Iniciais - 4º e 5º anos (multisseriada)', 'shift' => 'Tarde', 'capacity' => 15],
    ];

    public function handle(TenantConnectionManager $connections, TenantContext $context): int
    {
        if (! $this->safeDevelopmentDatabases()) {
            $this->error('A demonstracao e permitida somente desenvolvimento em bancos locais autorizados. Nenhuma alteracao foi feita.');

            return self::FAILURE;
        }

        $swap = (bool) $this->option('trocar-pelo-kit');

        // Verificar as DUAS conexoes antes de executar qualquer migracao.
        try {
            DB::connection('platform')->getPdo();
            $connections->activate(new Municipality(['db_key' => 'monte_sereno']));
            if ($swap && ! $this->isolatedRehearsal()) {
                $this->error('A troca pelo kit so e permitida no ensaio isolado da porta 55436. Nenhuma alteracao foi feita.');

                return self::FAILURE;
            }
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

            if ($swap && $user === null) {
                $this->error('A troca pelo kit so vale para uma demonstracao ja existente. Nenhuma alteracao foi feita.');

                return self::FAILURE;
            }

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
            $legacy = $context->runFor($municipality, fn (): bool => School::query()->whereIn('id', self::LEGACY_SCHOOL_IDS)->exists());
            if ($legacy && ! $swap) {
                $this->error('Esta copia ainda tem as escolas antigas da demonstracao. Use a troca pelo kit no ensaio isolado. Nenhuma alteracao foi feita.');

                return self::FAILURE;
            }

            $context->runFor($municipality, function () use ($demoActorId, $swap): void {
                app(AuditActor::class)->runAs($demoActorId, function () use ($demoActorId, $swap): void {
                    DB::connection('tenant')->transaction(function () use ($demoActorId, $swap): void {
                        if ($swap) {
                            $this->archiveLegacySchools($demoActorId);
                        }
                        $this->createSyntheticSchoolRecords();
                    });
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

            $this->info('Monte Sereno pronto: 3 escolas e 10 turmas do kit ficticio, ano letivo 2027. Os alunos entram pelo importador.');
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

    /**
     * Ensaio isolado: as duas conexoes efetivas na porta 55436, nunca no
     * PostgreSQL principal. Nos testes de integracao, as bases *_test.
     */
    private function isolatedRehearsal(): bool
    {
        if (app()->environment('testing')) {
            return getenv('TENANCY_INTEGRATION') === '1';
        }

        foreach (['platform', 'tenant'] as $connection) {
            $port = (int) DB::connection($connection)->selectOne('SELECT inet_server_port() AS porta')->porta;
            if ($port !== 55436) {
                return false;
            }
        }

        return true;
    }

    /**
     * Exclusao logica, com motivo e autoria, de tudo o que pertence as escolas
     * inventadas: matriculas, alunos sem outra matricula, turmas e escolas.
     * Nada e apagado fisicamente; a auditoria registra cada passo.
     */
    private function archiveLegacySchools(string $actorId): void
    {
        $enrollments = StudentEnrollment::query()->whereIn('school_id', self::LEGACY_SCHOOL_IDS)->orderBy('id')->get();
        foreach ($enrollments as $enrollment) {
            $enrollment->deleteWithReason(self::LEGACY_REASON, $actorId);
        }

        $students = Student::query()
            ->whereIn('id', $enrollments->pluck('student_id')->unique()->all())
            ->whereDoesntHave('enrollments')
            ->orderBy('id')
            ->get();
        foreach ($students as $student) {
            $student->deleteWithReason(self::LEGACY_REASON, $actorId);
        }

        foreach (SchoolClass::query()->whereIn('school_id', self::LEGACY_SCHOOL_IDS)->orderBy('id')->get() as $class) {
            $class->deleteWithReason(self::LEGACY_REASON, $actorId);
        }

        foreach (School::query()->whereIn('id', self::LEGACY_SCHOOL_IDS)->orderBy('id')->get() as $school) {
            $school->deleteWithReason(self::LEGACY_REASON, $actorId);
        }
    }

    private function createSyntheticSchoolRecords(): void
    {
        $year = SchoolYear::query()->firstOrCreate(['year' => 2027], [
            'id' => self::SCHOOL_YEAR_ID,
            'start_date' => '2027-02-08',
            'end_date' => '2027-12-17',
            'status' => 'active',
        ]);

        foreach (self::KIT_SCHOOLS as $schoolData) {
            School::query()->firstOrCreate(['id' => $schoolData['id']], [
                'name' => $schoolData['name'],
                'inep_code' => $schoolData['inep'],
                'status' => 'active',
            ]);
        }

        foreach (self::KIT_CLASSES as $classData) {
            SchoolClass::query()->firstOrCreate(['id' => $classData['id']], [
                'school_id' => self::KIT_SCHOOLS[$classData['school']]['id'],
                'school_year_id' => $year->id,
                'name' => $classData['name'],
                'stage' => $classData['stage'],
                'modality' => 'Regular',
                'shift' => $classData['shift'],
                'capacity' => $classData['capacity'],
                'status' => 'active',
            ]);
        }
    }
}
