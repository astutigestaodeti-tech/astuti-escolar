<?php

namespace App\Calendar;

use Illuminate\Support\Carbon;

/**
 * Feriados nacionais (Lei 662/1949, Lei 6.802/1980, Lei 10.607/2002 e
 * Lei 14.759/2023) e os pontos facultativos em que as redes costumam não
 * ter aula (Carnaval e Corpus Christi). As datas móveis saem da Páscoa.
 * Feriados estaduais e municipais a escola lança à mão.
 */
final class BrazilianHolidays
{
    /** @return array<string, array{kind: string, description: string}> data Y-m-d => lançamento */
    public static function forYear(int $year): array
    {
        $easter = self::easter($year);
        $days = [
            sprintf('%d-01-01', $year) => ['holiday', 'Confraternização Universal'],
            $easter->copy()->subDays(48)->toDateString() => ['non_school_day', 'Carnaval (ponto facultativo)'],
            $easter->copy()->subDays(47)->toDateString() => ['non_school_day', 'Carnaval (ponto facultativo)'],
            $easter->copy()->subDays(2)->toDateString() => ['holiday', 'Sexta-feira Santa'],
            sprintf('%d-04-21', $year) => ['holiday', 'Tiradentes'],
            sprintf('%d-05-01', $year) => ['holiday', 'Dia do Trabalho'],
            $easter->copy()->addDays(60)->toDateString() => ['non_school_day', 'Corpus Christi (ponto facultativo)'],
            sprintf('%d-09-07', $year) => ['holiday', 'Independência do Brasil'],
            sprintf('%d-10-12', $year) => ['holiday', 'Nossa Senhora Aparecida'],
            sprintf('%d-11-02', $year) => ['holiday', 'Finados'],
            sprintf('%d-11-15', $year) => ['holiday', 'Proclamação da República'],
            sprintf('%d-11-20', $year) => ['holiday', 'Dia Nacional de Zumbi e da Consciência Negra'],
            sprintf('%d-12-25', $year) => ['holiday', 'Natal'],
        ];
        ksort($days);

        return array_map(fn (array $day): array => ['kind' => $day[0], 'description' => $day[1]], $days);
    }

    /** Domingo de Páscoa pelo algoritmo de Meeus/Jones/Butcher (calendário gregoriano). */
    public static function easter(int $year): Carbon
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::create($year, $month, $day)->startOfDay();
    }
}
