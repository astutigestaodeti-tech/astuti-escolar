<?php

namespace App\Attendance;

use App\Models\Tenant\AttendanceMark;
use App\Models\Tenant\AttendanceSession;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

    /**
     * Triagem da busca ativa em uma única consulta agregada para toda a escola.
     *
     * Faltas justificadas não entram nos denominadores e interrompem a sequência
     * de faltas não justificadas. Este indicador não substitui a frequência oficial.
     *
     * @param  Collection<int, string>  $studentIds
     * @return array<string, array{month_percentage: ?int, year_percentage: ?int, consecutive_absences: int, last_presence: ?string, justified_year: int, reasons: list<string>}>
     */
    public function alertsForSchool(School $school, SchoolYear $year, Collection $studentIds, Carbon $day): array
    {
        $studentIds = $studentIds->filter()->unique()->values();
        $end = $day->lessThan($year->end_date) ? $day->toDateString() : $year->end_date->toDateString();
        $start = $year->start_date->toDateString();

        if ($studentIds->isEmpty() || $end < $start) {
            return [];
        }

        $monthStart = max($start, $day->copy()->startOfMonth()->toDateString());

        // Uma subconsulta preserva a última presença/justificativa por aluno,
        // mesmo quando uma matrícula muda de turma durante o mesmo ano.
        $history = DB::connection('tenant')->table('attendance_marks as marca')
            ->join('attendance_sessions as sessao', 'sessao.id', '=', 'marca.attendance_session_id')
            ->join('school_classes as turma', 'turma.id', '=', 'sessao.school_class_id')
            ->where('turma.school_id', $school->id)
            ->where('turma.school_year_id', $year->id)
            ->whereIn('marca.student_id', $studentIds)
            ->whereBetween('sessao.attendance_date', [$start, $end])
            ->select('marca.student_id', 'marca.status', 'sessao.attendance_date')
            ->selectRaw("MAX(CASE WHEN marca.status <> 'absent' THEN sessao.attendance_date END) OVER (PARTITION BY marca.student_id) AS ultima_nao_falta");

        $rows = DB::connection('tenant')->query()->fromSub($history, 'historico')
            ->select('historico.student_id')
            ->selectRaw("SUM(CASE WHEN status = 'present' AND attendance_date >= ? THEN 1 ELSE 0 END) AS presentes_mes", [$monthStart])
            ->selectRaw("SUM(CASE WHEN status IN ('present', 'absent') AND attendance_date >= ? THEN 1 ELSE 0 END) AS total_mes", [$monthStart])
            ->selectRaw("SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS presentes_ano")
            ->selectRaw("SUM(CASE WHEN status IN ('present', 'absent') THEN 1 ELSE 0 END) AS total_ano")
            ->selectRaw("SUM(CASE WHEN status = 'justified' THEN 1 ELSE 0 END) AS justificadas_ano")
            ->selectRaw("SUM(CASE WHEN status = 'absent' AND (ultima_nao_falta IS NULL OR attendance_date > ultima_nao_falta) THEN 1 ELSE 0 END) AS faltas_seguidas")
            ->selectRaw("MAX(CASE WHEN status = 'present' THEN attendance_date END) AS ultima_presenca")
            ->groupBy('historico.student_id')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $month = (int) $row->total_mes > 0 ? (int) round(100 * $row->presentes_mes / $row->total_mes) : null;
            $annual = (int) $row->total_ano > 0 ? (int) round(100 * $row->presentes_ano / $row->total_ano) : null;
            $streak = (int) $row->faltas_seguidas;

            $reasons = [];
            if ($streak >= 3) {
                $reasons[] = 'sequencia';
            }
            if ($month !== null && $month < 75) {
                $reasons[] = 'mes';
            }
            if ($annual !== null && $annual < 75) {
                $reasons[] = 'ano';
            }

            $result[$row->student_id] = [
                'month_percentage' => $month,
                'year_percentage' => $annual,
                'consecutive_absences' => $streak,
                'last_presence' => $row->ultima_presenca,
                'justified_year' => (int) $row->justificadas_ano,
                'reasons' => $reasons,
            ];
        }

        return $result;
    }
}
