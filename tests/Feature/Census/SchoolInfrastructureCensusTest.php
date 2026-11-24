<?php

namespace Tests\Feature\Census;

use App\Models\Tenant\AuditLog;
use App\Models\Tenant\SchoolCensus;
use App\Models\Tenant\SchoolInfrastructureCensus;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Tests\Support\InfrastructureCensusFixture;
use Tests\Support\TwoMunicipalityTestCase;

class SchoolInfrastructureCensusTest extends TwoMunicipalityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant(fn () => SchoolCensus::query()->create(['school_id' => self::ESCOLA_A1, 'operating_status' => 1]));
    }

    private function tenant(callable $callback): mixed
    {
        return app(TenantContext::class)->runFor($this->municipioA, $callback);
    }

    private function login(string $role, array $schools = [], bool $twoFactor = true): void
    {
        $user = $this->criarUsuario((string) Str::uuid().'@example.invalid');
        $this->vincular($user, $this->municipioA, $role, $schools);
        if (! $twoFactor) {
            $this->actingAs($user);

            return;
        }
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', [
            'code' => app(Totp::class)->at($secret, now()->getTimestamp()),
        ])->assertRedirect();
    }

    private function url(): string
    {
        return '/m/municipio-a/escolas/'.self::ESCOLA_A1.'/censo/infraestrutura';
    }

    private function anterior(): SchoolInfrastructureCensus
    {
        return $this->tenant(fn () => SchoolInfrastructureCensus::query()->create([
            'id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1, 'census_year' => 2025,
            'answers' => (object) InfrastructureCensusFixture::valid(),
        ]));
    }

    public function test_copia_ano_anterior_sem_gravar_e_salva_novo_ano_com_auditoria(): void
    {
        $this->anterior();
        $this->login('school_secretary', [self::ESCOLA_A1]);
        $this->get($this->url())->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Copiar do ano anterior')
            ->assertSee('Local de funcionamento da escola')
            ->assertSee('A escola desenvolve ações na área de educação ambiental?')
            ->assertSee('id="campo-91" name="answers[91]" type="number"', false)
            ->assertSee('id="campo-187"', false);
        $this->assertSame(1, $this->tenant(fn () => SchoolInfrastructureCensus::query()->count()));

        $answers = array_replace(InfrastructureCensusFixture::valid(), [91 => '5']);
        $this->put($this->url(), ['answers' => $answers])->assertRedirect($this->url());
        $this->tenant(function (): void {
            $this->assertSame(2, SchoolInfrastructureCensus::query()->count());
            $this->assertSame('4', ((array) SchoolInfrastructureCensus::query()->where('census_year', 2025)->sole()->answers)[91]);
            $this->assertSame('5', ((array) SchoolInfrastructureCensus::query()->where('census_year', 2026)->sole()->answers)[91]);
            $log = AuditLog::query()->where('action', 'school_infrastructure_census.created')->sole();
            $this->assertSame(self::ESCOLA_A1, $log->after['school_id']);
            $this->assertArrayHasKey('changed_fields', $log->after);
            $this->assertArrayNotHasKey('answers', $log->after);
        });
        $this->get($this->url())->assertOk()->assertDontSee('Copiar do ano anterior');
        $this->put($this->url(), ['answers' => $answers])->assertRedirect($this->url());
        $this->assertSame(0, $this->tenant(fn () => AuditLog::query()->where('action', 'school_infrastructure_census.updated')->count()));
    }

    public function test_nao_grava_respostas_invalidas_ou_campos_injetados(): void
    {
        $this->login('school_secretary', [self::ESCOLA_A1]);
        $base = InfrastructureCensusFixture::valid();
        $this->put($this->url(), ['answers' => array_replace($base, [23 => '1'])])->assertSessionHasErrors(['answers.23']);
        $this->put($this->url(), ['answers' => array_replace($base, [91 => '0'])])->assertSessionHasErrors(['answers.91']);
        $this->put($this->url(), ['answers' => array_replace($base, [188 => '1'])])->assertSessionHasErrors(['answers']);
        $this->assertSame(0, $this->tenant(fn () => SchoolInfrastructureCensus::query()->count()));
    }

    public function test_professor_outra_escola_e_sem_segundo_fator_nao_gravam(): void
    {
        $data = ['answers' => InfrastructureCensusFixture::valid()];
        $this->login('teacher', [self::ESCOLA_A1]);
        $this->get($this->url())->assertForbidden();
        $this->put($this->url(), $data)->assertForbidden();

        $this->login('school_secretary', [self::ESCOLA_A2]);
        $this->get($this->url())->assertForbidden();
        $this->put($this->url(), $data)->assertForbidden();

        $this->login('school_secretary', [self::ESCOLA_A1], false);
        $this->get($this->url())->assertOk();
        $this->put($this->url(), $data)->assertForbidden();
        $this->assertSame(0, $this->tenant(fn () => SchoolInfrastructureCensus::query()->count()));
    }
}
