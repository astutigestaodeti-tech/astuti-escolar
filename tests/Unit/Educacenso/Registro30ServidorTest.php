<?php

namespace Tests\Unit\Educacenso;

use App\Educacenso\Registro30Servidor;
use App\Models\Tenant\School;
use App\Models\Tenant\StaffCensus;
use App\Models\Tenant\StaffMember;
use Tests\TestCase;

class Registro30ServidorTest extends TestCase
{
    private const SERVIDOR = '5e7d0000-1a2b-4c3d-8e4f-0a1b2c3d4e5f';

    private function escola(): School
    {
        return new School(['name' => 'EMEF Teste', 'inep_code' => '35000001']);
    }

    private function servidor(array $dados = []): StaffMember
    {
        $servidor = new StaffMember(['full_name' => 'Joana Fictícia Diretora', 'role' => 'director', 'institutional_email' => 'joana@escola.edu.br', ...$dados]);
        $servidor->id = self::SERVIDOR;

        return $servidor;
    }

    private function censo(array $dados = []): StaffCensus
    {
        return (new StaffCensus)->setDateFormat('Y-m-d H:i:s')->fill([
            'cpf' => '52998224725', 'birth_date' => '1980-06-15', 'filiation' => 1, 'parent1_name' => 'Maria Fictícia',
            'sex' => 'female', 'race_color' => 'preta', 'nationality' => 'brasileira', 'nationality_country_code' => 76,
            'birth_municipality_code' => 3546801, 'conditions' => [], 'education_level' => 6, 'high_school_type' => 1,
            'higher_courses' => [['ano' => 2004, 'ies' => 55, 'curso' => '0113P011']],
            'postgraduates' => [['ano' => 2010, 'area' => 1, 'tipo' => 1]],
            'continuing_education' => ['gestao_escolar'], ...$dados,
        ]);
    }

    private function mensagens(Registro30Servidor $r): array
    {
        return array_map(fn ($p): string => ($p->campo ?? '-').': '.$p->mensagem, $r->pendencias);
    }

    public function test_gestora_completa_fica_pronta_com_formacao_e_email(): void
    {
        $r = Registro30Servidor::para($this->escola(), $this->servidor(), $this->censo(), true, false, 2026);

        $this->assertSame([], $this->mensagens($r));
        $v = $r->valores;
        $this->assertCount(110, $v);
        $this->assertSame(['5E7D00001A2B4C3D8E4F', '52998224725', 'JOANA FICTICIA DIRETORA', '15/06/1980'], [$v[3], $v[5], $v[6], $v[7]]);
        $this->assertSame(['2', '2', '1', '76', '3546801', '0'], [$v[11], $v[12], $v[14], $v[15], $v[16], $v[17]]);
        $this->assertNull($v[29]);
        $this->assertNull($v[51]);
        $this->assertSame(['6', '1', '0113P011', '2004', '55', null], [$v[56], $v[57], $v[58], $v[59], $v[60], $v[61]]);
        $this->assertSame(['1', '1', '2010', null, null], [$v[70], $v[71], $v[72], $v[73], $v[88]]);
        $this->assertSame(['1', '0', '0'], [$v[107], $v[89], $v[109]]);
        $this->assertSame('JOANA@ESCOLA.EDU.BR', $v[110]);
    }

    public function test_professor_sem_dados_do_censo_tem_uma_pendencia_so_e_sem_email(): void
    {
        $r = Registro30Servidor::para($this->escola(), $this->servidor(['institutional_email' => null]), null, false, true, 2026);

        $this->assertSame(['-: Os dados do Censo do servidor ainda não foram preenchidos (CPF, nascimento, filiação e formação).'], $this->mensagens($r));
        $this->assertNull($r->valores[110]);
        $this->assertFalse($r->pronto());
    }

    public function test_regras_condicionais_de_cpf_idade_escolaridade_e_email(): void
    {
        $semCpf = Registro30Servidor::para($this->escola(), $this->servidor(['institutional_email' => null]),
            $this->censo(['cpf' => null, 'birth_date' => '2010-01-01', 'education_level' => null]), true, false, 2026);
        $campos = array_map(fn ($p) => $p->campo, $semCpf->pendencias);
        $this->assertContains(5, $campos);
        $this->assertContains(7, $campos);
        $this->assertContains(56, $campos);
        $this->assertContains(110, $campos);

        // Estrangeiro em sala pode ficar sem CPF; 14 anos é a idade mínima de profissional em sala.
        $estrangeiro = Registro30Servidor::para($this->escola(), $this->servidor(), $this->censo([
            'cpf' => null, 'nationality' => 'estrangeira', 'nationality_country_code' => 600, 'birth_municipality_code' => null,
            'birth_date' => '2012-01-01', 'education_level' => 2, 'high_school_type' => null, 'higher_courses' => null, 'postgraduates' => null,
        ]), false, true, 2026);
        $this->assertSame([], $this->mensagens($estrangeiro));
        $this->assertNull($estrangeiro->valores[110]);
        $this->assertNull($estrangeiro->valores[58]);

        $semCurso = Registro30Servidor::para($this->escola(), $this->servidor(), $this->censo([
            'higher_courses' => null, 'high_school_type' => null, 'race_color' => 'indigena',
            'postgraduates' => [['ano' => 2010, 'area' => 1, 'tipo' => 1]],
        ]), false, true, 2026);
        $campos = array_map(fn ($p) => $p->campo, $semCurso->pendencias);
        $this->assertContains(57, $campos);
        $this->assertContains(58, $campos);
        $this->assertContains(13, $campos);
    }

    public function test_deficiencias_e_formacao_nenhuma(): void
    {
        $r = Registro30Servidor::para($this->escola(), $this->servidor(), $this->censo([
            'conditions' => ['baixa_visao', 'deficiencia_fisica'], 'continuing_education' => [], 'postgraduates' => null, 'no_postgraduate' => true,
        ]), false, true, 2026);

        $this->assertSame([], $this->mensagens($r));
        $v = $r->valores;
        $this->assertSame(['1', '0', '1', '1', '1', '0'], [$v[17], $v[18], $v[19], $v[24], $v[26], $v[27]]);
        $this->assertSame(['1', '0', '1'], [$v[88], $v[107], $v[109]]);
    }
}
