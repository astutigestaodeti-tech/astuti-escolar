<?php

namespace Tests\Unit\Educacenso;

use App\Educacenso\RegrasDaEscola;
use Tests\TestCase;

class RegrasDaEscolaTest extends TestCase
{
    private function linha(string $tipo, array $valores = [], array $origem = []): array
    {
        return ['registro' => $tipo, 'valores' => [1 => $tipo] + $valores, 'pendencias' => [], 'origem' => ['tipo' => 'escola', ...$origem]];
    }

    private function turma(string $id, array $dados = []): array
    {
        return $this->linha('20', $dados + [3 => $id, 5 => $id, 6 => '1', 8 => '07:00-11:00', 14 => '6', 22 => '0', 24 => '16'], ['turma' => $id]);
    }

    private function pessoa(string $id, array $dados = []): array
    {
        return $this->linha('30', $dados + [3 => $id, 17 => '0'], ['pessoa' => $id]);
    }

    private function vinculo(string $tipo, string $pessoa, string $turma, array $dados = []): array
    {
        return $this->linha($tipo, $dados + [3 => $pessoa, 5 => $turma], ['pessoa' => $pessoa, 'turma' => $turma]);
    }

    private function base(): array
    {
        return [$this->linha('00', [3 => '1']), $this->linha('10'), $this->turma('T1'),
            $this->pessoa('A'), $this->pessoa('P'), $this->pessoa('G'),
            $this->vinculo('40', 'G', ''), $this->vinculo('50', 'P', 'T1', [7 => '1']), $this->vinculo('60', 'A', 'T1')];
    }

    private function regras(array $linhas): array
    {
        return array_column((new RegrasDaEscola)->conferir($linhas), 'regra');
    }

    private function verifica(string $regra, array $validas, array $invalidas): void
    {
        $this->assertNotContains($regra, $this->regras($validas));
        $this->assertContains($regra, $this->regras($invalidas));
    }

    public function test_escola_ativa_com_todos_os_tipos(): void
    {
        $this->assertSame([], $this->regras($this->base()));
    }

    public function test_regra_18_paralisada_extinta(): void
    {
        foreach (['2', '3'] as $situacao) {
            $base = $this->base();
            $base[0]['valores'][3] = $situacao;
            $validas = array_values(array_filter($base, fn ($l) => in_array($l['registro'], ['00', '30', '40'], true)));
            $this->verifica('18', $validas, $base);
        }
    }

    public function test_regra_19_todos_os_tipos(): void
    {
        $base = $this->base();
        foreach (['10', '20', '30', '40', '50', '60'] as $tipo) {
            $this->verifica('19', $base, array_values(array_filter($base, fn ($l) => $l['registro'] !== $tipo)));
        }
    }

    public function test_regra_20_unicidade(): void
    {
        $base = $this->base();
        foreach ([0, 1] as $i) {
            $this->verifica('20', $base, [...$base, $base[$i]]);
            $faltando = $base;
            unset($faltando[$i]);
            $this->verifica('20', $base, $faltando);
        }
    }

    public function test_regra_21_tres_gestores(): void
    {
        $base = [...$this->base(), $this->vinculo('40', 'G2', ''), $this->vinculo('40', 'G3', '')];
        $this->verifica('21', $base, [...$base, $this->vinculo('40', 'G4', '')]);
    }

    public function test_regra_24_limite_de_turmas(): void
    {
        $base = $this->base();
        for ($i = 2; $i <= 1500; $i++) {
            $base[] = $this->turma('T'.$i);
        }
        $this->verifica('24', $base, [...$base, $this->turma('T1501')]);
    }

    public function test_regra_25_assinatura_completa(): void
    {
        $base = $this->base();
        $this->verifica('25', [...$base, $this->turma('T2', [5 => 'T1', 24 => '17'])], [...$base, $this->turma('T2', [5 => 'T1'])]);
    }

    public function test_regra_26_aluno_real_na_turma(): void
    {
        $base = $this->base();
        $this->verifica('26', $base, [...$base, $this->turma('T2')]);
    }

    public function test_regra_27_profissional_real_na_turma(): void
    {
        $base = $this->base();
        unset($base[7]);
        $this->verifica('27', $this->base(), $base);
    }

    public function test_regra_30_mesma_pessoa_na_mesma_turma(): void
    {
        $base = $this->base();
        foreach (['50', '60'] as $tipo) {
            $this->verifica('30', $base, [...$base, $this->vinculo($tipo, 'A', 'T1', [7 => '1'])]);
        }
        $this->verifica('30', $base, [...$base, $base[7]]);
    }

