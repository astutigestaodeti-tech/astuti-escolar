<?php

namespace Tests\Unit\Educacenso;

use App\Educacenso\Registro00;
use App\Educacenso\Tabelas;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolCensus;
use App\Models\Tenant\SchoolYear;
use Tests\TestCase;

class Registro00Test extends TestCase
{
    private function escola(array $alteracoes = []): School
    {
        return new School(array_replace([
            'name' => 'Escola Fictícia de Teste', 'inep_code' => '11000000',
            'postal_code' => '00000-000', 'address_street' => 'Rua Fictícia',
            'address_number' => '10', 'address_city' => "Alta Floresta d'Oeste",
            'address_state' => 'RO',
        ], $alteracoes));
    }

    private function censo(array $alteracoes = []): SchoolCensus
    {
        return new SchoolCensus(array_replace([
            'operating_status' => 1, 'district_code' => '05', 'location_zone' => 1,
            'differentiated_location' => 7, 'administrative_dependency' => 3,
            'regulation' => 1, 'regulation_sphere' => 3, 'linked_unit' => 0,
        ], $alteracoes));
    }

    private function ano(): SchoolYear
    {
        $ano = new SchoolYear;
        $ano->setDateFormat('Y-m-d H:i:s');
        $ano->fill(['year' => 2026, 'start_date' => '2026-02-02', 'end_date' => '2026-12-18']);

        return $ano;
    }

    private function recusas(Registro00 $registro): array
    {
        return array_values(array_unique(array_map(fn ($p) => $p->campo, array_filter($registro->pendencias, fn ($p) => $p->recusa()))));
    }

    public function test_tabelas_de_distritos_e_orgaos_regionais_preservam_codigos(): void
    {
        $tabelas = Tabelas::do(2026);
        $distritos = $tabelas->distritos(1100015);
        $this->assertCount(6, $distritos);
        $this->assertSame("Alta Floresta D'Oeste", $distritos['05']);
        $this->assertSame('Izidolândia', $distritos['20']);
        $this->assertSame([], $tabelas->distritos(0));

        $acre = $tabelas->orgaosRegionais('ac');
        $this->assertCount(5, $acre);
        $this->assertSame('00001 DIVISAO DE CENSO ESCOLAR', $acre['00001']);
        $this->assertArrayHasKey('MI310', $tabelas->orgaosRegionais('AM'));
        $this->assertSame([], $tabelas->orgaosRegionais('XX'));
    }

    public function test_monta_os_53_campos_sem_inventar_dados_fora_do_escopo(): void
    {
        $registro = Registro00::para($this->escola(), $this->censo([
            'email' => 'escola@example.org',
        ]), $this->ano(), 2026);

        $this->assertSame(range(1, 53), array_keys($registro->valores));
        $this->assertSame('00', $registro->valores[1]);
        $this->assertSame('02/02/2026', $registro->valores[4]);
        $this->assertSame('18/12/2026', $registro->valores[5]);
        $this->assertSame('ESCOLA FICTICIA DE TESTE', $registro->valores[6]);
        $this->assertSame('00000000', $registro->valores[7]);
        $this->assertSame('1100015', $registro->valores[8]);
        $this->assertSame('05', $registro->valores[9]);
        $this->assertSame(array_fill(0, 27, null), array_values(array_slice($registro->valores, 21, 27)));
        $this->assertSame('3', $registro->valores[50]);
        $this->assertContains(22, $this->recusas($registro));
        $this->assertContains(33, $this->recusas($registro));
        $this->assertFalse($registro->pronto());
    }

    public function test_datas_e_regras_de_zona_regulamentacao_e_vinculo(): void
    {
        $registro = Registro00::para($this->escola(), $this->censo([
            'differentiated_location' => 1, 'regulation_sphere' => 1,
            'linked_unit' => 1, 'host_school_code' => '11000000',
            'phone' => '11111111', 'phone_other' => '11111111',
        ]), null, 2026);
        foreach ([4, 5, 14, 15, 16, 20, 50, 52] as $campo) {
            $this->assertContains($campo, $this->recusas($registro));
        }

        $paralisada = Registro00::para($this->escola(), $this->censo([
            'operating_status' => 2, 'regulation' => null, 'linked_unit' => null,
        ]), $this->ano(), 2026);
        $this->assertNull($paralisada->valores[4]);
        $this->assertNull($paralisada->valores[5]);
        $this->assertNull($paralisada->valores[49]);
        $this->assertNotContains(49, $this->recusas($paralisada));
        $this->assertNotContains(51, $this->recusas($paralisada));
    }

    public function test_municipio_distrito_e_escola_privada_nao_sao_inferidos(): void
    {
        $registro = Registro00::para($this->escola(['address_city' => 'Monte Sereno']), $this->censo(), $this->ano(), 2026);
        $this->assertNull($registro->valores[8]);
        $this->assertContains(8, $this->recusas($registro));

        $registro = Registro00::para($this->escola(), $this->censo([
            'district_code' => '99', 'regional_body_code' => 'XXXXX',
            'administrative_dependency' => 4, 'linked_unit' => 2,
        ]), $this->ano(), 2026);
        foreach ([9, 18, 21, 53] as $campo) {
            $this->assertContains($campo, $this->recusas($registro));
        }
    }
}
