<?php

namespace Tests\Unit\Geo;

use App\Geo\Coordinates;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CoordinatesTest extends TestCase
{
    public static function textos(): array
    {
        return [
            'copiado do Google' => ['-23.31234567890123, -46.12345678901234', [-23.312346, -46.123457]],
            'sem espaço' => ['-23.3123,-46.1234', [-23.3123, -46.1234]],
            'vírgula decimal' => ['-23,3123 -46,1234', [-23.3123, -46.1234]],
            'ponto e vírgula' => ['-23.3123; -46.1234', [-23.3123, -46.1234]],
            'entre parênteses' => ['(-23.3123, -46.1234)', [-23.3123, -46.1234]],
            'graus do Google' => ['23°18\'44.4"S 46°08\'31.2"W', [-23.312333, -46.142]],
            'graus em português' => ['23°18\'44.4"S 46°08\'31.2"O', [-23.312333, -46.142]],
            'longitude primeiro' => ['46°08\'31.2"W 23°18\'44.4"S', [-23.312333, -46.142]],
            'link com marcador' => ['https://www.google.com/maps/place/X/@-23.4,-46.2,17z/data=!3d-23.3123!4d-46.1234', [-23.3123, -46.1234]],
            'link com centro' => ['https://www.google.com.br/maps/@-23.3123,-46.1234,15z', [-23.3123, -46.1234]],
            'link de busca' => ['https://maps.google.com/?q=-23.3123,-46.1234', [-23.3123, -46.1234]],
        ];
    }

    #[DataProvider('textos')]
    public function test_le_coordenadas(string $texto, array $esperado): void
    {
        $this->assertSame(['lat' => $esperado[0], 'lon' => $esperado[1]], (new Coordinates)->parse($texto));
    }

    public function test_endereco_e_link_de_outro_site_nao_sao_coordenadas(): void
    {
        $coordinates = new Coordinates;
        $this->assertNull($coordinates->parse('Rua das Palmeiras, 327, Monte Sereno'));
        $this->assertNull($coordinates->parse('https://exemplo.invalid/@-23.3123,-46.1234'));
        $this->assertNull($coordinates->parse('123, 456'));
        $this->assertNull($coordinates->parse('91.0, 10.0'));
        $this->assertFalse($coordinates->looksLikeMapLink('https://exemplo.invalid/mapa'));
        $this->assertTrue($coordinates->looksLikeMapLink('https://maps.app.goo.gl/abc'));
    }
}
