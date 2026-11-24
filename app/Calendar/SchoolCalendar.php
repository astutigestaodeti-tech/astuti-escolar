<?php

namespace App\Calendar;

use App\Models\Tenant\SchoolCalendarDay;
use App\Models\Tenant\SchoolYear;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SchoolCalendar
{
    private const NON_SCHOOL_KINDS = ['holiday', 'recess', 'non_school_day'];

    /** @return Collection<int, SchoolCalendarDay> */
    public function entries(string $schoolYearId, string $schoolId): Collection
    {
        return SchoolCalendarDay::query()
            ->where('school_year_id', $schoolYearId)
            ->where(function ($query) use ($schoolId): void {
                $query->whereNull('school_id')->orWhere('school_id', $schoolId);
            })
            ->orderBy('date')->orderBy('school_id')->get();
    }

    /**
     * Dias úteis (seg-sex), removidas as interrupções, mais sábados/domingos
     * explicitamente cadastrados como letivos. Uma interrupção prevalece sobre
     * uma marcação extra no mesmo dia; eventos são apenas informativos.
     *
     * @param  Collection<int, SchoolCalendarDay>  $entries
     */
    public function plannedDays(SchoolYear $year, Collection $entries): int
    {
        if ($year->start_date === null || $year->end_date === null) {
            return 0;
        }

        $kindsByDate = $entries->groupBy(fn (SchoolCalendarDay $day): string => $day->date->toDateString());
        $date = $year->start_date->copy()->startOfDay();
        $end = $year->end_date->copy()->startOfDay();
        $count = 0;

        while ($date->lte($end)) {
            $kinds = $kindsByDate->get($date->toDateString(), collect())->pluck('kind');
            $interrupted = $kinds->intersect(self::NON_SCHOOL_KINDS)->isNotEmpty();
            if ($interrupted === false && ($date->isWeekday() || $kinds->contains('extra_school_day'))) {
                $count++;
            }
            $date->addDay();
        }

        return $count;
    }

    /** Último dia letivo já transcorrido; hoje ainda pode receber chamada. */
    public function lastSchoolDay(SchoolYear $year, Collection $entries, Carbon $day): ?Carbon
    {
        if ($year->start_date === null || $year->end_date === null) {
            return null;
        }

        $kindsByDate = $entries->groupBy(fn (SchoolCalendarDay $entry): string => $entry->date->toDateString());
        $date = $day->copy()->startOfDay()->subDay()->min($year->end_date->copy());
        while ($date->gte($year->start_date)) {
            $kinds = $kindsByDate->get($date->toDateString(), collect())->pluck('kind');
            if ($kinds->intersect(self::NON_SCHOOL_KINDS)->isEmpty() && ($date->isWeekday() || $kinds->contains('extra_school_day'))) {
                return $date;
            }
            $date->subDay();
        }

        return null;
    }

    /** Retorna a interrupção explícita aplicável à chamada, sem impedir o registro. */
    public function nonSchoolNotice(string $schoolYearId, string $schoolId, Carbon $date): ?string
    {
        $entry = SchoolCalendarDay::query()
            ->where('school_year_id', $schoolYearId)
            ->whereDate('date', $date->toDateString())
            ->whereIn('kind', self::NON_SCHOOL_KINDS)
            ->where(function ($query) use ($schoolId): void {
                $query->whereNull('school_id')->orWhere('school_id', $schoolId);
            })
            ->orderByRaw('school_id IS NOT NULL')
            ->first();

        if ($entry === null) {
            return null;
        }

        return mb_strtolower(SchoolCalendarDay::KINDS[$entry->kind]).': '.$entry->description;
    }
}
