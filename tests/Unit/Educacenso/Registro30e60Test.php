<?php

namespace Tests\Unit\Educacenso;

use App\Educacenso\Registro30Aluno;
use App\Educacenso\Registro60;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClassCensus;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentCensus;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\StudentSpecialEducation;
use App\Models\Tenant\TransportRouteCensus;
use Tests\TestCase;

class Registro30e60Test extends TestCase
{
    private const ALUNO = '0a1b2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d';

    private function escola(): School
    {
        return new School(['name' => 'EMEF Teste', 'inep_code' => '35000001']);
    }

    private function aluno(array $dados = []): Student
    {
        $aluno = (new Student)->setDateFormat('Y-m-d H:i:s')->fill([
            'full_name' => 'Ana Fictícia de Souza', 'birth_date' => '2018-03-01', 'sex' => 'female', 'race_color' => 'parda',
            'nationality' => 'brasileira', 'postal_code' => '07500-000', 'residence_zone' => 'urban', ...$dados,
        ]);
        $aluno->id = self::ALUNO;

        return $aluno;
    }

    private function censo(array $dados = []): StudentCensus
    {
        return new StudentCensus([
            'filiation' => 1, 'parent1_name' => 'Maria Fictícia de Souza', 'nationality_country_code' => 76,
            'birth_municipality_code' => 3550308, 'residence_country_code' => 76, 'residence_municipality_code' => 3546801,
            'differentiated_location' => 7, ...$dados,
        ]);
    }

    private function turma(array $dados = []): SchoolClassCensus
    {
        $turma = new SchoolClassCensus(['inep_stage_code' => 16, 'mediation' => 1, 'class_type' => 6, 'differentiated_location' => 0, 'special_class' => false, ...$dados]);
        $turma->census_code = 'ABCDEFGHIJ0123456789';

        return $turma;
    }

    private function matricula(array $dados = []): StudentEnrollment
    {
        return new StudentEnrollment(['student_id' => self::ALUNO, 'school_class_id' => 'c1a55000-0000-4000-8000-000000000001', 'status' => 'active', 'other_schooling_space' => 1, ...$dados]);
    }

    private function mensagens(array $pendencias): array
    {
        return array_map(fn ($p): string => $p->registro.'.'.($p->campo ?? '-').': '.$p->mensagem, $pendencias);
    }

    public function test_aluno_completo_fica_pronto_com_codigos_oficiais(): void
    {
        $r30 = Registro30Aluno::para($this->escola(), $this->aluno(), $this->censo(), null, $this->turma(), 2026);

        $this->assertSame([], $this->mensagens($r30->pendencias));
        $v = $r30->valores;
        $this->assertCount(110, $v);
        $this->assertSame('0A1B2C3D4E5F4A6B8C7D', $v[3]);
        $this->assertSame('ANA FICTICIA DE SOUZA', $v[6]);
        $this->assertSame('01/03/2018', $v[7]);
        $this->assertSame(['1', 'MARIA FICTICIA DE SOUZA', null], [$v[8], $v[9], $v[10]]);
        $this->assertSame(['2', '3', null, '1', '76', '3550308'], [$v[11], $v[12], $v[13], $v[14], $v[15], $v[16]]);
        $this->assertSame(['0', null, '0', null, null], [$v[17], $v[18], $v[29], $v[36], $v[49]]);
        $this->assertSame(['76', '07500000', '3546801', '1', '7'], [$v[51], $v[52], $v[53], $v[54], $v[55]]);
        $this->assertNull($v[56]);
        $this->assertNull($v[110]);
    }

    public function test_pendencias_do_registro_30(): void
    {
        $semCenso = Registro30Aluno::para($this->escola(), $this->aluno(), null, null, null, 2026);
        $this->assertFalse($semCenso->pronto());
        $this->assertStringContainsString('ainda não foram preenchidos', implode(' ', $this->mensagens($semCenso->pendencias)));

        $indigena = Registro30Aluno::para($this->escola(), $this->aluno(['race_color' => 'indigena']), $this->censo(), null, null, 2026);
        $this->assertContains('30.13: Informe o povo indígena nos dados do Censo do aluno.', $this->mensagens($indigena->pendencias));

        $nome = Registro30Aluno::para($this->escola(), $this->aluno(['full_name' => 'Ana']), $this->censo(), null, null, 2026);
        $this->assertStringContainsString('30.6: Nome completo: deve ter nome e sobrenome.', implode(' ', $this->mensagens($nome->pendencias)));
        $comCpf = Registro30Aluno::para($this->escola(), $this->aluno(['full_name' => 'Ana', 'cpf' => '529.982.247-25']), $this->censo(), null, null, 2026);
        $this->assertSame([], $this->mensagens($comCpf->pendencias));

        $certidao = Registro30Aluno::para($this->escola(), $this->aluno(['birth_certificate' => '12345601552015100012345612345678']), $this->censo(), null, null, 2026);
        $this->assertContains('30.50: O ano de registro da certidão não combina com a data de nascimento.', $this->mensagens($certidao->pendencias));
        $certidaoBoa = Registro30Aluno::para($this->escola(), $this->aluno(['birth_certificate' => '1234560155201810001234561234567X']), $this->censo(), null, null, 2026);
        $this->assertContains('30.50: A certidão nova deve ter 32 caracteres: 30 números e mais 2 números ou "XX".', $this->mensagens($certidaoBoa->pendencias));

        $aee = Registro30Aluno::para($this->escola(), $this->aluno(), $this->censo(), null, $this->turma(['class_type' => 5, 'inep_stage_code' => null]), 2026);
        $this->assertContains('30.17: Aluno em classe especial ou AEE precisa ter a categoria de educação especial informada.', $this->mensagens($aee->pendencias));
    }

