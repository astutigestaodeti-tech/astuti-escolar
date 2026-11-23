<?php

namespace Tests\Feature\Waitlist;

use App\Models\Tenant\AuditLog;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\WaitlistRequest;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use App\Waitlist\StageCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class WaitlistTest extends TwoMunicipalityTestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private const ESCOLA_A3 = '44444444-4444-4444-8444-444444444444';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-23 15:00:00'));
        config()->set('waitlist.cutoff_date', '2027-03-31');

        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            School::query()->create(['id' => self::ESCOLA_A3, 'name' => 'Terceira Unidade Fictícia', 'status' => 'active']);
            $year = SchoolYear::query()->create([
                'id' => (string) Str::uuid(), 'year' => 2027,
                'start_date' => '2027-02-01', 'end_date' => '2027-12-20', 'status' => 'active',
            ]);
            foreach ([self::ESCOLA_A1, self::ESCOLA_A2, self::ESCOLA_A3] as $school) {
                SchoolClass::query()->create([
                    'id' => (string) Str::uuid(), 'school_id' => $school, 'school_year_id' => $year->id,
                    'name' => 'Maternal I', 'stage' => 'Educação Infantil', 'modality' => 'Regular',
                    'shift' => 'Manhã', 'status' => 'active',
                ]);
            }
        });
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function login(string $role, array $schoolIds = [self::ESCOLA_A1]): void
    {
        $user = $this->criarUsuario((string) Str::uuid().'@example.invalid');
        $this->vincular($user, $this->municipioA, $role, $schoolIds);
        $user->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', [
            'code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp()),
        ])->assertRedirect();
    }

    private function inMunicipality(callable $callback): mixed
    {
        return app(TenantContext::class)->runFor($this->municipioA, $callback);
    }

    private function url(): string
    {
        return '/m/municipio-a/lista-de-espera';
    }

    private function form(string $name = 'Ana Criança Fictícia', array $extra = []): array
    {
        return array_replace([
            'child_name' => $name,
            'birth_date' => '2024-10-01',
            'cpf' => '123.456.789-09',
            'guardian_name' => 'Rosa Responsável Fictícia',
            'guardian_phone' => '(11) 98765-4321',
            'postal_code' => '01001-000',
            'address_street' => 'Rua Fictícia',
            'address_number' => '10',
            'address_district' => 'Bairro Teste',
            'address_city' => 'Cidade Inventada',
            'address_state' => 'SP',
            'desired_shift' => 'manha',
            'preferences' => [self::ESCOLA_A1],
            'confirm_stage' => '1',
        ], $extra);
    }

    private function requestFixture(string $name, string $protocol, int $priority = 0, array $schools = [self::ESCOLA_A1]): string
    {
        return $this->inMunicipality(function () use ($name, $protocol, $priority, $schools): string {
            $criteria = $priority > 0 ? array_slice([
                'sibling' => 'Irmão na unidade fictícia.',
                'vulnerability' => 'Situação fictícia de prioridade.',
            ], 0, $priority, true) : [];

            $record = WaitlistRequest::query()->create([
                'id' => (string) Str::uuid(),
                'protocol' => $protocol, 'child_name' => $name, 'birth_date' => '2024-10-01',
                'cpf' => null, 'guardian_name' => 'Responsável Fictício',
                'guardian_phone' => '11987654321', 'postal_code' => '01001-000',
                'address_street' => 'Rua Fictícia', 'address_number' => '10',
                'address_district' => 'Bairro Teste', 'address_city' => 'Cidade Inventada',
                'address_state' => 'SP', 'intake_year' => 2027, 'cutoff_date' => '2027-03-31',
                'stage' => 'maternal_1', 'desired_shift' => 'manha',
                'priority_criteria' => $criteria, 'priority_count' => count($criteria),
                'status' => 'aguardando', 'requested_at' => now(), 'created_by' => $this->auditFixtureUser->id,
            ]);
            foreach ($schools as $index => $school) {
                $record->preferences()->create([
                    'id' => (string) Str::uuid(), 'school_id' => $school, 'preference_order' => $index + 1,
                ]);
            }

            return $record->id;
        });
    }

    public function test_etapa_por_corte_configuravel_e_confirmacao_obrigatoria(): void
    {
        $calculator = app(StageCalculator::class);
        $this->assertSame('bercario', $calculator->stageFor('2026-04-01'));
        $this->assertSame('maternal_1', $calculator->stageFor('2025-03-31'));
        $this->assertSame('maternal_2', $calculator->stageFor('2024-03-31'));
        $this->assertSame('pre_1', $calculator->stageFor('2023-03-31'));
        $this->assertSame('pre_2', $calculator->stageFor('2022-03-31'));
        $this->assertNull($calculator->stageFor('2019-01-01'));
        $this->assertNull($calculator->stageFor('2027-04-01'));

        config()->set('waitlist.cutoff_date', '2027-04-30');
        $this->assertSame('pre_1', $calculator->stageFor('2023-04-15'));
        config()->set('waitlist.cutoff_date', '2027-03-31');

        $this->login('municipal_manager', []);
        $this->post($this->url(), $this->form(extra: ['confirm_stage' => null]))
            ->assertSessionHasErrors('confirm_stage');
        $this->assertSame(0, $this->inMunicipality(fn (): int => WaitlistRequest::query()->count()));
    }

    public function test_cadastro_gera_protocolo_imutavel_com_prioridade_e_auditoria_mascarada(): void
    {
        $this->login('school_secretary');
        $this->get($this->url().'/novo')->assertOk()->assertSee('Buscar CEP')
            ->assertSee('Data de corte')->assertSee('confirmo a etapa');
        $this->post($this->url(), $this->form(extra: [
            'preferences' => [self::ESCOLA_A1, self::ESCOLA_A2, ''],
            'priority_flags' => ['disability' => '1'],
            'priority_reasons' => ['disability' => 'Atendimento prioritário informado pela família.'],
        ]))->assertRedirect();

        $data = $this->inMunicipality(function (): array {
            $record = WaitlistRequest::query()->with('preferences')->sole();
            $audit = AuditLog::query()->where('action', 'waitlist.created')->sole();

            return [
                'id' => $record->id,
                'protocol' => $record->protocol,
                'status' => $record->status,
                'stage' => $record->stage,
                'phone' => $record->guardian_phone,
                'priority_count' => $record->priority_count,
                'schools' => $record->preferences->pluck('school_id')->all(),
                'audit' => json_encode($audit->after),
            ];
        });

        $this->assertMatchesRegularExpression('/\A[A-HJ-NP-Z2-9]{10}\z/D', $data['protocol']);
        $this->assertSame('aguardando', $data['status']);
        $this->assertSame('maternal_1', $data['stage']);
        $this->assertSame('11987654321', $data['phone']);
        $this->assertSame(1, $data['priority_count']);
        $this->assertSame([self::ESCOLA_A1, self::ESCOLA_A2], $data['schools']);
        $this->assertStringContainsString('[REDACTED]', $data['audit']);
        $this->assertStringNotContainsString('Atendimento prioritário', $data['audit']);
        $this->assertStringNotContainsString('11987654321', $data['audit']);
        $this->assertStringNotContainsString('123.456.789-09', $data['audit']);

        $this->get($this->url().'/'.$data['id'])->assertOk()
            ->assertSee('Atendimento prioritário informado pela família.')
            ->assertSee('123.456.789-09');
    }

    public function test_fila_ordenada_por_prioridade_depois_horario_e_posicao_nao_muda_com_busca(): void
    {
        $this->travelTo(Carbon::parse('2026-11-23 10:00:00'));
        $old = $this->requestFixture('Bruno Criança Fictícia', 'ABCDEFG234');
        $this->travelTo(Carbon::parse('2026-11-23 11:00:00'));
        $first = $this->requestFixture('Carla Criança Fictícia', 'ABCDEFG235', 1, [self::ESCOLA_A1, self::ESCOLA_A2]);
        $this->travelTo(Carbon::parse('2026-11-23 12:00:00'));
        $second = $this->requestFixture('Diana Criança Fictícia', 'ABCDEFG236', 1);
        $this->login('municipal_manager', []);

        $url = $this->url().'?escola='.self::ESCOLA_A1.'&etapa=maternal_1';
        $response = $this->get($url)->assertOk();
        $rows = collect($response->viewData('items')->items())->map(fn ($row): array => [
            'id' => $row->id,
            'position' => (int) $row->queue_position,
        ])->all();

        $this->assertSame([
            ['id' => $first, 'position' => 1],
            ['id' => $second, 'position' => 2],
            ['id' => $old, 'position' => 3],
        ], $rows);

        $filtered = $this->get($url.'&busca=Diana')->assertOk()->viewData('items')->items();
        $this->assertCount(1, $filtered);
        $this->assertSame(2, (int) $filtered[0]->queue_position);
        $this->get($this->url().'?escola='.self::ESCOLA_A2)->assertOk()->assertSee('Carla Criança Fictícia')
            ->assertDontSee('Bruno Criança Fictícia');
    }

    public function test_duplicidade_por_nome_nascimento_ou_cpf_pede_confirmacao(): void
    {
        $this->login('municipal_manager', []);
        $this->post($this->url(), $this->form())->assertRedirect();
        $this->post($this->url(), $this->form(extra: ['cpf' => null]))
            ->assertSessionHasErrors('child_name')->assertSessionHas('waitlist_duplicate_warning');
        $this->post($this->url(), $this->form(name: 'Outra Criança Fictícia'))
            ->assertSessionHasErrors('child_name')->assertSessionHas('waitlist_duplicate_warning');
        $this->assertSame(1, $this->inMunicipality(fn (): int => WaitlistRequest::query()->count()));
        $this->post($this->url(), $this->form(name: 'Outra Criança Fictícia', extra: ['confirm_duplicate' => '1']))
            ->assertRedirect();
        $this->assertSame(2, $this->inMunicipality(fn (): int => WaitlistRequest::query()->count()));
    }

    public function test_professor_e_outra_escola_nao_acessam_justificativas(): void
    {
        $id = $this->requestFixture('Criança Privada Fictícia', 'ABCDEFG237', 1);
        $this->login('teacher');
        $this->get($this->url())->assertForbidden();
        $this->get($this->url().'/novo')->assertForbidden();
        $this->get($this->url().'/'.$id)->assertForbidden();

        $this->login('school_secretary', [self::ESCOLA_A2]);
        $this->get($this->url())->assertOk()->assertDontSee('Criança Privada Fictícia');
        $this->get($this->url().'/'.$id)->assertNotFound();
        $this->post($this->url(), $this->form())->assertForbidden();

        $this->login('school_director', [self::ESCOLA_A1]);
        $this->get($this->url().'/'.$id)->assertOk()->assertSee('Irmão na unidade fictícia.');
        $this->get($this->url().'/novo')->assertForbidden();
        $this->post($this->url().'/'.$id.'/situacao', ['status' => 'convocado', 'reason' => 'Encaminhamento fictício'])
            ->assertForbidden();
    }

    public function test_segundo_fator_recente_motivo_e_exclusao_logica_auditada(): void
    {
        $id = $this->requestFixture('Criança de Teste Fictícia', 'ABCDEFG238');
        $this->login('school_secretary');
        $url = $this->url().'/'.$id.'/situacao';
        $this->post($url, ['status' => 'convocado', 'reason' => ''])->assertSessionHasErrors('reason');
        $this->post($url, ['status' => 'convocado', 'reason' => 'Convocação manual de teste.'])->assertRedirect();

        $this->travelTo(now()->addMinutes(20));
        $this->post($url, ['status' => 'atendido', 'reason' => 'Conclusão fictícia registrada.'])
            ->assertRedirect(route('two-factor.challenge'));
        $this->assertSame('convocado', $this->inMunicipality(fn (): string => WaitlistRequest::query()->findOrFail($id)->status));

        $this->actingAs(auth()->user())->post('/seguranca/segundo-fator/desafio', [
            'code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp()),
        ])->assertRedirect();
        $this->post($this->url().'/'.$id.'/arquivar', ['reason' => 'Pedido duplicado confirmado em teste.'])->assertRedirect();

        $data = $this->inMunicipality(function () use ($id): array {
            $record = WaitlistRequest::withTrashed()->findOrFail($id);
            $audits = AuditLog::query()->whereIn('action', ['waitlist.status.changed', 'waitlist.archived'])
                ->orderBy('occurred_at')->get();

            return [
                'trashed' => $record->trashed(),
                'count' => $audits->count(),
                'snapshots' => $audits->pluck('after')->all(),
                'history_count' => \Illuminate\Support\Facades\DB::connection('tenant')->table('waitlist_status_changes')
                    ->where('waitlist_request_id', $id)->count(),
            ];
        });

        $this->assertTrue($data['trashed']);
        $this->assertSame(2, $data['count']);
        $this->assertSame(1, $data['history_count']);
        $this->assertStringContainsString('[REDACTED]', json_encode($data['snapshots']));
        $this->assertStringNotContainsString('Pedido duplicado confirmado', json_encode($data['snapshots']));
        $this->get($this->url().'/'.$id)->assertNotFound();
    }
}
