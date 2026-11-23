<?php

namespace Tests\Unit\Geo;

use App\Geo\Geocoder;
use PHPUnit\Framework\TestCase;

class AddressAbbreviationTest extends TestCase
{
    public function test_abreviacoes_do_google_e_das_planilhas_viram_nomes_por_extenso(): void
    {
        $geo = new Geocoder;
        $this->assertSame('Rua Prefeito José Basílio Alvarenga', $geo->expand('R. Pref. José Basílio Alvarenga'));
        $this->assertSame('Avenida Doutor Antônio Silva', $geo->expand('Av Dr Antônio Silva'));
        $this->assertSame('Rua Professora Maria Santa Clara', $geo->expand('R. Profª Maria Sta. Clara'));
        $this->assertSame('Jardim Monte Serrat', $geo->expand('Jd. Monte Serrat'));
        // Palavras comuns sem ponto ficam como estão.
        $this->assertSame('Rua Com Sol e Pe de Moleque', $geo->expand('Rua Com Sol e Pe de Moleque'));
    }
}