    public function test_educacao_especial_vira_campos_17_a_49_com_deficiencia_multipla_e_anexo_4(): void
    {
        $especial = new StudentSpecialEducation(['conditions' => ['cegueira', 'deficiencia_fisica'], 'learning_disorders' => ['dislexia'], 'exam_resources' => ['braille', 'tempo_adicional']]);
        $v = Registro30Aluno::para($this->escola(), $this->aluno(), $this->censo(), $especial, null, 2026)->valores;
        $this->assertSame(['1', '1', '0', '1', '1'], [$v[17], $v[18], $v[19], $v[24], $v[26]]);
        $this->assertSame(['1', '0', '1'], [$v[29], $v[30], $v[33]]);
        $this->assertSame(['1', '1', '0', '0'], [$v[47], $v[48], $v[36], $v[49]]);

        // Cegueira sem recurso: a tabela do Anexo 4 não traz "Nenhum" (aviso, não recusa).
        $semRecurso = Registro30Aluno::para($this->escola(), $this->aluno(), $this->censo(), new StudentSpecialEducation(['conditions' => ['cegueira']]), null, 2026);
        $this->assertSame('1', $semRecurso->valores[49]);
        $this->assertTrue($semRecurso->pronto());
        $this->assertSame('aviso', $semRecurso->pendencias[0]->gravidade);

        $incompativel = Registro30Aluno::para($this->escola(), $this->aluno(), $this->censo(), new StudentSpecialEducation(['conditions' => ['cegueira'], 'exam_resources' => ['prova_ampliada']]), null, 2026);
        $this->assertContains('30.36: Recursos de prova incompatíveis com as categorias marcadas (Anexo 4).', $this->mensagens($incompativel->pendencias));
    }

    public function test_registro_60_completo_com_transporte_por_rota(): void
    {
        $rota = new TransportRouteCensus(['public_power' => 2, 'vehicles' => ['onibus', 'van']]);
        $r60 = Registro60::para($this->escola(), $this->matricula(['transport_status' => 'active', 'transport_route' => 'Rota 1']), $this->turma(), $this->censo(), null, $rota, 2026);

        $this->assertSame([], $this->mensagens($r60->pendencias));
        $v = $r60->valores;
        $this->assertCount(33, $v);
        $this->assertSame(['60', '35000001', '0A1B2C3D4E5F4A6B8C7D', 'ABCDEFGHIJ0123456789'], [$v[1], $v[2], $v[3], $v[5]]);
        $this->assertSame([null, null, null, null], [$v[6], $v[7], $v[8], $v[10]]);
        $this->assertSame(['1', '1', '2'], [$v[21], $v[22], $v[23]]);
        $this->assertSame(['0', '0', '1', '0', '1', '0'], [$v[24], $v[25], $v[26], $v[27], $v[28], $v[33]]);
    }

    public function test_pendencias_do_registro_60(): void
    {
        $semTurma = Registro60::para($this->escola(), $this->matricula(['school_class_id' => null]), null, $this->censo(), null, null, 2026);
        $this->assertSame(['60.5: O aluno não está em nenhuma turma: o Educacenso recusa matrícula sem turma.'], $this->mensagens($semTurma->pendencias));

        $semRota = Registro60::para($this->escola(), $this->matricula(['transport_status' => 'active']), $this->turma(), $this->censo(), null, null, 2026);
        $this->assertContains('60.23: O aluno usa transporte, mas sem rota: informe a rota para o Censo saber o veículo.', $this->mensagens($semRota->pendencias));

        $multi = Registro60::para($this->escola(), $this->matricula(), $this->turma(['inep_stage_code' => 3]), $this->censo(), null, null, 2026);
        $this->assertContains('60.8: Turma multi: informe a etapa individual do aluno na matrícula.', $this->mensagens($multi->pendencias));
        $multiOk = Registro60::para($this->escola(), $this->matricula(['inep_stage_code' => 2]), $this->turma(['inep_stage_code' => 3]), $this->censo(), null, null, 2026);
        $this->assertSame('2', $multiOk->valores[8]);
        $this->assertTrue($multiOk->pronto());

        $aee = $this->turma(['class_type' => 5, 'inep_stage_code' => null]);
        $semAtividade = Registro60::para($this->escola(), $this->matricula(['other_schooling_space' => null]), $aee, $this->censo(), null, null, 2026);
        $this->assertContains('60.10: Aluno em turma de AEE: marque pelo menos uma atividade do AEE na educação especial.', $this->mensagens($semAtividade->pendencias));
        $this->assertNull($semAtividade->valores[21]);
        $comAtividade = Registro60::para($this->escola(), $this->matricula(), $aee, $this->censo(), new StudentSpecialEducation(['aee_activities' => ['braille']]), null, 2026);
        $this->assertSame('1', $comAtividade->valores[17]);
        $this->assertTrue($comAtividade->pronto());
    }
}
