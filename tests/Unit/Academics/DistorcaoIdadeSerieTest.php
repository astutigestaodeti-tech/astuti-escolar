<?php

namespace Tests\Unit\Academics;

use App\Academics\DistorcaoIdadeSerie;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolClassCensus;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DistorcaoIdadeSerieTest extends TestCase
{
    public function test_idade_em_31_de_marco_e_dois_anos_acima(): void
    {
        $service = new DistorcaoIdadeSerie;
        $this->assertFalse($service->calcular(Carbon::parse('2018-04-01'), 2026, 6)['distorcao']);
        $this->assertTrue($service->calcular(Carbon::parse('2018-03-31'), 2026, 6)['distorcao']);
        $this->assertSame(8, $service->calcular(Carbon::parse('2018-03-31'), 2026, 6)['idade']);
        $this->assertNull($service->calcular(null, 2026, 6));
        $this->assertNull($service->calcular(Carbon::parse('2027-01-01'), 2026, 6));
    }

    public function test_etapa_inep_prevalece_e_sugestao_e_provisoria(): void
    {
        $service = new DistorcaoIdadeSerie;
        $class = new SchoolClass(['name' => '3º Ano A', 'stage' => 'Ensino Fundamental']);
        $this->assertSame(['idade' => 8, 'sugerida' => true, 'etapa' => 16], $service->etapa($class, null));
        $this->assertSame(['idade' => 14, 'sugerida' => false, 'etapa' => 41], $service->etapa($class, new SchoolClassCensus(['inep_stage_code' => 41, 'class_type' => 6])));
        foreach ([1, 2, 3, 22, 23, 56, 69, 70, 72] as $code) {
            $this->assertNull($service->etapa($class, new SchoolClassCensus(['inep_stage_code' => $code, 'class_type' => 6]))['idade']);
        }
        $this->assertNull($service->etapa($class, new SchoolClassCensus(['inep_stage_code' => 16, 'class_type' => 5]))['idade']);
    }
}
