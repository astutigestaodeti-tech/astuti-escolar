<?php

namespace Tests\Feature\Census;

use App\Models\Tenant\AuditLog;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\TransportRouteCensus;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class TransportCensusTest extends TwoMunicipalityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2027-05-20 10:00:00'));
        $this->tenant(function (): void {
            $year = SchoolYear::query()->create([
                'id' => (string) Str::uuid(), 'year' => 2027,
                'start_date' => '2027-02-01', 'end_date' => '2027-12-15', 'status' => 'active',
            ]);
            foreach (['Rota 1', 'Rota 1', 'Rota 2', null] as $i => $rota) {
                $student = Student::query()->create(['id' => (string) Str::uuid(), 'full_name' => 'Aluno Fictício Transporte '.$i, 'birth_date' => '2017-01-01']);
                StudentEnrollment::query()->create([
                    'id' => (string) Str::uuid(), 'student_id' => $student->id, 'school_id' => self::ESCOLA_A1,
                    'school_year_id' => $year->id, 'status' => 'active', 'started_at' => '2027-02-01',
                    'transport_status' => 'active', 'transport_route' => $rota,
                ]);
            }
        });
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function tenant(callable $callback): mixed
    {
        return app(TenantContext::class)->runFor($this->municipioA, $callback);
    }

    private function login(string $role, array $schools = []): void
    {
        $user = $this->criarUsuario((string) Str::uuid().'@example.invalid');
        $this->vincular($user, $this->municipioA, $role, $schools);
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', [
            'code' => app(Totp::class)->at($secret, now()->getTimestamp()),
        ])->assertRedirect();
    }

    private function url(): string
    {
        return '/m/municipio-a/escolas/'.self::ESCOLA_A1.'/censo/transporte';
    }

    public function test_secretaria_informa_poder_publico_e_veiculos_por_rota_sem_ver_alunos(): void
    {
        $this->login('school_secretary', [self::ESCOLA_A1]);

        $html = $this->get($this->url())->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Rota 1')->assertSee('2 alunos')->assertSee('Rota 2')
            ->assertSee('1 aluno usa transporte sem rota informada')
            ->assertDontSee('Aluno Fictício Transporte')
            ->getContent();
        if ($dump = getenv('ASTUTI_DUMP_TELAS')) {
            $html = str_replace(rtrim((string) config('app.url'), '/').'/', 'file://'.public_path().'/', $html);
            file_put_contents(rtrim($dump, '/').'/censo_transporte_rotas.html', preg_replace('/\\.(css|js)\\?v=\\d+/', '.$1', $html));
        }

        $this->put($this->url(), ['rotas' => [
            ['nome' => 'Rota 1', 'poder' => '2', 'veiculos' => ['onibus', 'van']],
            ['nome' => 'Rota 2', 'poder' => '1', 'veiculos' => ['microonibus']],
            ['nome' => 'Rota inventada', 'poder' => '2', 'veiculos' => ['onibus']],
        ]])->assertRedirect($this->url());

        $this->tenant(function (): void {
            $rotas = TransportRouteCensus::query()->where('school_id', self::ESCOLA_A1)->orderBy('route_name')->get();
            $this->assertSame(['Rota 1', 'Rota 2'], $rotas->pluck('route_name')->all());
            $this->assertSame(2, $rotas[0]->public_power);
            $this->assertSame(['onibus', 'van'], $rotas[0]->vehicles);
            $this->assertSame(1, $rotas[1]->public_power);
            $this->assertSame(['routes_changed' => 2], AuditLog::query()->where('action', 'transport_census.updated')->sole()->after);
        });
    }

    public function test_regras_do_censo_para_veiculos_e_acesso(): void
    {
        $this->login('school_director', [self::ESCOLA_A1]);

        $this->put($this->url(), ['rotas' => [['nome' => 'Rota 1', 'poder' => '2', 'veiculos' => []]]])
            ->assertSessionHasErrors(['rotas.0.veiculos']);
        $this->put($this->url(), ['rotas' => [['nome' => 'Rota 1', 'poder' => '2', 'veiculos' => array_keys(TransportRouteCensus::VEHICLES)]]])
            ->assertSessionHasErrors(['rotas.0.veiculos']);
        $this->put($this->url(), ['rotas' => [['nome' => 'Rota 1', 'poder' => '2', 'veiculos' => ['foguete']]]])
            ->assertSessionHasErrors(['rotas.0.veiculos.0']);
        $this->assertSame(0, $this->tenant(fn () => TransportRouteCensus::query()->count()));

        $this->login('teacher', [self::ESCOLA_A1]);
        $this->get($this->url())->assertForbidden();
        $this->login('school_secretary', [self::ESCOLA_A2]);
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/censo/transporte')->assertForbidden();
    }
}
