<?php

namespace Tests\Feature\Demo;

use App\Audit\AuditActor;
use App\Models\Platform\Membership;
use App\Models\Platform\Municipality;
use App\Models\Tenant\AuditLog;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiter as DatabaseRateLimiter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Support\TwoMunicipalityTestCase;

class PrepareMonteSerenoDemoTest extends TwoMunicipalityTestCase
{
    private const EMAIL = 'demo.monte-sereno@astuti.invalid';

    private string $lastOutput = '';

    protected function setUp(): void
    {
        parent::setUp();

        // Reutiliza um banco efêmero exclusivo dos testes, nunca o banco do notebook.
        $settings = config('tenancy.connections.municipio_b');
        app(TenantContext::class)->runFor($this->municipioB, function (): void {
            // Limpeza exclusiva da base descartável de teste; a aplicação não expõe exclusão física.
            DB::connection('tenant')->table('schools')->where('id', self::ESCOLA_B1)->delete();
        });
        Municipality::query()->where('slug', 'monte-sereno')->delete();
        config()->set('tenancy.connections', ['monte_sereno' => $settings]);
    }

    public function test_comando_cria_cenario_sintetico_e_login_acessa_consulta_real(): void
    {
        $this->assertSame(0, $this->runDemo());
        $password = $this->senhaEmitida();

        $municipality = Municipality::query()->where('slug', 'monte-sereno')->firstOrFail();
        $this->assertSame('active', $municipality->status);

        app(TenantContext::class)->runFor($municipality, function (): void {
            // Escolas e turmas do kit ficticio; os alunos entram pelo importador.
            $this->assertSame(['99000101', '99000102', '99000103'], School::query()->orderBy('inep_code')->pluck('inep_code')->all());
            $this->assertSame(1, SchoolYear::query()->where('year', 2027)->count());
            $this->assertSame(10, SchoolClass::query()->count());
            $this->assertSame(['1A', '2A', '3A', '4A', '5A'], SchoolClass::query()->where('school_id', '45000000-0000-4000-8000-000000000002')->orderBy('name')->pluck('name')->all());
            $this->assertSame(0, Student::query()->count());
            $this->assertSame(0, StudentEnrollment::query()->count());
        });

        $user = User::query()->where('email', self::EMAIL)->firstOrFail();
        $this->assertTrue(Hash::check($password, $user->password));

        $this->post('/login', ['email' => self::EMAIL, 'password' => $password])
            ->assertRedirect('/');
        $this->get('/m/monte-sereno/escolas')->assertOk()
            ->assertSee('EMEF Prof. Sebastião Arantes')
            ->assertSee('INEP 99000102')
            ->assertSee('Ano letivo 2027')
            ->assertSee('Gestão municipal')
            ->assertDontSee('Voltar à tela anterior');
    }

    public function test_troca_pelo_kit_arquiva_as_escolas_antigas_com_motivo_e_sem_apagar(): void
    {
        $this->assertSame(0, $this->runDemo());
        $municipality = Municipality::query()->where('slug', 'monte-sereno')->firstOrFail();
        $actor = User::query()->where('email', self::EMAIL)->firstOrFail()->id;

        // Copia antiga: escola inventada com turma, aluno e matricula ativa.
        app(TenantContext::class)->runFor($municipality, function () use ($actor): void {
            app(AuditActor::class)->runAs($actor, function (): void {
                $year = SchoolYear::query()->where('year', 2027)->firstOrFail();
                School::query()->create(['id' => '44444444-4444-4444-8444-000000000001', 'name' => 'EMEF Caminhos do Saber', 'status' => 'active']);
                SchoolClass::query()->create(['id' => '50000000-0000-4000-8000-000000000001', 'school_id' => '44444444-4444-4444-8444-000000000001', 'school_year_id' => $year->id, 'name' => '1º Ano A', 'stage' => 'Ensino Fundamental', 'modality' => 'Regular', 'shift' => 'Manhã', 'status' => 'active']);
                Student::query()->create(['id' => '60000000-0000-4000-8000-000000000001', 'full_name' => 'Estudante fictício 001']);
                StudentEnrollment::query()->create(['id' => '70000000-0000-4000-8000-000000000001', 'student_id' => '60000000-0000-4000-8000-000000000001', 'school_id' => '44444444-4444-4444-8444-000000000001', 'school_year_id' => $year->id, 'status' => 'active']);
            });
        });

        // Sem a opcao explicita, nada muda.
        $this->assertSame(1, $this->runDemo());
        $this->assertStringContainsString('escolas antigas', $this->lastOutput);

        $this->assertSame(0, $this->runDemo(['--trocar-pelo-kit' => true]));

        app(TenantContext::class)->runFor($municipality, function () use ($actor): void {
            $this->assertSame(3, School::query()->count());
            $this->assertFalse(School::query()->where('id', '44444444-4444-4444-8444-000000000001')->exists());

            foreach ([
                School::query()->withTrashed()->findOrFail('44444444-4444-4444-8444-000000000001'),
                SchoolClass::query()->withTrashed()->findOrFail('50000000-0000-4000-8000-000000000001'),
                Student::query()->withTrashed()->findOrFail('60000000-0000-4000-8000-000000000001'),
                StudentEnrollment::query()->withTrashed()->findOrFail('70000000-0000-4000-8000-000000000001'),
            ] as $archived) {
                $this->assertNotNull($archived->deleted_at, $archived::class);
                $this->assertSame($actor, $archived->deleted_by);
                $this->assertStringContainsString('kit ficticio Monte Sereno', $archived->deletion_reason);
            }

            $this->assertSame(4, AuditLog::query()->where('action', 'deleted')->count());
        });

        // Repetir a troca nao duplica nem falha.
        $this->assertSame(0, $this->runDemo(['--trocar-pelo-kit' => true]));
        app(TenantContext::class)->runFor($municipality, fn () => $this->assertSame(10, SchoolClass::query()->count()));
    }

