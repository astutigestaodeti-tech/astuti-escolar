<?php

namespace App\Attendance;

use App\Models\Tenant\AttendanceMark;
use App\Models\Tenant\AttendanceSession;
use App\Models\Tenant\SchoolClass;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Percentual mensal e alertas por aluno. Três consultas por turma,
 * independentemente da quantidade de estudantes.
 *
 * Faltas justificadas também são ausências para o percentual. Aluno que
 * entrou depois não é punido por sessões sem marca dele.
 */
class AttendanceSummary
{
    /**
     * @param  Collection<int, string>  $studentIds
     * @return array<string, array{percentage: ?int, sessions: int, present: int, alert: ?string}>
     */
    public function forStudents(SchoolClass $class, Collection $studentIds, Carbon $day): array
    {
        $studentIds = $studentIds->filter()->unique()->values();
        if ($studentIds->isEmpty()) {
            return [];
        }

        $base = AttendanceSession::query()
            ->where('school_class_id', $class->id)
            ->whereDate('attendance_date', '<=', $day->toDateString());

        $monthSessions = (clone $base)
            ->whereDate('attendance_date', '>=', $day->copy()->startOfMonth()->toDateString())
            ->orderBy('attendance_date')
            ->get(['id', 'attendance_date']);
        $recentSessions = (clone $base)
            ->orderByDesc('attendance_date')
            ->limit(3)
            ->get(['id', 'attendance_date']);

        $sessionIds = $monthSessions->pluck('id')->merge($recentSessions->pluck('id'))->unique();
        $marks = $sessionIds->isEmpty()
            ? collect()
            : AttendanceMark::query()
                ->whereIn('attendance_session_id', $sessionIds)
                ->whereIn('student_id', $studentIds)
                ->get(['attendance_session_id', 'student_id', 'status'])
                ->groupBy('student_id');

        $summary = [];
        foreach ($studentIds as $id) {
            $bySession = ($marks->get($id) ?? collect())->keyBy('attendance_session_id');
            $monthMarks = $monthSessions->map(fn (AttendanceSession $session) => $bySession->get($session->id))
                ->filter()->values();
            $total = $monthMarks->count();
            $present = $monthMarks->where('status', 'present')->count();
            $percentage = $total > 0 ? (int) round(100 * $present / $total) : null;

            $recentMarks = $recentSessions->map(fn (AttendanceSession $session) => $bySession->get($session->id))
                ->filter()->values();
            $threeAbsences = $recentMarks->count() === 3
                && $recentMarks->every(fn (AttendanceMark $mark): bool => in_array($mark->status, ['absent', 'justified'], true));

            $summary[$id] = [
                'percentage' => $percentage,
                'sessions' => $total,
                'present' => $present,
                'alert' => $threeAbsences ? 'Atenção: 3 faltas seguidas' : (
                    $percentage !== null && $percentage < 75 ? 'Abaixo de 75%' : null
                ),
            ];
        }

        return $summary;
    }
}
