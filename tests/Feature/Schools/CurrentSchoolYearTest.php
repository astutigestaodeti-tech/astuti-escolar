<?php

namespace Tests\Feature\Schools;

use App\Attendance\SchoolAbsenceAlerts;
use App\Models\Tenant\SchoolYear;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class CurrentSchoolYearTest extends TwoMunicipalityTestCase
{
    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    private function addYear(
        int $year,
        string $status = 'active'
    ): void {
        app(TenantContext::class)->runFor(
            $this->municipioA,
            fn () => SchoolYear::query()->create([
                'id' => (string) Str::uuid(),
                'year' => $year,
                'start_date' => "{$year}-02-08",
                'end_date' => "{$year}-12-17",
                'status' => $status,
            ])
        );
    }

    private function selectedYear(string $date): ?int
    {
        $this->travelTo(
            Carbon::parse($date.' 12:00:00')
        );

        return app(TenantContext::class)->runFor(
            $this->municipioA,
            fn () => app(SchoolAbsenceAlerts::class)
                ->currentYear()?->year
        );
    }

    public function test_ano_vigente_prevalece_sobre_ano_futuro(): void
    {
        $this->addYear(2026);
        $this->addYear(2027);

        $this->assertSame(
            2026,
            $this->selectedYear('2026-11-25')
        );
    }

    public function test_primeiro_dia_do_ano_letivo(): void
    {
        $this->addYear(2026);
        $this->addYear(2027);

        $this->assertSame(
            2027,
            $this->selectedYear('2027-02-08')
        );
    }

    public function test_ultimo_dia_do_ano_letivo(): void
    {
        $this->addYear(2026);
        $this->addYear(2027);

        $this->assertSame(
            2026,
            $this->selectedYear('2026-12-17')
        );
    }

    public function test_intervalo_entre_anos(): void
    {
        $this->addYear(2026);
        $this->addYear(2027);

        $this->assertSame(
            2026,
            $this->selectedYear('2027-01-15')
        );
    }

    public function test_ano_futuro_mais_proximo(): void
    {
        $this->addYear(2027);
        $this->addYear(2028);

        $this->assertSame(
            2027,
            $this->selectedYear('2026-11-25')
        );
    }

    public function test_ano_concluido_nao_e_selecionado(): void
    {
        $this->addYear(2026, 'completed');
        $this->addYear(2027);

        $this->assertSame(
            2027,
            $this->selectedYear('2026-11-25')
        );
    }

    public function test_todos_os_anos_concluidos(): void
    {
        $this->addYear(2025, 'completed');
        $this->addYear(2026, 'completed');

        $this->assertNull(
            $this->selectedYear('2026-11-25')
        );
    }

    public function test_nenhum_ano_cadastrado(): void
    {
        $this->assertNull(
            $this->selectedYear('2026-11-25')
        );
    }

    public function test_ano_planejado_nao_oculta_ano_vigente(): void
    {
        $this->addYear(2026);
        $this->addYear(2027, 'planned');

        $this->assertSame(
            2026,
            $this->selectedYear('2026-11-25')
        );
    }

    public function test_ano_vigente_prevalece_sobre_anos_antigos(): void
    {
        $this->addYear(2025);
        $this->addYear(2026);
        $this->addYear(2027);

        $this->assertSame(
            2026,
            $this->selectedYear('2026-11-25')
        );
    }

    public function test_ultimo_ano_nao_concluido_apos_calendario(): void
    {
        $this->addYear(2025);
        $this->addYear(2026);

        $this->assertSame(
            2026,
            $this->selectedYear('2027-01-15')
        );
    }

    public function test_ano_de_outro_municipio_nao_interfere(): void
    {
        $this->addYear(2026);

        app(TenantContext::class)->runFor(
            $this->municipioB,
            fn () => SchoolYear::query()->create([
                'id' => (string) Str::uuid(),
                'year' => 2027,
                'start_date' => '2027-02-08',
                'end_date' => '2027-12-17',
                'status' => 'active',
            ])
        );

        $this->assertSame(
            2026,
            $this->selectedYear('2027-05-15')
        );
    }
}