    public function test_troca_pelo_kit_exige_demonstracao_existente(): void
    {
        $this->assertSame(1, $this->runDemo(['--trocar-pelo-kit' => true]));
        $this->assertStringContainsString('demonstracao ja existente', $this->lastOutput);
        $this->assertNull(User::query()->where('email', self::EMAIL)->first());
    }

    public function test_segunda_execucao_nao_duplica_nem_muda_senha(): void
    {
        $this->assertSame(0, $this->runDemo());
        $passwordHash = User::query()->where('email', self::EMAIL)->firstOrFail()->password;

        $this->assertSame(0, $this->runDemo());
        $this->assertStringContainsString('Senha preservada', $this->lastOutput);
        $this->assertSame($passwordHash, User::query()->where('email', self::EMAIL)->firstOrFail()->password);

        $municipality = Municipality::query()->where('slug', 'monte-sereno')->firstOrFail();
        app(TenantContext::class)->runFor($municipality, function (): void {
            $this->assertSame(3, School::query()->count());
            $this->assertSame(10, SchoolClass::query()->count());
            $this->assertSame(0, Student::query()->count());
        });
    }

    public function test_senha_so_pode_ser_renovada_quando_opcao_e_explicita(): void
    {
        $this->assertSame(0, $this->runDemo());
        $oldHash = User::query()->where('email', self::EMAIL)->firstOrFail()->password;

        $this->assertSame(0, $this->runDemo(['--renovar-senha' => true]));
        $password = $this->senhaEmitida();

        $user = User::query()->where('email', self::EMAIL)->firstOrFail();
        $this->assertNotSame($oldHash, $user->password);
        $this->assertTrue(Hash::check($password, $user->password));
    }

    public function test_producao_nao_executa_nenhuma_migracao_ou_gravacao(): void
    {
        $before = Municipality::query()->count();
        $this->app['env'] = 'production';

        $this->assertSame(1, $this->runDemo());
        $this->assertSame($before, Municipality::query()->count());
        $this->assertStringContainsString('somente desenvolvimento', $this->lastOutput);
    }

    public function test_conexao_remota_ou_banco_compartilhado_sao_recusados(): void
    {
        $valid = config('tenancy.connections.monte_sereno');
        $before = Municipality::query()->count();

        foreach ([
            ['host' => '203.0.113.7'],
            ['database' => config('database.connections.platform.database')],
        ] as $unsafe) {
            config()->set('tenancy.connections.monte_sereno', array_replace($valid, $unsafe));
            $this->assertSame(1, $this->runDemo());
            $this->assertSame($before, Municipality::query()->count());
        }
    }

    public function test_banco_municipal_inexistente_nao_e_criado_implicitamente(): void
    {
        $valid = config('tenancy.connections.monte_sereno');
        config()->set('tenancy.connections.monte_sereno', array_replace($valid, [
            'database' => 'astuti_ausente_para_demo_test',
        ]));

        $this->assertSame(1, $this->runDemo());
        $this->assertNull(Municipality::query()->where('slug', 'monte-sereno')->first());
    }

    public function test_municipio_desativado_nao_e_reativado_pela_demonstracao(): void
    {
        $this->assertSame(0, $this->runDemo());
        $municipality = Municipality::query()->where('slug', 'monte-sereno')->firstOrFail();
        $municipality->update(['status' => 'inactive']);

        $this->assertSame(1, $this->runDemo());
        $this->assertSame('inactive', $municipality->fresh()->status);
    }

