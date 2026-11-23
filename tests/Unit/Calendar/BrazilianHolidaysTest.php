<?php

namespace Tests\Unit\Calendar;

use App\Calendar\BrazilianHolidays;
use PHPUnit\Framework\TestCase;

class BrazilianHolidaysTest extends TestCase
{
    public function test_pascoa_e_datas_moveis_conferem_com_o_calendario_oficial(): void
    {
        $this->assertSame('2024-03-31', BrazilianHolidays::easter(2024)->toDateString());
        $this->assertSame('2025-04-20', BrazilianHolidays::easter(2025)->toDateString());
        $this->assertSame('2026-04-05', BrazilianHolidays::easter(2026)->toDateString());
        $this->assertSame('2027-03-28', BrazilianHolidays::easter(2027)->toDateString());

        $days = BrazilianHolidays::forYear(2027);
        $this->assertCount(13, $days);
        $this->assertSame('Carnaval (ponto facultativo)', $days['2027-02-08']['description']);
        $this->assertSame('non_school_day', $days['2027-02-09']['kind']);
        $this->assertSame('Sexta-feira Santa', $days['2027-03-26']['description']);
        $this->assertSame('Corpus Christi (ponto facultativo)', $days['2027-05-27']['description']);
        $this->assertSame('holiday', $days['2027-11-20']['kind']);
    }
}