    public function test_regra_31_horarios_aluno_aluno(): void
    {
        $base = $this->base();
        $v = $this->vinculo('60', 'A', 'T2');
        foreach (['11:00-13:00', '06:00-07:00'] as $horario) {
            $this->assertNotContains('31', $this->regras([...$base, $this->turma('T2', [8 => $horario]), $v]));
        }
        $this->verifica('31', [...$base, $this->turma('T2', [14 => '5', 24 => null]), $v], [...$base, $this->turma('T2', [8 => '08:00-10:00']), $v]);
        $this->verifica('31', [...$base, $this->turma('T2', [6 => '3']), $v], [...$base, $this->turma('T2'), $v]);
    }

    public function test_regra_31_horarios_aluno_profissional_e_profissional_profissional(): void
    {
        $base = $this->base();
        $this->verifica('31', [...$base, $this->turma('T2', [8 => '06:00-12:00']), $this->vinculo('50', 'A', 'T2')], [...$base, $this->turma('T2', [8 => '08:00-10:00']), $this->vinculo('50', 'A', 'T2')]);
        $this->assertNotContains('31', $this->regras([...$base, $this->turma('T2'), $this->vinculo('50', 'P', 'T2')]));
    }

    public function test_regra_32_quatro_vinculos_com_etapa(): void
    {
        $base = $this->base();
        for ($i = 2; $i <= 4; $i++) {
            $base[] = $this->turma('T'.$i, [24 => '39']);
            $base[] = $this->vinculo('60', 'A', 'T'.$i);
        }
        $this->verifica('32', $base, [...$base, $this->turma('T5', [24 => '40']), $this->vinculo('60', 'A', 'T5')]);
    }

    public function test_regra_33_dois_vinculos_com_etapa_nao_excepcional(): void
    {
        $base = [...$this->base(), $this->turma('T2'), $this->vinculo('60', 'A', 'T2')];
        $this->verifica('33', [...$base, $this->turma('T3', [24 => '64']), $this->vinculo('60', 'A', 'T3')], [...$base, $this->turma('T3'), $this->vinculo('60', 'A', 'T3')]);
    }

    public function test_regra_34_quatro_vinculos_sem_etapa(): void
    {
        $base = $this->base();
        for ($i = 2; $i <= 5; $i++) {
            $base[] = $this->turma('T'.$i, [14 => '5', 24 => null]);
            $base[] = $this->vinculo('60', 'A', 'T'.$i);
        }
        $this->verifica('34', $base, [...$base, $this->turma('T6', [14 => '4', 24 => null]), $this->vinculo('60', 'A', 'T6')]);
    }

    public function test_codigo_proprio_unico(): void
    {
        $base = $this->base();
        $this->verifica('30.3', $base, [...$base, $this->pessoa('OUTRO', [3 => 'A'])]);
    }

    public function test_cpf_unico_sem_confundir_campos_nulos(): void
    {
        $base = [...$this->base(), $this->pessoa('A2', [5 => '00000000001'])];
        $this->verifica('30.CPF', $base, [...$base, $this->pessoa('A3', [5 => '00000000001'])]);
    }

    public function test_registro_50_regra_7_so_interpretes_ou_tutores(): void
    {
        foreach (['4', '6'] as $funcao) {
            $base = $this->base();
            $base[7]['valores'][7] = $funcao;
            $this->verifica('50.7.7', [...$base, $this->vinculo('50', 'P2', 'T1', [7 => '1'])], $base);
        }
    }

    public function test_registro_50_regra_8_funcao_4(): void
    {
        $base = [...$this->base(), $this->vinculo('50', 'P2', 'T1', [7 => '4']), $this->pessoa('P2')];
        $validas = $base;
        $validas[3]['valores'][17] = '1';
        $validas[3]['valores'][21] = '1';
        $this->verifica('50.7.8', $validas, $base);
    }

    public function test_registro_30_campo_17_regra_2_na_turma_real(): void
    {
        $base = $this->base();
        $base[2]['valores'][14] = '5';
        $validas = $base;
        $validas[3]['valores'][17] = '1';
        $this->verifica('30.17.2', $validas, $base);
        $base[2]['valores'][14] = '6';
        $base[2]['valores'][22] = '1';
        $this->verifica('30.17.2', $validas, $base);
    }
}