    public function test_conta_reservada_com_outra_rede_nao_recebe_privilegios_municipais(): void
    {
        $user = $this->criarUsuario(self::EMAIL);
        $membership = $this->vincular($user, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);
        $originalPassword = $user->password;

        $this->assertSame(1, $this->runDemo(['--renovar-senha' => true]));
        $this->assertNull(Municipality::query()->where('slug', 'monte-sereno')->first());
        $this->assertSame($originalPassword, $user->fresh()->password);
        $this->assertSame([$membership->id], Membership::query()->where('user_id', $user->id)->pluck('id')->all());
    }

    public function test_vinculo_do_gestor_desativado_impede_reuso_da_conta(): void
    {
        $this->assertSame(0, $this->runDemo());
        $municipality = Municipality::query()->where('slug', 'monte-sereno')->firstOrFail();
        $user = User::query()->where('email', self::EMAIL)->firstOrFail();
        $originalPassword = $user->password;
        $membership = Membership::query()
            ->where('municipality_id', $municipality->id)->where('user_id', $user->id)->firstOrFail();
        $membership->update(['status' => 'inactive']);

        $this->assertSame(1, $this->runDemo(['--renovar-senha' => true]));
        $this->assertSame('inactive', $membership->fresh()->status);
        $this->assertSame($originalPassword, $user->fresh()->password);
    }

    public function test_falha_ao_criar_vinculo_desfaz_conta_e_senha(): void
    {
        Membership::creating(static function (): never {
            throw new RuntimeException('Falha de vinculo simulada');
        });

        try {
            $this->assertSame(1, $this->runDemo());
        } finally {
            Membership::flushEventListeners();
        }

        $this->assertNull(User::query()->where('email', self::EMAIL)->first());
        $this->assertStringNotContainsString('Senha de teste:', $this->lastOutput);
    }

    public function test_login_com_cache_de_banco_funciona_apos_preparo_local(): void
    {
        // Simula a instalacao nova: nenhuma tabela de cache preexistente.
        Schema::connection('platform')->dropIfExists('cache_locks');
        Schema::connection('platform')->dropIfExists('cache');
        DB::connection('platform')->table('migrations')
            ->where('migration', '2026_11_20_000007_create_platform_cache_tables')->delete();

        $this->assertSame(0, $this->runDemo());
        $password = $this->senhaEmitida();

        $this->assertTrue(Schema::connection('platform')->hasTable('cache'));
        $this->assertTrue(Schema::connection('platform')->hasTable('cache_locks'));

        config()->set('cache.default', 'database');
        $limiter = new DatabaseRateLimiter(Cache::store('database'));
        $limiter->for('astuti-login', RateLimiter::limiter('astuti-login'));
        $this->app->instance('rate.limiter', $limiter);
        RateLimiter::swap($limiter);
        DB::connection('platform')->table('cache')->delete();

        $this->post('/login', ['email' => self::EMAIL, 'password' => $password])
            ->assertRedirect('/');
        $this->assertGreaterThan(0, DB::connection('platform')->table('cache')->count());
    }

    public function test_preparo_preserva_cache_legado_mesmo_se_faltar_tabela_de_travas(): void
    {
        $this->assertTrue(Schema::connection('platform')->hasTable('cache'));
        DB::connection('platform')->table('cache')->updateOrInsert(
            ['key' => 'cache-legado-preservar'],
            ['value' => 'informacao-existente', 'expiration' => time() + 3600],
        );
        Schema::connection('platform')->dropIfExists('cache_locks');
        DB::connection('platform')->table('migrations')
            ->where('migration', '2026_11_20_000007_create_platform_cache_tables')->delete();

        $this->assertSame(0, $this->runDemo());
        $this->assertSame('informacao-existente', DB::connection('platform')->table('cache')
            ->where('key', 'cache-legado-preservar')->value('value'));
        $this->assertTrue(Schema::connection('platform')->hasTable('cache_locks'));

        // Rollback deste complemento nunca pode apagar cache preexistente.
        $migration = require database_path('migrations/platform/2026_11_20_000007_create_platform_cache_tables.php');
        $migration->down();
        $this->assertTrue(Schema::connection('platform')->hasTable('cache'));
        $this->assertTrue(Schema::connection('platform')->hasTable('cache_locks'));
    }

    private function senhaEmitida(): string
    {
        $this->assertSame(1, preg_match('/Senha de teste: ([^\s]+)/', $this->lastOutput, $matches));
        $this->assertGreaterThanOrEqual(20, strlen($matches[1]));

        return $matches[1];
    }

    private function runDemo(array $options = []): int
    {
        $output = new BufferedOutput;
        $result = Artisan::call('astuti:demo-preparar', $options, $output);
        $this->lastOutput = $output->fetch();

        return $result;
    }
}
