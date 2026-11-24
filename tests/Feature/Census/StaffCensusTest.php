<?php

namespace Tests\Feature\Census;

use App\Models\Tenant\AuditLog;
use App\Models\Tenant\StaffCensus;
use App\Models\Tenant\StaffMember;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class StaffCensusTest extends TwoMunicipalityTestCase
{
    private string $memberId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-05-20 10:00:00'));
        $this->memberId = $this->tenant(fn (): string => StaffMember::query()->create([
            'id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1, 'full_name' => 'Professora Fictícia Censo',
            'role' => 'teacher', 'institutional_email' => 'professora@escola.example', 'status' => 'active',
        ])->id);
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

    private function url(?string $school = null, ?string $member = null): string
    {
        return '/m/municipio-a/escolas/'.($school ?? self::ESCOLA_A1).'/servidores/'.($member ?? $this->memberId).'/censo';
    }

    private function valid(array $override = []): array
    {
        return array_replace([
            'cpf' => '529.982.247-25', 'nascimento' => '1985-04-10', 'sexo' => 'female', 'cor' => 'parda',
            'filiacao' => '1', 'filiacao_1' => 'Maria Fictícia Censo', 'filiacao_2' => '',
            'nacionalidade' => 'brasileira', 'nascimento_uf' => 'SP', 'nascimento_municipio' => 'santa isabel',
            'condicoes' => ['baixa_visao'],
            'escolaridade' => '6', 'ensino_medio' => '2',
            'cursos' => [['curso' => '0113P011', 'ano' => '2008', 'ies' => '55'], ['curso' => '', 'ano' => '', 'ies' => '']],
            'complementacao' => ['25', ''],
            'pos' => [['tipo' => '1', 'area' => '1', 'ano' => '2012'], ['tipo' => '', 'area' => '', 'ano' => '']],
            'formacao' => ['alfabetizacao', 'educacao_especial'],
        ], $override);
    }

    public function test_secretaria_grava_codigos_oficiais_e_regravar_igual_nao_gera_auditoria(): void
    {
        $this->login('school_secretary', [self::ESCOLA_A1]);

        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/servidores')->assertOk()->assertSee($this->url(), false);
        $this->get($this->url())->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Pedagogia - Licenciatura')->assertSee('Formação continuada');

        $this->put($this->url(), $this->valid())->assertRedirect('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/servidores');

        [$census, $log] = $this->tenant(fn (): array => [
            StaffCensus::query()->findOrFail($this->memberId),
            AuditLog::query()->where('action', 'staff_census.created')->sole(),
        ]);
        $this->assertSame('52998224725', $census->cpf);
        $this->assertSame(76, $census->nationality_country_code);
        $this->assertSame(3546801, $census->birth_municipality_code);
        $this->assertSame(['baixa_visao'], $census->conditions);
        $this->assertSame([['ano' => 2008, 'ies' => 55, 'curso' => '0113P011']], $census->higher_courses);
        $this->assertSame([25], $census->pedagogical_areas);
        $this->assertSame([['ano' => 2012, 'area' => 1, 'tipo' => 1]], $census->postgraduates);
        $this->assertSame(['alfabetizacao', 'educacao_especial'], $census->continuing_education);
        $this->assertArrayHasKey('changed_fields', $log->after);
        $this->assertStringNotContainsString('52998224725', json_encode([$log->before, $log->after]));

        $this->get($this->url())->assertOk()->assertSee('Na tabela do Inep: UNIVERSIDADE DE SAO PAULO.')
            ->assertSee('value="529.982.247-25"', false);
        $this->put($this->url(), $this->valid())->assertRedirect();
        $this->assertSame(0, $this->tenant(fn () => AuditLog::query()->where('action', 'staff_census.updated')->count()));

        $this->put($this->url(), $this->valid(['formacao' => ['nenhum'], 'escolaridade' => '7', 'condicoes' => []]))->assertRedirect();
        $census = $this->tenant(fn () => StaffCensus::query()->findOrFail($this->memberId));
        $this->assertSame([], $census->continuing_education);
        $this->assertNull($census->higher_courses);
        $this->assertNull($census->pedagogical_areas);
        $this->assertSame([], $census->conditions);
    }

    public function test_regras_do_registro_30_recusam_combinacoes_invalidas(): void
    {
        $this->login('school_director', [self::ESCOLA_A1]);

        $this->put($this->url(), $this->valid(['cpf' => '529.982.247-26']))->assertSessionHasErrors(['cpf']);
        $this->put($this->url(), $this->valid(['cursos' => [['curso' => '0113P011', 'ano' => '2008', 'ies' => '8888888']]]))
            ->assertSessionHasErrors(['cursos.0.ies']);
        $this->put($this->url(), $this->valid(['cursos' => [['curso' => '0113P011', 'ano' => '', 'ies' => '55']]]))
            ->assertSessionHasErrors(['cursos.0.curso']);
        $this->put($this->url(), $this->valid(['complementacao' => ['99']]))->assertSessionHasErrors(['complementacao.0']);
        $this->put($this->url(), $this->valid(['sem_pos' => '1']))->assertSessionHasErrors(['sem_pos']);
        $this->put($this->url(), $this->valid(['pos' => [['tipo' => '2', 'area' => '1', 'ano' => '2005']]]))
            ->assertSessionHasErrors(['pos.0.ano']);
        $this->put($this->url(), $this->valid(['formacao' => ['nenhum', 'outros']]))->assertSessionHasErrors(['formacao']);
        $this->put($this->url(), $this->valid(['nacionalidade' => 'estrangeira', 'pais_nacionalidade' => '76']))
            ->assertSessionHasErrors(['pais_nacionalidade']);
        $this->assertNull($this->tenant(fn () => StaffCensus::query()->find($this->memberId)));

        $outro = $this->tenant(function (): string {
            $outro = StaffMember::query()->create([
                'id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1, 'full_name' => 'Diretor Fictício Censo',
                'role' => 'director', 'status' => 'active',
            ]);
            StaffCensus::query()->create(['staff_member_id' => $outro->id, 'cpf' => '52998224725', 'filiation' => 0]);

            return $outro->id;
        });
        $this->put($this->url(), $this->valid())->assertSessionHasErrors(['cpf']);
        $this->assertNotSame($outro, $this->memberId);
    }

    public function test_professor_outra_escola_e_sem_segundo_fator_nao_acessam(): void
    {
        $this->login('teacher', [self::ESCOLA_A1]);
        $this->get($this->url())->assertForbidden();

        $this->login('school_secretary', [self::ESCOLA_A2]);
        $this->get($this->url(self::ESCOLA_A2))->assertNotFound();

        $this->login('school_secretary', [self::ESCOLA_A1], false);
        $this->get($this->url())->assertRedirect();
        $this->put($this->url(), $this->valid())->assertForbidden();
        $this->assertNull($this->tenant(fn () => StaffCensus::query()->find($this->memberId)));
    }
}
