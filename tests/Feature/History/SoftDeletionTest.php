<?php

namespace Tests\Feature\History;

use App\Models\Tenant\AuditLog;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Tenancy\TenantContext;
use LogicException;
use Tests\Support\TwoMunicipalityTestCase;

class SoftDeletionTest extends TwoMunicipalityTestCase
{
    public function test_turma_excluida_continua_na_base_com_autoria_motivo_e_restaura_com_auditoria(): void
    {
        $user = $this->criarUsuario('secretaria-historico@example.invalid');
        $this->vincular($user, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);
        $this->actingAs($user);

        app(TenantContext::class)->runFor($this->municipioA, function () use ($user): void {
            $year = SchoolYear::query()->create(['id' => 'a1020000-0000-4000-8000-000000000001', 'year' => 2027, 'start_date' => '2027-02-01', 'end_date' => '2027-12-17', 'status' => 'planned']);
            $class = SchoolClass::query()->create([
                'id' => 'a1020000-0000-4000-8000-000000000002',
                'school_id' => self::ESCOLA_A1,
                'school_year_id' => $year->id,
                'name' => 'Turma de testes Monte Sereno',
                'stage' => 'Ensino Fundamental',
                'modality' => 'Regular',
                'shift' => 'Manhã',
            ]);

            $class->deleteWithReason('Registro duplicado no teste', $user->id);
            $this->assertNull(SchoolClass::query()->find($class->id));
            $deleted = SchoolClass::withTrashed()->findOrFail($class->id);
            $this->assertNotNull($deleted->deleted_at);
            $this->assertSame($user->id, $deleted->deleted_by);
            $this->assertSame('Registro duplicado no teste', $deleted->deletion_reason);

            $deleted->restoreWithReason('Documento conferido pela secretaria', $user->id);
            $this->assertNotNull(SchoolClass::query()->find($class->id));
            $actions = AuditLog::query()->where('auditable_id', $class->id)->orderBy('occurred_at')->pluck('action')->all();
            $this->assertSame(['created', 'deleted', 'restored'], $actions);
            $this->assertSame($user->id, AuditLog::query()->where('auditable_id', $class->id)->where('action', 'deleted')->firstOrFail()->user_id);
        });
    }

    public function test_exclusao_sem_motivo_ou_autoria_e_exclusao_fisica_sao_bloqueadas(): void
    {
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $year = SchoolYear::query()->create(['id' => 'a1020000-0000-4000-8000-000000000003', 'year' => 2027, 'start_date' => '2027-02-01', 'end_date' => '2027-12-17', 'status' => 'planned']);
            try {
                $year->delete();
                $this->fail('Exclusão sem autoria e motivo não deve ser possível.');
            } catch (LogicException) {
                $this->assertNotNull(SchoolYear::query()->find($year->id));
            }

            $this->expectException(LogicException::class);
            $year->forceDelete();
        });
    }

    public function test_exclusao_em_lote_e_reuso_do_motivo_anterior_sao_bloqueados(): void
    {
        $user = $this->criarUsuario('historico-sem-atalhos@example.invalid');
        $this->actingAs($user);

        app(TenantContext::class)->runFor($this->municipioA, function () use ($user): void {
            $year = SchoolYear::query()->create(['id' => 'a1020000-0000-4000-8000-000000000004', 'year' => 2027, 'start_date' => '2027-02-01', 'end_date' => '2027-12-17', 'status' => 'planned']);
            try {
                SchoolYear::query()->whereKey($year->id)->delete();
                $this->fail('Exclusão em lote não pode contornar autoria e motivo.');
            } catch (LogicException) {
                $this->assertNotNull(SchoolYear::query()->find($year->id));
            }

            $year->deleteWithReason('Duplicidade sintética', $user->id);
            $year->restoreWithReason('Conferência de registro duplicado', $user->id);

            $this->expectException(LogicException::class);
            $year->delete();
        });
    }

    public function test_turma_conserva_referencia_a_ano_letivo_arquivado(): void
    {
        $user = $this->criarUsuario('consulta-historica@example.invalid');
        $this->vincular($user, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);
        $this->actingAs($user);

        app(TenantContext::class)->runFor($this->municipioA, function () use ($user): void {
            $year = SchoolYear::query()->create(['id' => 'a1020000-0000-4000-8000-000000000005', 'year' => 2027, 'start_date' => '2027-02-01', 'end_date' => '2027-12-17', 'status' => 'active']);
            $class = SchoolClass::query()->create([
                'id' => 'a1020000-0000-4000-8000-000000000006', 'school_id' => self::ESCOLA_A1,
                'school_year_id' => $year->id, 'name' => 'Turma Monte Sereno',
                'stage' => 'Ensino Fundamental', 'modality' => 'Regular', 'shift' => 'Tarde', 'status' => 'active',
            ]);
            $class->deleteWithReason('Turma arquivada após o encerramento', $user->id);
            $year->deleteWithReason('Ano encerrado em cenário fictício', $user->id);
            $this->assertSame(2027, SchoolClass::withTrashed()->findOrFail($class->id)->schoolYear->year);
        });
    }
}
