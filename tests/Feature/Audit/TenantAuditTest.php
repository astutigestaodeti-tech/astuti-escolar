<?php

namespace Tests\Feature\Audit;

use App\Audit\AuditTrail;
use App\Models\Tenant\AuditLog;
use App\Models\Tenant\SchoolYear;
use App\Tenancy\TenantContext;
use LogicException;
use Mockery;
use RuntimeException;
use Tests\Support\TwoMunicipalityTestCase;

class TenantAuditTest extends TwoMunicipalityTestCase
{
    public function test_criacao_e_atualizacao_do_ano_escolar_registram_antes_e_depois(): void
    {
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $year = SchoolYear::query()->create([
                'id' => 'a1010000-0000-4000-8000-000000000001',
                'year' => 2027,
                'start_date' => '2027-02-01',
                'end_date' => '2027-12-17',
                'status' => 'planned',
            ]);

            $year->update(['status' => 'active']);

            $logs = AuditLog::query()->where('auditable_id', $year->id)->orderBy('occurred_at')->get();
            $this->assertCount(2, $logs);
            $this->assertSame(['created', 'updated'], $logs->pluck('action')->all());
            $this->assertNull($logs[0]->before);
            $this->assertSame('planned', $logs[0]->after['status']);
            $this->assertSame('planned', $logs[1]->before['status']);
            $this->assertSame('active', $logs[1]->after['status']);
            $this->assertNotEmpty($logs[1]->request_id);
        });
    }

    public function test_registros_de_auditoria_nao_podem_ser_alterados(): void
    {
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $year = SchoolYear::query()->create([
                'id' => 'a1010000-0000-4000-8000-000000000002',
                'year' => 2027,
                'start_date' => '2027-02-01',
                'end_date' => '2027-12-17',
                'status' => 'planned',
            ]);
            $log = AuditLog::query()->where('auditable_id', $year->id)->firstOrFail();
            $this->expectException(LogicException::class);
            $log->update(['action' => 'forged']);
        });
    }

    public function test_registros_de_auditoria_nao_podem_ser_excluidos(): void
    {
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $year = SchoolYear::query()->create([
                'id' => 'a1010000-0000-4000-8000-000000000003',
                'year' => 2027,
                'start_date' => '2027-02-01',
                'end_date' => '2027-12-17',
                'status' => 'planned',
            ]);
            $log = AuditLog::query()->where('auditable_id', $year->id)->firstOrFail();
            $this->expectException(LogicException::class);
            $log->delete();
        });
    }

    public function test_auditoria_respeita_a_sequencia_a_b_a_sem_vazamento(): void
    {
        $context = app(TenantContext::class);
        $id = 'a1010000-0000-4000-8000-000000000004';

        $context->runFor($this->municipioA, function () use ($id): void {
            SchoolYear::query()->create(['id' => $id, 'year' => 2027, 'start_date' => '2027-02-01', 'end_date' => '2027-12-17', 'status' => 'planned']);
            $this->assertSame(1, AuditLog::query()->where('auditable_id', $id)->count());
        });

        $context->runFor($this->municipioB, function () use ($id): void {
            $this->assertSame(0, AuditLog::query()->where('auditable_id', $id)->count());
            SchoolYear::query()->create(['id' => $id, 'year' => 2027, 'start_date' => '2027-02-01', 'end_date' => '2027-12-17', 'status' => 'active']);
            $this->assertSame('active', AuditLog::query()->where('auditable_id', $id)->firstOrFail()->after['status']);
        });

        $context->runFor($this->municipioA, function () use ($id): void {
            $this->assertSame('planned', AuditLog::query()->where('auditable_id', $id)->firstOrFail()->after['status']);
        });
    }

    public function test_senhas_tokens_e_laudos_sao_mascarados_antes_da_gravacao(): void
    {
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            app(AuditTrail::class)->record('tenant', 'test.redacted', SchoolYear::class, null,
                ['password' => 'segredo-123', 'token' => 'token-123'],
                ['medical_report' => 'laudo-123', 'nested' => ['laudo' => 'diagnostico-123']]);

            $log = AuditLog::query()->where('action', 'test.redacted')->firstOrFail();
            $this->assertSame([], $log->before);
            $this->assertSame([], $log->after);
            $this->assertStringNotContainsString('segredo-123', $log->toJson());
            $this->assertStringNotContainsString('laudo-123', $log->toJson());
        });
    }

    public function test_sem_auditoria_a_criacao_e_desfeita_na_mesma_transacao(): void
    {
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $mock = Mockery::mock(AuditTrail::class);
            $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('Auditoria indisponível'));
            app()->instance(AuditTrail::class, $mock);

            $id = 'a1010000-0000-4000-8000-000000000005';
            try {
                SchoolYear::query()->create(['id' => $id, 'year' => 2027, 'start_date' => '2027-02-01', 'end_date' => '2027-12-17', 'status' => 'planned']);
                $this->fail('A criação sem registro de auditoria deveria falhar.');
            } catch (RuntimeException $exception) {
                $this->assertSame('Auditoria indisponível', $exception->getMessage());
            }

            $this->assertNull(SchoolYear::query()->find($id));
        });
    }

    public function test_instancia_carregada_em_a_nao_pode_alterar_mesmo_uuid_em_b(): void
    {
        $context = app(TenantContext::class);
        $id = 'a1010000-0000-4000-8000-000000000006';

        $yearA = $context->runFor($this->municipioA, fn () => SchoolYear::query()->create([
            'id' => $id, 'year' => 2027, 'start_date' => '2027-02-01', 'end_date' => '2027-12-17', 'status' => 'planned',
        ]));

        $context->runFor($this->municipioB, function () use ($id, $yearA): void {
            SchoolYear::query()->create(['id' => $id, 'year' => 2027, 'start_date' => '2027-02-01', 'end_date' => '2027-12-17', 'status' => 'active']);

            try {
                $yearA->update(['status' => 'cancelled']);
                $this->fail('Uma instância do município A não pode gravar no município B.');
            } catch (LogicException) {
                $this->assertSame('active', SchoolYear::query()->findOrFail($id)->status);
            }
        });

        $context->runFor($this->municipioA, function () use ($id): void {
            $this->assertSame('planned', SchoolYear::query()->findOrFail($id)->status);
        });
    }

    public function test_multiplos_papeis_ativos_sao_identificados_sem_atribuicao_aleatoria(): void
    {
        $user = $this->criarUsuario('auditor-varios-papeis@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');
        $this->vincular($user, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);
        $this->actingAs($user);

        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $year = SchoolYear::query()->create(['id' => 'a1010000-0000-4000-8000-000000000007', 'year' => 2027, 'start_date' => '2027-02-01', 'end_date' => '2027-12-17', 'status' => 'planned']);
            $log = AuditLog::query()->where('auditable_id', $year->id)->firstOrFail();
            $this->assertSame('municipal_manager,school_secretary', $log->role);
        });
    }

    public function test_update_e_delete_em_lote_na_auditoria_sao_bloqueados(): void
    {
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            try {
                AuditLog::query()->update(['action' => 'fraudado']);
                $this->fail('Não pode atualizar registros de auditoria em lote.');
            } catch (LogicException) {
                $this->assertTrue(true);
            }

            $this->expectException(LogicException::class);
            AuditLog::query()->delete();
        });
    }
}
