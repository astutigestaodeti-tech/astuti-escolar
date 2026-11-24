<?php

namespace App\Academics;

use App\Audit\AuditTrail;
use App\Educacenso\SugestaoDeTurma;
use App\Models\Tenant\AcademicPeriod;
use App\Models\Tenant\ClassYearClosure;
use App\Models\Tenant\CurricularComponent;
use App\Models\Tenant\LessonRecord;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolClassCensus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class LessonDiary
{
    // Validação de formato, não de existência da habilidade na BNCC.
    public const BNCC_FORMAT = '/\A(?:EI0[1-3][A-Z]{2}[0-9]{2}|EF[0-9]{2}[A-Z]{2}[0-9]{2}|EM[0-9]{2}[A-Z]{3}[0-9]{3})\z/D';

    public function dateAllowed(SchoolClass $class, Carbon $date): bool
    {
        $year = $class->schoolYear;

        return $year !== null && $year->start_date !== null && $year->end_date !== null
            && $date->lte(today()) && $date->betweenIncluded($year->start_date, $year->end_date);
    }

    public function polivalent(SchoolClass $class): bool
    {
        $stage = SchoolClassCensus::query()->find($class->id)?->inep_stage_code ?? SugestaoDeTurma::etapa($class);

        if ($stage !== null) {
            return in_array((int) $stage, [1, 2, 14, 15, 16, 17, 18], true);
        }

        $text = Str::lower(Str::ascii($class->stage.' '.$class->modality));

        return ! preg_match('/\beja\b|jovens e adultos|anos finais/', $text)
            && (str_contains($text, 'infantil') || str_contains($text, 'anos iniciais'));
    }

    public function day(SchoolClass $class, Carbon $date): Collection
    {
        return LessonRecord::query()->where('school_class_id', $class->id)
            ->where('lesson_date', $date->toDateString())->with('component:id,name')
            ->orderBy('curricular_component_id')->get();
    }

    public function report(SchoolClass $class, AcademicPeriod $period): Collection
    {
        return LessonRecord::query()->where('school_class_id', $class->id)
            ->whereBetween('lesson_date', [$period->starts_on->toDateString(), $period->ends_on->toDateString()])
            ->with('component:id,name')->orderBy('lesson_date')->orderBy('curricular_component_id')->get();
    }

    /**
     * Entrada online. A versão 0 significa que não havia registro na tela.
     * A sincronização futura deverá compartilhar as travas e a auditoria, mas
     * manter o histórico idempotente de operações em estrutura própria.
     */
    public function save(SchoolClass $class, Carbon $date, ?string $component, array $data, string $actor): LessonRecord
    {
        return DB::connection('tenant')->transaction(function () use ($class, $date, $component, $data, $actor): LessonRecord {
            $closure = ClassYearClosure::query()->lockForUpdate()->findOrFail($class->id);
            if ($closure->closed_at !== null) {
                throw ValidationException::withMessages(['conteudo' => 'Ano letivo encerrado. O conteúdo está somente para consulta.']);
            }
            if (! $this->dateAllowed($class, $date)) {
                throw ValidationException::withMessages(['data' => 'Escolha uma data dentro do ano letivo, até hoje.']);
            }
            $record = LessonRecord::query()->where('school_class_id', $class->id)
                ->where('lesson_date', $date->toDateString())->where('curricular_component_id', $component)
                ->lockForUpdate()->first();
            if ($component === null && ! $this->polivalent($class) && $record === null) {
                throw ValidationException::withMessages(['componente' => 'Selecione um componente curricular.']);
            }
            if ($component !== null && ! CurricularComponent::query()->whereKey($component)
                ->when($record === null, fn ($q) => $q->where('active', true))->exists()) {
                throw ValidationException::withMessages(['componente' => 'Selecione um componente válido.']);
            }
            if (($record?->version ?? 0) !== (int) $data['versao']) {
                throw ValidationException::withMessages(['versao' => 'O conteúdo mudou desde a abertura da tela. Confira a versão atual antes de editar.']);
            }

            $new = $record === null;
            $record ??= new LessonRecord([
                'id' => Str::uuid()->toString(), 'school_class_id' => $class->id,
                'lesson_date' => $date->toDateString(), 'curricular_component_id' => $component,
                'recorded_by' => $actor, 'version' => 0,
            ]);
            $before = $new ? null : ['version' => $record->version];
            $record->fill(['content' => $data['conteudo'], 'activity' => $data['atividade'] ?? null,
                'bncc_codes' => $data['bncc_codes']]);
            if (! $new && ! $record->isDirty(['content', 'activity', 'bncc_codes'])) {
                return $record;
            }
            $changed = array_keys($record->getDirty());
            $record->fill([
                'updated_by' => $actor, 'correction_reason' => $data['motivo'] ?? null,
                'version' => $record->version + 1, 'operation_id' => null,
            ]);
            $record->save();
            app(AuditTrail::class)->record('tenant', $new ? 'lesson_record.created' : 'lesson_record.updated',
                LessonRecord::class, $record->id, $before, [
                    'version' => $record->version,
                    'changed_fields' => implode(',', array_unique([...$changed, 'updated_by', 'correction_reason', 'version', 'operation_id'])),
                ]);

            return $record;
        });
    }
}
