<?php

namespace Tests\Feature\Demo;

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
