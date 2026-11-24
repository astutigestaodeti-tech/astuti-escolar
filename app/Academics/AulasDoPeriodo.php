<?php

namespace App\Academics;

use App\Models\Tenant\AcademicPeriod;
use App\Models\Tenant\SchoolClassCensus;
use App\Models\Tenant\SchoolYear;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class AulasDoPeriodo
{
    public function calcular(SchoolYear $year, AcademicPeriod $period, Collection $calendar, Collection $sessions, ?SchoolClassCensus $census, Carbon $hoje): array
    {
        $dias = [];
        foreach (SchoolClassCensus::DAYS as $day => $label) {
            if (filled($census?->{'schedule_'.$day})) {
                $dias[] = $day;
            }
        }
        $porData = $calendar->groupBy(fn ($entry) => $entry->date->toDateString());
        $datas = [];
        if ($year->start_date !== null && $year->end_date !== null) {
            $inicio = $period->starts_on->copy()->max($year->start_date);
            $fim = $period->ends_on->copy()->min($year->end_date);
            for ($data = $inicio->copy(); $data->lte($fim); $data->addDay()) {
                $chave = $data->toDateString();
                $kinds = $porData->get($chave, collect())->pluck('kind');
                if ($kinds->intersect(['holiday', 'recess', 'non_school_day'])->isNotEmpty()) {
                    continue;
                }
                $regular = $dias === [] ? $data->isWeekday() : in_array(strtolower($data->englishDayOfWeek), $dias, true);
                if ($regular || $kinds->contains('extra_school_day')) {
                    $datas[] = $chave;
                }
            }
        }
        $registradas = $sessions->filter(fn ($s) => $s->attendance_date->betweenIncluded($period->starts_on, $period->ends_on) && $s->attendance_date->lte($hoje));
        $comChamada = $registradas->map(fn ($s) => $s->attendance_date->toDateString())->unique()->all();
        // Hoje ainda pode receber chamada, como no calendário escolar.
        $vencidas = array_filter($datas, fn ($data) => $data < $hoje->toDateString());
        $faltantes = array_values(array_diff($vencidas, $comChamada));
        $situacao = $faltantes !== [] ? 'atrasada' : ($period->ends_on->lt($hoje) ? 'concluída' : 'em dia');

        return ['previstas' => count($datas), 'dadas' => $registradas->count(), 'faltantes' => count($faltantes),
            'fora_calendario' => count(array_diff($comChamada, $datas)), 'situacao' => $situacao];
    }
}
