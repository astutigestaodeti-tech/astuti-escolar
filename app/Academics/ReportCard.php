<?php

namespace App\Academics;

use App\Models\Tenant\AcademicPeriod;
use App\Models\Tenant\CurricularComponent;
use App\Models\Tenant\GradeEntry;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\StudentEnrollment;
use Illuminate\Support\Facades\DB;

class ReportCard
{
    /**
     * Média aritmética apenas das notas efetivamente lançadas, sem zeros para
     * períodos não avaliados. Nunca confundir resultado parcial com aprovação.
     */
    public function build(School $school, SchoolYear $year, StudentEnrollment $enrollment): array
    {
        $periods = AcademicPeriod::query()->where('school_year_id', $year->id)
            ->orderBy('number')->get();

        $entries = GradeEntry::query()->where('student_enrollment_id', $enrollment->id)
            ->where('school_class_id', $enrollment->school_class_id)
            ->whereIn('academic_period_id', $periods->pluck('id'))
            ->get();

        $componentIds = $entries->pluck('curricular_component_id')->unique();
        $components = CurricularComponent::query()
            ->where(function ($query) use ($componentIds): void {
                $query->where('active', true)->orWhereIn('id', $componentIds);
            })
            ->orderBy('display_order')->orderBy('name')->get();

        $gradeMap = $entries->keyBy(fn (GradeEntry $entry): string => $entry->curricular_component_id.'|'.$entry->academic_period_id);
        $minimum = max(0, min(10, (float) config('academics.minimum_grade', 6.0)));

        $rows = $components->map(function (CurricularComponent $component) use ($periods, $gradeMap, $minimum): array {
            $marks = [];
            $values = [];

            foreach ($periods as $period) {
                $entry = $gradeMap->get($component->id.'|'.$period->id);
                $mark = $entry?->grade === null ? null : (float) $entry->grade;
                $marks[$period->id] = $mark;
                if ($mark !== null) {
                    $values[] = $mark;
                }
            }

            $mean = count($values) > 0 ? round(array_sum($values) / count($values), 1) : null;

            return [
                'component' => $component,
                'marks' => $marks,
                'mean' => $mean,
                'below' => $mean !== null && $mean < $minimum,
                'status' => $mean === null ? 'Sem lançamento' : (
                    $mean < $minimum ? 'Abaixo da média' : 'Em acompanhamento'
                ),
            ];
        });

        // A frequência vem da chamada diária existente. Justificadas contam
        // como ausência no percentual, igual ao relatório mensal da escola.
        $frequency = DB::connection('tenant')->table('attendance_marks as marca')
            ->join('attendance_sessions as sessao', 'sessao.id', '=', 'marca.attendance_session_id')
            ->join('school_classes as turma', 'turma.id', '=', 'sessao.school_class_id')
            ->where('marca.student_id', $enrollment->student_id)
            ->where('turma.school_id', $school->id)
            ->where('turma.school_year_id', $year->id)
            ->whereBetween('sessao.attendance_date', [
                $year->start_date->toDateString(),
                min(today()->toDateString(), $year->end_date->toDateString()),
            ])
            ->selectRaw("count(*) as total, sum(case when marca.status = 'present' then 1 else 0 end) as presentes")
            ->first();

        $sessions = (int) ($frequency?->total ?? 0);
        $present = (int) ($frequency?->presentes ?? 0);

        return [
            'periods' => $periods,
            'rows' => $rows,
            'minimum' => $minimum,
            'sessions' => $sessions,
            'present' => $present,
            'frequency' => $sessions > 0 ? round(100 * $present / $sessions, 1) : null,
            'openPeriods' => $periods->filter(fn (AcademicPeriod $period): bool => $period->closed_at === null),
        ];
    }
}
