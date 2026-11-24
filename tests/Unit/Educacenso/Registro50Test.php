<?php

namespace Tests\Unit\Educacenso;

use App\Educacenso\Registro50;
use App\Models\Tenant\ClassTeacherAssignment;
use App\Models\Tenant\ClassTeacherAssignmentCensus;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolCensus;
use App\Models\Tenant\SchoolClassCensus;
use Tests\TestCase;

class Registro50Test extends TestCase
{
    private function registro(array $dados = [], array $turma = [], int $dependencia = 3): Registro50
    {
        $classe = new SchoolClassCensus(array_replace(['class_type' => 6, 'mediation' => 1, 'inep_stage_code' => 16, 'curricular_areas' => [3, 5]], $turma));
        $classe->census_code = 'ABCDEFGHIJ0123456789';

        return Registro50::para(new School(['inep_code' => '35000001']),
            new ClassTeacherAssignment(['staff_member_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa']),
            new ClassTeacherAssignmentCensus(array_replace(['class_function' => 1, 'employment_status' => 1, 'components' => [3, 5]], $dados)),
            $classe, new SchoolCensus(['operating_status' => 1, 'administrative_dependency' => $dependencia]), 2026);
    }

    public function test_docente_com_componentes_e_avisos(): void
    {
        $r = $this->registro();
        $this->assertTrue($r->pronto());
        $this->assertSame(range(1, 38), array_keys($r->valores));
        $this->assertSame('50', $r->valores[1]);
        $this->assertSame('ABCDEFGHIJ0123456789', $r->valores[5]);
        $this->assertSame('3', $r->valores[9]);
        $this->assertSame('5', $r->valores[10]);
        foreach ([4, 6, ...range(11, 38)] as $campo) {
            $this->assertNull($r->valores[$campo]);
        }
        $interprete = $this->registro(['class_function' => 4, 'employment_status' => null, 'components' => []]);
        $this->assertTrue($interprete->pronto());
        $this->assertTrue(collect($interprete->pendencias)->contains(fn ($p) => ! $p->recusa() && str_contains($p->mensagem, 'surdez')));
        $this->assertTrue($this->registro(['class_function' => 5], ['mediation' => 3])->pronto());
    }

    public function test_regras_locais_de_funcao_regime_e_componentes(): void
    {
        foreach ([
            [7, ['class_function' => 1], ['mediation' => 3], 3],
            [7, ['class_function' => 5], [], 3],
            [7, ['class_function' => 2, 'employment_status' => null, 'components' => []], ['class_type' => 4], 3],
            [7, ['class_function' => 3, 'employment_status' => null, 'components' => []], [], 3],
            [7, ['class_function' => 9], [], 3],
            [8, ['employment_status' => null], [], 3],
            [8, [], [], 4],
            [8, ['class_function' => 4, 'components' => []], [], 3],
            [9, ['class_function' => 4, 'employment_status' => null], [], 3],
            [9, ['components' => [6]], [], 3],
            [9, ['components' => [3]], ['inep_stage_code' => 1], 3],
            [10, ['components' => [3, 3]], [], 3],
            [11, ['components' => [0 => 3, 2 => 5]], [], 3],
        ] as [$campo, $dados, $turma, $dep]) {
            $r = $this->registro($dados, $turma, $dep);
            $this->assertFalse($r->pronto());
            $this->assertTrue(collect($r->pendencias)->contains(fn ($p) => $p->campo === $campo && $p->recusa()), 'Campo '.$campo);
        }
    }
}
