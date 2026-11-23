<?php

namespace App\Attendance;

use App\Models\Tenant\AttendanceMark;
use App\Models\Tenant\AttendanceSession;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\StudentEnrollment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MonthlyAttendanceReport
{
    private const SYMBOLS = [
        'present' => '•',
        'absent' => 'F',
        'justified' => 'J',
    ];

    /**
     * Três consultas para a grade, independentemente do número de estudantes:
     * datas das chamadas, matrículas e nomes juntos, marcações sem observações.
     *
     * @return array{sessions: Collection, students: Collection, dailyTotals: array<string, int>}
     */
    public function forClass(SchoolClass $class, Carbon $month): array
    {
        $year = $class->schoolYear;
        $from = max($month->copy()->startOfMonth()->toDateString(), $year->start_date->toDateString());
        $until = min($month->copy()->endOfMonth()->toDateString(), $year->end_date->toDateString());

        $sessions = AttendanceSession::query()
            ->where('school_class_id', $class->id)
            ->whereBetween('attendance_date', [$from, $until])
            ->orderBy('attendance_date')
            ->get(['id', 'attendance_date']);

        // Incluir quem estava matriculado no mês, inclusive quem foi transferido.
        // A junção traz o nome sem provocar uma consulta por aluno.
        $enrollments = StudentEnrollment::query()
            ->join('students as aluno', 'aluno.id', '=', 'student_enrollments.student_id')
            ->where('student_enrollments.school_id', $class->school_id)
            ->where('student_enrollments.school_year_id', $class->school_year_id)
            ->where('student_enrollments.school_class_id', $class->id)
            ->whereIn('student_enrollments.status', ['active', 'transferred', 'ended'])
            ->whereNull('aluno.deleted_at')
            ->where(function (Builder $query) use ($until): void {
                $query->whereNull('student_enrollments.started_at')
                    ->orWhereDate('student_enrollments.started_at', '<=', $until);
            })
            ->where(function (Builder $query) use ($from): void {
                $query->whereNull('student_enrollments.ended_at')
                    ->orWhereDate('student_enrollments.ended_at', '>=', $from);
            })
            ->get([
                'student_enrollments.student_id',
                'student_enrollments.status',
                'student_enrollments.started_at',
                'student_enrollments.ended_at',
                'aluno.full_name',
            ]);

        $studentIds = $enrollments->pluck('student_id')->unique()->values();
        $marks = $sessions->isEmpty() || $studentIds->isEmpty()
            ? collect()
            : AttendanceMark::query()
                ->whereIn('attendance_session_id', $sessions->pluck('id'))
                ->whereIn('student_id', $studentIds)
                ->get(['attendance_session_id', 'student_id', 'status'])
                ->groupBy('student_id')
                ->map(fn (Collection $items) => $items->keyBy('attendance_session_id'));

        $dailyTotals = $sessions->mapWithKeys(fn (AttendanceSession $session): array => [$session->id => 0])->all();

        $students = $enrollments->groupBy('student_id')
            ->map(function (Collection $links, string $studentId) use ($sessions, $marks, $month, &$dailyTotals): array {
                $first = $links->first();
                $studentMarks = $marks->get($studentId, collect());
                $totals = ['present' => 0, 'absent' => 0, 'justified' => 0];
                $symbols = [];
                $outside = [];

                foreach ($sessions as $session) {
                    $date = $session->attendance_date->toDateString();
                    $active = $links->contains(fn (StudentEnrollment $link): bool => (
                        ($link->started_at === null || $link->started_at->toDateString() <= $date)
                        && ($link->ended_at === null || $link->ended_at->toDateString() >= $date)
                    ));
                    $outside[$session->id] = ! $active;
                    $status = $active ? $studentMarks->get($session->id)?->status : null;
                    if ($status !== null && isset(self::SYMBOLS[$status])) {
                        $totals[$status]++;
                        $dailyTotals[$session->id]++;
                    }
                    $symbols[$session->id] = self::SYMBOLS[$status] ?? '';
                }

                $recorded = array_sum($totals);
                $transfer = $links->first(fn (StudentEnrollment $link): bool => $link->status === 'transferred'
                    && $link->ended_at !== null && $link->ended_at->format('Y-m') === $month->format('Y-m'));

                return [
                    'id' => $studentId,
                    'name' => $first->full_name,
                    'marks' => $symbols,
                    'outside' => $outside,
                    'totals' => $totals,
                    'percentage' => $recorded > 0 ? (int) round($totals['present'] * 100 / $recorded) : null,
                    'transferredAt' => $transfer?->ended_at,
                ];
            })
            ->sortBy(fn (array $student): string => Str::lower(Str::ascii($student['name'])).'|'.$student['id'])
            ->values();

        return compact('sessions', 'students', 'dailyTotals');
    }
}
