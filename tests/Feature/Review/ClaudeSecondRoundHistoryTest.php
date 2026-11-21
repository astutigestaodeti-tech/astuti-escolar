<?php

namespace Tests\Feature\Review;

use App\Models\Tenant\AuditLog;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Tenancy\TenantContext;
use Illuminate\Support\Str;
use LogicException;
use Tests\Support\TwoMunicipalityTestCase;

class ClaudeSecondRoundHistoryTest extends TwoMunicipalityTestCase
{
    public function test_n1_atribuicao_direta_de_deleted_at_nao_exclui_ano_com_turma_ativa(): void
    {
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $year = $this->year(2031);
            $this->schoolClass($year);
            $logsBefore = AuditLog::query()->where('auditable_id', $year->id)->count();

            $year->deleted_at = now();
            try {
                $year->save();
                $this->fail('Não é permitido excluir pelo atributo deleted_at.');
            } catch (LogicException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }

            $this->assertFalse($year->fresh()->trashed());
            $this->assertSame($logsBefore, AuditLog::query()->where('auditable_id', $year->id)->count());
        });
    }

    public function test_n1_atribuicao_direta_nao_restaura_ano_sem_autoria_e_motivo(): void
    {
        $actor = $this->criarUsuario('n1-restauracao@example.invalid');
        app(TenantContext::class)->runFor($this->municipioA, function () use ($actor): void {
            $year = $this->year(2032);
            $year->deleteWithReason('Registro fictício duplicado', $actor->id);
            $logsBefore = AuditLog::query()->where('auditable_id', $year->id)->count();

            $year->deleted_at = null;
            try {
                $year->save();
                $this->fail('Não é permitido restaurar alterando deleted_at diretamente.');
            } catch (LogicException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }

            $this->assertTrue(SchoolYear::withTrashed()->findOrFail($year->id)->trashed());
            $this->assertSame($logsBefore, AuditLog::query()->where('auditable_id', $year->id)->count());
        });
    }

    public function test_n1_motivo_e_autor_da_exclusao_nao_podem_ser_reescritos(): void
    {
        $actor = $this->criarUsuario('n1-autoria@example.invalid');
        app(TenantContext::class)->runFor($this->municipioA, function () use ($actor): void {
            $year = $this->year(2033);
            $year->deleteWithReason('Motivo original de teste', $actor->id);
            $original = SchoolYear::withTrashed()->findOrFail($year->id);
            $logsBefore = AuditLog::query()->where('auditable_id', $year->id)->count();

            foreach ([
                ['deletion_reason' => 'Motivo adulterado'],
                ['deleted_by' => (string) Str::uuid()],
                ['status' => 'closed'],
            ] as $changes) {
                $candidate = SchoolYear::withTrashed()->findOrFail($year->id);
                $candidate->forceFill($changes);
                try {
                    $candidate->save();
                    $this->fail('Um registro excluído não pode ser alterado sem restauração.');
                } catch (LogicException $exception) {
                    $this->assertNotEmpty($exception->getMessage());
                }
            }

            $after = SchoolYear::withTrashed()->findOrFail($year->id);
            $this->assertSame($original->deletion_reason, $after->deletion_reason);
            $this->assertSame($original->deleted_by, $after->deleted_by);
            $this->assertSame($original->status, $after->status);
            $this->assertSame($logsBefore, AuditLog::query()->where('auditable_id', $year->id)->count());
        });
    }

    private function year(int $year): SchoolYear
    {
        return SchoolYear::query()->create([
            'id' => (string) Str::uuid(), 'year' => $year,
            'start_date' => "{$year}-02-01", 'end_date' => "{$year}-12-18", 'status' => 'active',
        ]);
    }

    private function schoolClass(SchoolYear $year): SchoolClass
    {
        return SchoolClass::query()->create([
            'id' => (string) Str::uuid(),
            'school_id' => self::ESCOLA_A1, 'school_year_id' => $year->id,
            'name' => 'Turma inteiramente fictícia',
            'stage' => 'Ensino Fundamental', 'modality' => 'Regular', 'shift' => 'Manhã',
        ]);
    }
}
