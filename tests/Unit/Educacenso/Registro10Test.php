<?php

namespace Tests\Unit\Educacenso;

use App\Educacenso\Leiaute;
use App\Educacenso\Registro10;
use App\Educacenso\Tabelas;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolCensus;
use App\Models\Tenant\SchoolInfrastructureCensus;
use Tests\Support\InfrastructureCensusFixture;
use Tests\TestCase;

class Registro10Test extends TestCase
{
    private function registro(array $respostas, int $situacao = 1): Registro10
    {
        return Registro10::para(
            new School(['inep_code' => '35000000']),
            new SchoolInfrastructureCensus(['census_year' => 2026, 'answers' => (object) array_filter($respostas, fn ($valor) => $valor !== null)]),
            new SchoolCensus(['operating_status' => $situacao]),
            2026,
        );
    }

    private function recusas(Registro10 $registro): array
    {
        return array_values(array_map(fn ($p) => $p->campo, array_filter($registro->pendencias, fn ($p) => $p->recusa())));
    }

    public function test_questionario_completo_tem_187_campos_e_aviso_entre_registros(): void
    {
        $this->assertCount(16, Leiaute::do(2026)->grupos('10'));
        $registro = $this->registro(InfrastructureCensusFixture::valid());
        $this->assertSame(range(1, 187), array_keys($registro->valores));
        $this->assertSame('10', $registro->valores[1]);
        $this->assertSame('35000000', $registro->valores[2]);
        $this->assertSame([], $this->recusas($registro));
        $this->assertTrue($registro->pronto());
        $this->assertTrue($registro->aplicavel);
        $this->assertNull($registro->valores[138]);
        $this->assertNull($registro->valores[165]);
        $this->assertTrue(collect($registro->pendencias)->contains(fn ($p) => $p->campo === 139 && ! $p->recusa()));
    }

    public function test_regras_dos_16_grupos_oficiais(): void
    {
        $base = InfrastructureCensusFixture::valid();
        $casos = [
            3 => [3 => '0'],
            12 => [10 => '1', 11 => '35000001', 12 => '35000001'],
            18 => [18 => '0'],
            24 => [24 => '0'],
            28 => [28 => '0'],
            32 => [32 => '0'],
            37 => [37 => '0'],
            41 => [41 => '0'],
            81 => [81 => '0'],
            96 => [96 => '0'],
            111 => [111 => '0'],
            119 => [119 => null],
            140 => array_fill_keys(range(140, 159), null),
            165 => [164 => '1'] + array_fill_keys(range(165, 170), '0'),
            174 => [174 => '0'],
            182 => [181 => '1'] + array_fill_keys(range(182, 187), '0'),
        ];
        foreach ($casos as $campo => $alteracoes) {
            $registro = $this->registro(array_replace($base, $alteracoes));
            $this->assertContains($campo, $this->recusas($registro), 'Grupo do campo '.$campo);
        }
    }

    public function test_nenhum_e_condicionais_locais_sao_conferidos(): void
    {
        $base = InfrastructureCensusFixture::valid();
        foreach ([23, 27, 31, 80, 90, 102, 115, 159, 179] as $campo) {
            $this->assertContains($campo, $this->recusas($this->registro(array_replace($base, [$campo => '1']))));
        }
        foreach ([
            9 => [9 => null],
            11 => [10 => '1'],
            13 => [10 => '1', 11 => '35000001', 13 => '35000002'],
            30 => [29 => '1', 30 => '1'],
            44 => [45 => '1'],
            93 => [93 => '5'],
            103 => [103 => '0'],
            116 => [113 => '1', 116 => '1'],
            118 => [113 => '1', 116 => '2'],
            138 => [138 => '1'],
            139 => [139 => '2'],
            165 => [165 => '0'],
            187 => [187 => '1'],
        ] as $campo => $alteracoes) {
            $this->assertContains($campo, $this->recusas($this->registro(array_replace($base, $alteracoes))), 'Campo '.$campo);
        }
        $this->assertTrue($this->registro(array_replace($base, [119 => null, 138 => '1']))->pronto());
    }

    public function test_linguas_indigenas_vem_da_tabela_oficial_e_nao_se_repetem(): void
    {
        $linguas = Tabelas::do(2026)->linguasIndigenas();
        $this->assertSame('Boróro', $linguas['00030']);
        $this->assertSame('Umutína', $linguas['00244']);
        $base = InfrastructureCensusFixture::valid();
        $registro = $this->registro(array_replace($base, [160 => '1', 161 => '00030', 162 => '00030', 163 => '99999']));
        $this->assertContains(162, $this->recusas($registro));
        $this->assertContains(163, $this->recusas($registro));
    }

    public function test_escola_paralisada_ou_extinta_nao_envia_registro_10(): void
    {
        foreach ([2, 3] as $situacao) {
            $registro = $this->registro([], $situacao);
            $this->assertFalse($registro->aplicavel);
            $this->assertTrue($registro->pronto());
            $this->assertNull($registro->valores[3]);
        }
        $escola = new School(['inep_code' => '35000000']);
        $this->assertFalse(Registro10::para($escola, null, new SchoolCensus(['operating_status' => 1]), 2026)->pronto());
        $this->assertFalse(Registro10::para($escola, null, null, 2026)->pronto());
    }
}
