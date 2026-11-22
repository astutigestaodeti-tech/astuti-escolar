<?php

namespace Tests\Unit\Geo;

use App\Geo\Distance;
use PHPUnit\Framework\TestCase;

class DistanceTest extends TestCase
{
    public function test_distancia_em_linha_reta_e_rotulo(): void
    {
        // Praça da Sé até a Avenida Paulista (MASP): cerca de 2,8 km.
        $km = Distance::km(-23.550520, -46.633309, -23.561414, -46.655881);
        $this->assertEqualsWithDelta(2.6, $km, 0.2);
        $this->assertSame('2,6 km', Distance::label(2.62));
        $this->assertSame('560 m', Distance::label(0.556));
        $this->assertSame(0.0, Distance::km(-23.5, -46.6, -23.5, -46.6));
    }

    public function test_aviso_do_transporte_pela_regra_municipal(): void
    {
        $this->assertStringStartsWith('Mora a mais de 2,0 km', (string) Distance::transportHint(3.1, null, 2.0));
        $this->assertStringStartsWith('Está no transporte e mora a menos de 2,0 km', (string) Distance::transportHint(0.8, 'active', 2.0));
        $this->assertNull(Distance::transportHint(3.1, 'active', 2.0));
        $this->assertNull(Distance::transportHint(0.8, 'not_requested', 2.0));
    }
}
