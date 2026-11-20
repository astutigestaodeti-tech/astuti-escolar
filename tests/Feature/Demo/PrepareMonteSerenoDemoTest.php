<?php

namespace Tests\Feature\Demo;

use App\Models\Platform\Membership;
use App\Models\Platform\Municipality;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
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
            School::query()->where('id', self::ESCOLA_B1)->delete();
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
            $this->assertSame(3, School::query()->count());
            $this->assertSame(1, SchoolYear::query()->where('year', 2027)->count());
            $this->assertSame(3, SchoolClass::query()->count());
            $this->assertSame(115, Student::query()->count());
            $this->assertSame(115, StudentEnrollment::query()->count());
            $this->assertSame(45, StudentEnrollment::query()->where('school_id', '44444444-4444-4444-8444-000000000001')->count());
        });

        $user = User::query()->where('email', self::EMAIL)->firstOrFail();
        $this->assertTrue(Hash::check($password, $user->password));

        $this->post('/login', ['email' => self::EMAIL, 'password' => $password])
            ->assertRedirect('/');
        $this->get('/m/monte-sereno/escolas')->assertOk()->assertSee('EMEF Caminhos do Saber');
        $this->get('/m/monte-sereno/escolas/44444444-4444-4444-8444-000000000001/alunos')
            ->assertOk()->assertSee('Estudante fictício 001')->assertDontSee('Estudante fictício 099');
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
            $this->assertSame(3, SchoolClass::query()->count());
            $this->assertSame(115, Student::query()->count());
            $this->assertSame(115, StudentEnrollment::query()->count());
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
        $this->assertSame(0, $this->runDemo());
        $password = $this->senhaEmitida();

        $this->assertTrue(Schema::connection('platform')->hasTable('cache'));
        $this->assertTrue(Schema::connection('platform')->hasTable('cache_locks'));

        config()->set('cache.default', 'database');
        $this->post('/login', ['email' => self::EMAIL, 'password' => $password])
            ->assertRedirect('/');
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
