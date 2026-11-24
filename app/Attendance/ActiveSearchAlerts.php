<?php

namespace App\Attendance;

use App\Calendar\SchoolCalendar;
use App\Models\Tenant\ActiveSearchParameter;
use App\Models\Tenant\AttendanceSession;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\StudentEnrollment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ActiveSearchAlerts
{
    public function year(): ?SchoolYear
    {
        return SchoolYear::query()->where('start_date', '<=', today())->where('end_date', '>=', today())
            ->orderByDesc('year')->first()
            ?? SchoolYear::query()->where('status', 'active')->orderByDesc('year')->first();
    }

    public function parameters(SchoolYear $year): array
    {
        $saved = ActiveSearchParameter::query()->where('school_year_id', $year->id)->first();

        return ['days' => $saved?->consecutive_days ?? 5, 'percentage' => $saved?->monthly_percentage ?? 75];
    }

    public function enrollments(School $school, SchoolYear $year, ?string $classId = null): Collection
    {
        return StudentEnrollment::query()->where('school_id', $school->id)
            ->where('school_year_id', $year->id)->where('status', 'active')
            ->whereNull('ended_at')->whereNotNull('school_class_id')
            ->when($classId, fn ($query) => $query->where('school_class_id', $classId))
            ->whereHas('student', fn ($query) => $query->whereNull('deleted_at'))
            ->whereHas('schoolClass', fn ($query) => $query->where('school_id', $school->id)
                ->where('school_year_id', $year->id)->where('status', 'active')->whereNull('deleted_at'))
            ->with(['student', 'schoolClass'])->get();
    }

    public function summaries(School $school, SchoolYear $year, Collection $enrollments, Carbon $day): array
    {
        if ($enrollments->isEmpty()) {
            return [];
        }
        $end = min($day->toDateString(), $year->end_date->toDateString());
        $start = $year->start_date->toDateString();
        if ($end < $start) {
            return [];
        }
        $settings = $this->parameters($year);
        $calendar = app(SchoolCalendar::class)->entries($year->id, $school->id)
            ->groupBy(fn ($entry) => $entry->date->toDateString());
        $sessions = AttendanceSession::query()->whereIn('school_class_id', $enrollments->pluck('school_class_id')->unique())
            ->whereBetween('attendance_date', [$start, $end])
            ->with(['marks' => fn ($query) => $query->whereIn('student_id', $enrollments->pluck('student_id')->unique())])
            ->get()->groupBy('school_class_id');
        $monthStart = max($start, Carbon::parse($end)->startOfMonth()->toDateString());
        $result = [];
        foreach ($enrollments as $entry) {
            $marks = ($sessions->get($entry->school_class_id) ?? collect())->mapWithKeys(
                fn ($session) => [$session->attendance_date->toDateString() => $session->marks->firstWhere('student_id', $entry->student_id)?->status]
            );
            // Não apagar um alerta só porque a próxima chamada ainda não foi feita.
            $lastRecorded = $marks->filter()->keys()->sort()->last();
            $entryEnd = min($end, $entry->ended_at?->toDateString() ?? $end, $lastRecorded ?? $start);
            $streak = 0;
            $present = 0;
            $monthCount = 0;
            $absences = 0;
            $justified = 0;
            $entryStart = max($start, $entry->started_at?->toDateString() ?? $start);
            for ($date = Carbon::parse($entryStart); $date->toDateString() <= $entryEnd; $date->addDay()) {
                $key = $date->toDateString();
                $kinds = ($calendar->get($key) ?? collect())->pluck('kind');
                if ($kinds->intersect(['holiday', 'recess', 'non_school_day'])->isNotEmpty()
                    || (! $date->isWeekday() && ! $kinds->contains('extra_school_day'))) {
                    continue;
                }
                $mark = $marks->get($key);
                // Sem chamada não se presume falta nem presença: o dia não conta e
                // não interrompe a sequência. Presença ou justificativa interrompem.
                if ($mark === null) {
                    continue;
                }
                $streak = $mark === 'absent' ? $streak + 1 : 0;
                $absences += $mark === 'absent' ? 1 : 0;
                $justified += $mark === 'justified' ? 1 : 0;
                if ($key >= $monthStart && in_array($mark, ['present', 'absent', 'justified'], true)) {
                    $monthCount++;
                    $present += $mark === 'present' ? 1 : 0;
                }
            }
            $percentage = $monthCount === 0 ? null : round(100 * $present / $monthCount, 2);
            $result[$entry->id] = [
                'streak' => $streak,
                'absences' => $absences,
                'justified' => $justified,
                'monthly' => $percentage,
                'month' => substr($end, 0, 7),
                'alert' => $streak >= $settings['days'] || ($monthCount > 0 && 100 * $present < $settings['percentage'] * $monthCount),
            ];
        }

        return $result;
    }
}
