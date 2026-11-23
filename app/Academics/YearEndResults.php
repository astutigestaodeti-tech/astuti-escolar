<?php

namespace App\Academics;

use App\Models\Tenant\AcademicPeriod;
use App\Models\Tenant\ClassYearClosure;
use App\Models\Tenant\CurricularComponent;
use App\Models\Tenant\GradeEntry;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\StudentFinalResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class YearEndResults
{
    public const EXIT_LABELS = [
        'transferred' => 'Transferido',
        'left_network' => 'Transferido para fora da rede',
        'abandoned' => 'Abandono',
        'completed' => 'Matrícula concluída',
        'deceased' => 'Falecimento',
    ];

    /**
     * Cálculo em lote. Mesma média do boletim: só notas lançadas compõem a
     * média, nunca se substitui período vazio por nota zero.
     * A decisão é apenas sugestão: nenhuma gravação acontece aqui.
     *
     * @return array{periods: Collection, components: Collection, records: Collection, closure: ClassYearClosure, minimum: float, attendanceMinimum: float, allPeriodsClosed: bool}
     */
    public function forClass(SchoolClass $class): array
    {
        $periods = AcademicPeriod::query()->where('school_year_id', $class->school_year_id)
            ->orderBy('number')->get();

        $enrollments = StudentEnrollment::query()
            ->where('school_id', $class->school_id)
            ->where('school_year_id', $class->school_year_id)
            ->where('school_class_id', $class->id)
            ->whereIn('status', ['active', ...array_keys(self::EXIT_LABELS)])
            ->whereHas('student', fn ($q) => $q->whereNull('deleted_at'))
            ->with('student:id,full_name')
            ->get()
            ->sortBy(fn (StudentEnrollment $item): string => Str::lower(Str::ascii($item->student->full_name)).'|'.$item->id)
            ->values();

        $ids = $enrollments->pluck('id');
        $studentIds = $enrollments->pluck('student_id');
        $grades = $ids->isEmpty() ? collect() : GradeEntry::query()
            ->where('school_class_id', $class->id)
            ->whereIn('student_enrollment_id', $ids)
            ->whereIn('academic_period_id', $periods->pluck('id'))
            ->get();
        $usedComponentIds = $grades->pluck('curricular_component_id')->unique();

        $components = CurricularComponent::query()
            ->where(fn ($q) => $q->where('active', true)->orWhereIn('id', $usedComponentIds))
            ->orderBy('display_order')->orderBy('name')->get();

        $byEnrollment = $grades->groupBy('student_enrollment_id');
        $year = $class->schoolYear;
        $marks = $studentIds->isEmpty() ? collect() : DB::connection('tenant')
            ->table('attendance_marks as mark')
            ->join('attendance_sessions as session', 'session.id', '=', 'mark.attendance_session_id')
            ->join('school_classes as lesson', 'lesson.id', '=', 'session.school_class_id')
            ->where('lesson.school_id', $class->school_id)
            ->where('lesson.school_year_id', $class->school_year_id)
            ->whereIn('mark.student_id', $studentIds)
            ->whereBetween('session.attendance_date', [
                $year->start_date->toDateString(),
                min(today()->toDateString(), $year->end_date->toDateString()),
            ])
            ->groupBy('mark.student_id')
            ->select('mark.student_id')
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when mark.status = 'present' then 1 else 0 end) as present")
            ->get()->keyBy('student_id');

        $confirmed = $ids->isEmpty() ? collect() : StudentFinalResult::query()
            ->where('school_class_id', $class->id)
            ->whereIn('student_enrollment_id', $ids)->get()->keyBy('student_enrollment_id');

        $minGrade = max(0, min(10, (float) config('academics.minimum_grade', 6)));
        $minAttendance = max(0, min(100, (float) config('academics.minimum_attendance', 75)));

        $records = $enrollments->map(function (StudentEnrollment $enrollment) use (
            $byEnrollment, $components, $marks, $minGrade, $minAttendance, $confirmed
        ): array {
            $entries = $byEnrollment->get($enrollment->id, collect())->groupBy('curricular_component_id');
            $averages = [];
            $below = [];
            $pending = [];

            foreach ($components as $component) {
                $values = $entries->get($component->id, collect())
                    ->filter(fn (GradeEntry $grade): bool => $grade->grade !== null)
                    ->pluck('grade')->map(fn ($grade): float => (float) $grade);
                $average = $values->isEmpty() ? null : round($values->sum() / $values->count(), 1);
                $averages[$component->id] = $average;

                if ($average !== null && $average < $minGrade) {
                    $below[] = $component->name;
                } elseif ($average === null && ! $component->is_optional && $component->active) {
                    $pending[] = $component->name;
                }
            }

            $attendance = $marks->get($enrollment->student_id);
            $total = (int) ($attendance?->total ?? 0);
            $frequency = $total > 0 ? round(100 * (int) $attendance->present / $total, 1) : null;

            $reasons = [];
            if ($below !== []) {
                $reasons[] = 'Abaixo da média: '.implode(', ', $below);
            }
            if ($frequency !== null && $frequency < $minAttendance) {
                $reasons[] = 'Frequência '.number_format($frequency, 1, ',', '.').'% inferior a '.number_format($minAttendance, 1, ',', '.').'%';
            }

            if ($enrollment->status !== 'active') {
                $suggestion = null;
                $reasons = ['Situação da matrícula preservada: '.self::EXIT_LABELS[$enrollment->status]];
            } elseif ($reasons !== []) {
                $suggestion = 'retido';
            } elseif ($pending !== [] || $frequency === null) {
                $suggestion = 'cursando';
                if ($pending !== []) {
                    $reasons[] = 'Sem nota em: '.implode(', ', $pending);
                }
                if ($frequency === null) {
                    $reasons[] = 'Sem frequência registrada';
                }
            } else {
                $suggestion = 'aprovado';
                $reasons[] = 'Médias e frequência atingiram os limites de referência';
            }

            return [
                'enrollment' => $enrollment,
                'averages' => $averages,
                'frequency' => $frequency,
                'suggestion' => $suggestion,
                'reason' => implode('. ', $reasons),
                'confirmed' => $confirmed->get($enrollment->id),
                'exitLabel' => self::EXIT_LABELS[$enrollment->status] ?? null,
            ];
        });

        $periodCount = $periods->count();
        $closure = ClassYearClosure::query()->findOrFail($class->id);

        return [
            'periods' => $periods,
            'components' => $components,
            'records' => $records,
            'closure' => $closure,
            'minimum' => $minGrade,
            'attendanceMinimum' => $minAttendance,
            'allPeriodsClosed' => $periodCount === ($periods->first()?->kind === 'trimestre' ? 3 : 4)
                && $periods->every(fn (AcademicPeriod $period): bool => $period->closed_at !== null),
        ];
    }
}
