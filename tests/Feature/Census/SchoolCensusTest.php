<?php

namespace Tests\Feature\Census;

use App\Models\Tenant\AuditLog;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolCensus;
use App\Models\Tenant\SchoolYear;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class SchoolCensusTest extends TwoMunicipalityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant(function (): void {
            School::query()->findOrFail(self::ESCOLA_A1)->fill([
                'name' => 'Escola Fictícia Censo', 'inep_code' => '11000000',
                'address_city' => "Alta Floresta d'Oeste", 'address_state' => 'RO',
                'address_street' => 'Rua Fictícia', 'address_number' => '10',
                'postal_code' => '00000000',
            ])->save();
            SchoolYear::query()->create([
                'id' => (string) Str::uuid(), 'year' => 2026,
                'start_date' => '2026-02-02', 'end_date' => '2026-12-18', 'status' => 'active',
            ]);
        });
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
        return '/m/municipio-a/escolas/'.self::ESCOLA_A1.'/censo/escola';
    }

    private function valid(array $override = []): array
    {
        return array_replace([
            'operating_status' => '1', 'district_code' => '05',
            'location_zone' => '1', 'differentiated_location' => '7',
            'administrative_dependency' => '3', 'regulation' => '1',
            'regulation_sphere' => '3', 'linked_unit' => '0',
        ], $override);
    }

    public function test_secretaria_grava_apenas_dados_da_escola_com_auditoria(): void
    {
        $this->login('school_secretary', [self::ESCOLA_A1]);
        $this->get($this->url())->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Primeiro preenchimento')->assertSee('Na tabela do Inep:');
        $this->assertSame(0, $this->tenant(fn () => SchoolCensus::query()->count()));

        $this->put($this->url(), $this->valid(['phone_ddd' => '69', 'phone' => '12345678']))->assertRedirect($this->url());
        [$census, $log] = $this->tenant(fn (): array => [
            SchoolCensus::query()->findOrFail(self::ESCOLA_A1),
            AuditLog::query()->where('action', 'school_census.created')->sole(),
        ]);
        $this->assertSame('05', $census->district_code);
        $this->assertSame(3, $census->administrative_dependency);
        $this->assertSame('12345678', $census->phone);
        $this->assertSame(self::ESCOLA_A1, $log->after['school_id']);
        $this->assertContains('phone', $log->after['changed_fields']);
        $this->assertStringNotContainsString('12345678', json_encode($log->after));

        $this->put($this->url(), $this->valid(['phone_ddd' => '69', 'phone' => '12345678']))->assertRedirect();
        $this->assertSame(0, $this->tenant(fn () => AuditLog::query()->where('action', 'school_census.updated')->count()));
        $this->put($this->url(), $this->valid())->assertRedirect();
        $this->assertSame(1, $this->tenant(fn () => AuditLog::query()->where('action', 'school_census.updated')->count()));
    }

    public function test_regras_condicionais_nao_gravam_dados_invalidos(): void
    {
        $this->login('school_secretary', [self::ESCOLA_A1]);
        foreach ([
            ['differentiated_location' => '1'],
            ['regulation_sphere' => '1'],
            ['district_code' => '99'],
            ['linked_unit' => '1'],
            ['linked_unit' => '1', 'host_school_code' => '11000000'],
            ['phone' => '11111111'],
            ['operating_status' => '2'],
        ] as $index => $override) {
            $campo = ['differentiated_location', 'regulation_sphere', 'district_code', 'host_school_code', 'host_school_code', 'phone', 'regulation'][$index];
            $this->put($this->url(), $this->valid($override))->assertSessionHasErrors([$campo]);
        }
        $this->assertSame(0, $this->tenant(fn () => SchoolCensus::query()->count()));
    }

    public function test_professor_outra_escola_e_sem_segundo_fator_nao_gravam(): void
    {
        $this->login('teacher', [self::ESCOLA_A1]);
        $this->get($this->url())->assertForbidden();
        $this->put($this->url(), $this->valid())->assertForbidden();

        $this->login('school_secretary', [self::ESCOLA_A2]);
        $this->get($this->url())->assertForbidden();
        $this->put($this->url(), $this->valid())->assertForbidden();

        $this->login('school_secretary', [self::ESCOLA_A1], false);
        $this->get($this->url())->assertOk();
        $this->put($this->url(), $this->valid())->assertForbidden();
        $this->assertSame(0, $this->tenant(fn () => SchoolCensus::query()->count()));
    }

    public function test_conferencia_da_rede_nao_declara_escola_pronta_com_campos_fora_do_escopo(): void
    {
        $this->login('municipal_manager');
        $this->get('/m/municipio-a/conferencia-do-censo')->assertOk()
            ->assertSee('Escola pronta para o Educacenso')->assertSee('Não - conferir');
    }
}
