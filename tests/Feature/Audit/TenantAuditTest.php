<?php

namespace Tests\Feature\Audit;

use App\Models\Tenant\AuditLog;
use App\Models\Tenant\SchoolYear;
use App\Tenancy\TenantContext;
use LogicException;
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
            app(\App\Audit\AuditTrail::class)->record('tenant', 'test.redacted', SchoolYear::class, null,
                ['password' => 'segredo-123', 'token' => 'token-123'],
                ['medical_report' => 'laudo-123', 'nested' => ['laudo' => 'diagnostico-123']]);

            $log = AuditLog::query()->where('action', 'test.redacted')->firstOrFail();
            $this->assertSame('[REDACTED]', $log->before['password']);
            $this->assertSame('[REDACTED]', $log->before['token']);
            $this->assertSame('[REDACTED]', $log->after['medical_report']);
            $this->assertSame('[REDACTED]', $log->after['nested']['laudo']);
            $this->assertStringNotContainsString('segredo-123', $log->toJson());
        });
    }
}
