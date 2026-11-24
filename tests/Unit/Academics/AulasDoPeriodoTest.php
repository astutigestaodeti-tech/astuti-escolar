<?php

namespace Tests\Unit\Academics;

use App\Academics\AulasDoPeriodo;
use App\Models\Tenant\AcademicPeriod;
use App\Models\Tenant\AttendanceSession;
use App\Models\Tenant\SchoolCalendarDay;
use App\Models\Tenant\SchoolClassCensus;
use App\Models\Tenant\SchoolYear;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AulasDoPeriodoTest extends TestCase
{
    public function test_calendario_horario_reposicao_e_chamadas_vencidas(): void
    {
        $year = (new SchoolYear)->setDateFormat('Y-m-d H:i:s')->fill(['year' => 2026, 'start_date' => '2026-02-02', 'end_date' => '2026-12-18']);
        $period = (new AcademicPeriod)->setDateFormat('Y-m-d H:i:s')->fill(['starts_on' => '2026-02-02', 'ends_on' => '2026-02-07']);
        $calendar = collect([
            (new SchoolCalendarDay)->setDateFormat('Y-m-d H:i:s')->fill(['date' => '2026-02-03', 'kind' => 'holiday']),
            (new SchoolCalendarDay)->setDateFormat('Y-m-d H:i:s')->fill(['date' => '2026-02-04', 'kind' => 'recess']),
            (new SchoolCalendarDay)->setDateFormat('Y-m-d H:i:s')->fill(['date' => '2026-02-05', 'kind' => 'non_school_day']),
            (new SchoolCalendarDay)->setDateFormat('Y-m-d H:i:s')->fill(['date' => '2026-02-07', 'kind' => 'extra_school_day']),
            (new SchoolCalendarDay)->setDateFormat('Y-m-d H:i:s')->fill(['date' => '2026-02-06', 'kind' => 'event']),
            (new SchoolCalendarDay)->setDateFormat('Y-m-d H:i:s')->fill(['date' => '2026-02-03', 'kind' => 'extra_school_day']),
        ]);
        $sessions = collect([(new AttendanceSession)->setDateFormat('Y-m-d H:i:s')->fill(['attendance_date' => '2026-02-02'])]);
        $service = new AulasDoPeriodo;
        $r = $service->calcular($year, $period, $calendar, $sessions, null, Carbon::parse('2026-02-07'));
        $this->assertSame(['previstas' => 3, 'dadas' => 1, 'faltantes' => 1, 'fora_calendario' => 0, 'situacao' => 'atrasada'], $r);

        $census = new SchoolClassCensus(['schedule_monday' => '07:00-11:00']);
        $r = $service->calcular($year, $period, $calendar, $sessions, $census, Carbon::parse('2026-02-07'));
        $this->assertSame(2, $r['previstas']);
        $this->assertSame('em dia', $r['situacao']);
        $sessions->push((new AttendanceSession)->setDateFormat('Y-m-d H:i:s')->fill(['attendance_date' => '2026-02-07']));
        $r = $service->calcular($year, $period, $calendar, $sessions, $census, Carbon::parse('2026-02-08'));
        $this->assertSame('concluída', $r['situacao']);
        $this->assertSame(2, $r['dadas']);
    }
}
