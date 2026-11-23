<?php

namespace App\Integral;

use App\Census\CensusReadiness;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\StudentEnrollment;
use Illuminate\Support\Collection;

/**
 * Retrato operacional: não substitui pactuação homologada nem prova de
 * criação de vaga no programa federal.
 *
 * Consultas em lote: escolas, turmas, matrículas, alunos e ano anterior.
 */
class FullTimeReadiness
{
    public const STAGES = [
        'creche' => 'Creche',
        'pre_escola' => 'Pré-escola',
        'fundamental_iniciais' => 'Ensino Fundamental - anos iniciais',
        'fundamental_finais' => 'Ensino Fundamental - anos finais',
    ];

    public static function integral(SchoolClass $class): bool
    {
        return $class->daily_hours !== null
            ? (float) $class->daily_hours >= 7
            : CensusReadiness::shift((string) $class->shift) === 'integral';
    }

    public static function mismatch(SchoolClass $class): bool
    {
        if ($class->daily_hours === null) {
            return false;
        }

        $shift = CensusReadiness::shift((string) $class->shift);

        return $shift !== null && ($shift === 'integral') !== ((float) $class->daily_hours >= 7);
    }

    public static function stage(SchoolClass $class): ?string
    {
        $stage = CensusReadiness::stage($class->stage.' '.$class->name, null);

        return array_key_exists((string) $stage, self::STAGES) ? $stage : null;
    }

    /**
     * @return array{rows: Collection, classes: Collection, byStage: array, bySchool: Collection, totals: array, baselineYear: ?int}
     */
    public function forYear(SchoolYear $year, ?string $schoolId = null): array
    {
        $previous = SchoolYear::query()->where('year', $year->year - 1)
            ->first(['id', 'year', 'start_date', 'end_date']);

        $schools = School::query()->where('status', 'active')
            ->when($schoolId, fn ($q, string $id) => $q->where('id', $id))
            ->orderBy('name')->get(['id', 'name']);

        // O histórico anterior deve abranger a rede inteira para que uma
        // transferência entre escolas não pareça criação de vaga.
        $allClasses = SchoolClass::query()
            ->whereIn('school_year_id', array_filter([$year->id, $previous?->id]))
            ->get(['id', 'school_id', 'school_year_id', 'name', 'stage', 'shift', 'daily_hours', 'status']);
        $classesById = $allClasses->keyBy('id');
        $schoolNames = $schools->pluck('name', 'id');

        $enrollments = StudentEnrollment::query()
            ->whereIn('school_year_id', array_filter([$year->id, $previous?->id]))
            ->whereIn('status', ['active', 'completed'])
            ->with(['student:id,full_name,deleted_at'])
            ->get(['id', 'student_id', 'school_id', 'school_class_id', 'school_year_id', 'status']);

        $priorIntegral = $enrollments
            ->filter(function (StudentEnrollment $item) use ($previous, $classesById): bool {
                if ($previous === null || $item->school_year_id !== $previous->id) {
                    return false;
                }
                $class = $classesById->get($item->school_class_id);

                return $class !== null && self::integral($class);
            })
            ->pluck('student_id')->flip();

        $seen = [];
        $rows = $enrollments
            ->filter(function (StudentEnrollment $item) use ($year, $schoolNames, $classesById): bool {
                $class = $classesById->get($item->school_class_id);

                return $item->school_year_id === $year->id
                    && $item->status === 'active'
                    && $item->student !== null
                    && $item->student->deleted_at === null
                    && $schoolNames->has($item->school_id)
                    && $class !== null
                    && $class->school_id === $item->school_id
                    && self::integral($class);
            })
            ->map(function (StudentEnrollment $item) use ($classesById, $schoolNames, $priorIntegral): array {
                $class = $classesById->get($item->school_class_id);

                return [
                    'student_id' => $item->student_id,
                    'student' => $item->student->full_name,
                    'school_id' => $item->school_id,
                    'school' => $schoolNames->get($item->school_id),
                    'class_id' => $class->id,
                    'class' => $class->name,
                    'stage' => self::stage($class),
                    'new' => ! $priorIntegral->has($item->student_id),
                    'verified' => $class->daily_hours !== null,
                ];
            })
            ->filter(function (array $row) use (&$seen): bool {
                if (isset($seen[$row['student_id']])) {
                    return false;
                }
                $seen[$row['student_id']] = true;

                return true;
            })
            ->sortBy('student')->values();

        $byStage = [];
        foreach (self::STAGES as $stage => $label) {
            $items = $rows->where('stage', $stage);
            $byStage[$stage] = [
                'label' => $label,
                'current' => $items->count(),
                'new' => $items->where('new', true)->count(),
            ];
        }

        $bySchool = $schools->map(function (School $school) use ($rows): array {
            $items = $rows->where('school_id', $school->id);

            return [
                'id' => $school->id,
                'name' => $school->name,
                'current' => $items->count(),
                'new' => $items->where('new', true)->count(),
            ];
        });

        $integralClasses = $allClasses->filter(fn (SchoolClass $class): bool =>
            $class->school_year_id === $year->id
            && $schoolNames->has($class->school_id)
            && $class->status === 'active'
            && self::integral($class)
        )->map(fn (SchoolClass $class): array => [
            'id' => $class->id,
            'name' => $class->name,
            'school' => $schoolNames->get($class->school_id),
            'school_id' => $class->school_id,
            'hours' => $class->daily_hours,
            'inferred' => $class->daily_hours === null,
            'mismatch' => self::mismatch($class),
        ])->values();

        return [
            'rows' => $rows,
            'classes' => $integralClasses,
            'byStage' => $byStage,
            'bySchool' => $bySchool,
            'totals' => [
                'current' => $rows->count(),
                'new' => $rows->where('new', true)->count(),
                'inferred' => $rows->where('verified', false)->count(),
                'without_stage' => $rows->whereNull('stage')->count(),
            ],
            'baselineYear' => $previous?->year,
        ];
    }
}
