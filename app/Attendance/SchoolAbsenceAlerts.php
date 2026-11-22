<?php

namespace App\Attendance;

use App\Models\Tenant\School;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\StudentEnrollment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Lista de triagem escolar: apenas matriculas ativas de alunos ativos.
 * Nenhuma observacao da chamada ou dado medico e consultado.
 */
class SchoolAbsenceAlerts
{
    public function __construct(private readonly AttendanceSummary $summary) {}

    public function currentYear(): ?SchoolYear
    {
        return SchoolYear::query()->where('status', '!=', 'completed')->orderByDesc('year')->first();
    }

    private function activeEnrollments(School $school, SchoolYear $year): Builder
    {
        return StudentEnrollment::query()
            ->where('school_id', $school->id)
            ->where('school_year_id', $year->id)
            ->where('status', 'active')
            ->whereNotNull('school_class_id')
            ->whereHas('student', fn (Builder $query) => $query->whereNull('deleted_at'))
            ->whereHas('schoolClass', fn (Builder $query) => $query
                ->where('school_id', $school->id)
                ->where('school_year_id', $year->id)
                ->where('status', 'active'));
    }

    public function alertCount(School $school, ?SchoolYear $year, Carbon $day): int
    {
        if ($year === null) {
            return 0;
        }

        $studentIds = $this->activeEnrollments($school, $year)->distinct()->pluck('student_id');
        $summaries = $this->summary->alertsForSchool($school, $year, $studentIds, $day);

        return collect($summaries)->filter(fn (array $item): bool => $item['reasons'] !== [])->count();
    }

    /**
     * @return Collection<int, array{student: mixed, schoolClass: mixed, guardian: mixed, month_percentage: ?int, year_percentage: ?int, consecutive_absences: int, last_presence: ?string, justified_year: int, reasons: list<string>}>
     */
    public function alertedStudents(School $school, ?SchoolYear $year, Carbon $day): Collection
    {
        if ($year === null) {
            return collect();
        }

        $enrollments = $this->activeEnrollments($school, $year)
            ->with(['student.primaryGuardianLink.guardian', 'schoolClass'])
            ->get()
            ->unique('student_id')
            ->values();

        $summaries = $this->summary->alertsForSchool($school, $year, $enrollments->pluck('student_id'), $day);

        return $enrollments
            ->map(function (StudentEnrollment $enrollment) use ($summaries): ?array {
                $data = $summaries[$enrollment->student_id] ?? null;
                if ($data === null || $data['reasons'] === []) {
                    return null;
                }

                return [
                    'student' => $enrollment->student,
                    'schoolClass' => $enrollment->schoolClass,
                    'guardian' => $enrollment->student?->primaryGuardianLink?->guardian,
                    ...$data,
                ];
            })
            ->filter()
            ->sort(function (array $a, array $b): int {
                // Mais faltas consecutivas primeiro; depois menor frequencia.
                $severity = $b['consecutive_absences'] <=> $a['consecutive_absences'];
                if ($severity !== 0) {
                    return $severity;
                }

                $worstA = min($a['month_percentage'] ?? 101, $a['year_percentage'] ?? 101);
                $worstB = min($b['month_percentage'] ?? 101, $b['year_percentage'] ?? 101);

                return ($worstA <=> $worstB)
                    ?: strcmp(Str::lower(Str::ascii((string) $a['student']->full_name)), Str::lower(Str::ascii((string) $b['student']->full_name)));
            })
            ->values();
    }
}
