<?php

namespace Tests\Unit\Educacenso;

use App\Educacenso\Registro40;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolCensus;
use App\Models\Tenant\StaffManagerCensus;
use App\Models\Tenant\StaffMember;
use Tests\TestCase;

class Registro40Test extends TestCase
{
    private function registro(array $dados, int $situacao = 1, int $dependencia = 3): Registro40
    {
        return Registro40::para(new School(['inep_code' => '35000001']),
            new StaffMember(['id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa']),
            new StaffManagerCensus($dados),
            new SchoolCensus(['operating_status' => $situacao, 'administrative_dependency' => $dependencia]), 2026);
    }

    public function test_diretor_e_outro_cargo(): void
    {
        $r = $this->registro(['position' => 1, 'access_criterion' => 2, 'employment_status' => 1]);
        $this->assertTrue($r->pronto());
        $this->assertSame(['40', '35000001', 'AAAAAAAAAAAA4AAA8AAA', null, '1', '2', '1'], array_values($r->valores));
        $this->assertTrue($this->registro(['position' => 2])->pronto());
        $this->assertTrue($this->registro(['position' => 1], 2)->pronto());
        $this->assertTrue($this->registro(['position' => 1, 'access_criterion' => 1], 1, 4)->pronto());
    }

    public function test_regras_condicionais_recusam(): void
    {
        foreach ([
            [6, ['position' => 1, 'employment_status' => 1], 1, 3],
            [7, ['position' => 1, 'access_criterion' => 2], 1, 3],
            [6, ['position' => 1, 'access_criterion' => 1, 'employment_status' => 1], 1, 3],
            [6, ['position' => 1, 'access_criterion' => 4], 1, 4],
            [7, ['position' => 1, 'access_criterion' => 1, 'employment_status' => 1], 1, 4],
            [6, ['position' => 2, 'access_criterion' => 2], 1, 3],
            [7, ['position' => 2, 'employment_status' => 1], 1, 3],
            [6, ['position' => 1, 'access_criterion' => 2], 3, 3],
            [7, ['position' => 1, 'employment_status' => 1], 2, 3],
            [5, ['position' => 9], 1, 3],
        ] as [$campo, $dados, $situacao, $dep]) {
            $r = $this->registro($dados, $situacao, $dep);
            $this->assertFalse($r->pronto());
            $this->assertTrue(collect($r->pendencias)->contains(fn ($p) => $p->campo === $campo && $p->recusa()));
        }
    }
}
