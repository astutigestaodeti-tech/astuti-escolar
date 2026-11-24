<?php

namespace Tests\Unit\Educacenso;

use App\Educacenso\Registro20;
use App\Educacenso\Tabelas;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolClassCensus;
use Tests\TestCase;

class Registro20Test extends TestCase
{
    private function censo(array $dados = []): SchoolClassCensus
    {
        $censo = new SchoolClassCensus([
            'inep_stage_code' => 16, 'mediation' => 1, 'class_type' => 6,
            'schedule_monday' => '07:00-11:30', 'schedule_tuesday' => '07:00-11:30', 'schedule_wednesday' => '07:00-11:30',
            'schedule_thursday' => '07:00-11:30', 'schedule_friday' => '07:00-11:30',
            'differentiated_location' => 0, 'special_class' => false, 'organization' => 1, 'alternance' => false,
            'curricular_areas' => [3, 5, 6, 10, 11, 12, 13], 'bilingual_deaf' => false, ...$dados,
        ]);
        $censo->census_code = 'ABCDEFGHIJ0123456789';

        return $censo;
    }

    private function registro(?SchoolClassCensus $censo, int $alunos = 20, int $professores = 1, string $nome = '3º Ano A'): Registro20
    {
        return Registro20::para(new School(['name' => 'EMEF Teste', 'inep_code' => '35000001']), new SchoolClass(['name' => $nome]), $censo, $alunos, $professores, 2026);
    }

    private function mensagens(Registro20 $registro): array
    {
        return array_map(fn ($p): string => ($p->campo ?? '-').': '.$p->mensagem, $registro->pendencias);
    }

    public function test_turma_regular_completa_fica_pronta_com_66_campos(): void
    {
        $registro = $this->registro($this->censo());

        $this->assertSame([], $this->mensagens($registro));
        $this->assertTrue($registro->pronto());
        $this->assertCount(66, $registro->valores);
        $this->assertSame('20', $registro->valores[1]);
        $this->assertSame('ABCDEFGHIJ0123456789', $registro->valores[3]);
        $this->assertNull($registro->valores[4], 'código Inep da turma só vem na exportação');
        $this->assertSame('3º ANO A', $registro->valores[5]);
        $this->assertNull($registro->valores[7], 'domingo sem aula');
        $this->assertSame('07:00-11:30', $registro->valores[8]);
        $this->assertSame('6', $registro->valores[14]);
        $this->assertSame('302', $registro->valores[23], 'etapa agregada vem da Tabela de Etapas');
        $this->assertSame('16', $registro->valores[24]);
        $this->assertSame('1', $registro->valores[41], 'matemática (código 3) é o campo 41');
        $this->assertSame('0', $registro->valores[39], 'química não oferecida vai 0');
        foreach ([25, 26, 27, 30, 31, 32, 33, 34, 35, 36, 37, 38] as $campo) {
            $this->assertNull($registro->valores[$campo], "campo {$campo} fora do escopo municipal");
        }
    }

    public function test_sem_dados_do_censo_a_turma_tem_pendencia(): void
    {
        $registro = $this->registro(null);
        $this->assertFalse($registro->pronto());
        $this->assertContains('-: Os dados da turma para o Censo ainda não foram preenchidos.', $this->mensagens($registro));
    }

    public function test_turma_sem_aluno_ou_professor_e_recusada(): void
    {
        $mensagens = $this->mensagens($this->registro($this->censo(), alunos: 0, professores: 0));
        $this->assertContains('-: A turma não tem aluno matriculado: o Educacenso recusa turma sem aluno.', $mensagens);
        $this->assertContains('-: A turma não tem professor vinculado: o Educacenso recusa turma sem profissional.', $mensagens);
    }

    public function test_educacao_infantil_nao_informa_organizacao_nem_areas(): void
    {
        $registro = $this->registro($this->censo(['inep_stage_code' => 1, 'organization' => null, 'curricular_areas' => []]), nome: 'Berçário I');

        $this->assertSame([], $this->mensagens($registro));
        $this->assertSame('301', $registro->valores[23]);
        $this->assertNull($registro->valores[28]);
        $this->assertNull($registro->valores[41]);

        $comAreas = $this->registro($this->censo(['inep_stage_code' => 2, 'organization' => null, 'curricular_areas' => [3]]));
        $this->assertFalse($comAreas->pronto());
    }

    public function test_area_fora_da_tabela_da_etapa_e_recusada(): void
    {
        // Química (1) não é habilitada nos anos iniciais; Estudos Sociais (28) não é nos anos finais.
        $this->assertContains('39: A área 1 não pode ser oferecida nesta etapa (Tabela de Regras de Áreas do Conhecimento).', $this->mensagens($this->registro($this->censo(['curricular_areas' => [1, 3]]))));
        $this->assertFalse($this->registro($this->censo(['inep_stage_code' => 19, 'curricular_areas' => [28]]))->pronto());
        $this->assertTrue($this->registro($this->censo(['inep_stage_code' => 19, 'curricular_areas' => [1, 3]]))->pronto());
    }

    public function test_organizacao_e_alternancia_seguem_o_anexo_6(): void
    {
        $this->assertFalse($this->registro($this->censo(['organization' => 2]))->pronto(), 'período semestral não vale no 3º ano');
        $this->assertFalse($this->registro($this->censo(['alternance' => true]))->pronto(), 'alternância não vale no 3º ano');
        $this->assertTrue($this->registro($this->censo(['inep_stage_code' => 19, 'alternance' => true]))->pronto());
    }

    public function test_combinacoes_do_anexo_7(): void
    {
        $this->assertFalse($this->registro($this->censo(['mediation' => 2]))->pronto(), 'semipresencial só na EJA');
        $semipresencial = $this->censo(['inep_stage_code' => 70, 'mediation' => 2, 'organization' => 1, 'curricular_areas' => [3, 6]]);
        foreach (array_keys(SchoolClassCensus::DAYS) as $dia) {
            $semipresencial->{'schedule_'.$dia} = null;
        }
        $this->assertSame([], $this->mensagens($this->registro($semipresencial)));
        $this->assertFalse($this->registro($this->censo(['inep_stage_code' => 56, 'class_type' => 9, 'complementary_activities' => [11002]]))->pronto(), 'multietapa não tem curricular com atividade complementar');
    }

    public function test_presencial_precisa_de_horario_e_turma_a_distancia_nao_tem(): void
    {
        $sem = $this->censo();
        foreach (array_keys(SchoolClassCensus::DAYS) as $dia) {
            $sem->{'schedule_'.$dia} = null;
        }
        $this->assertContains('8: Informe o horário de pelo menos um dia da semana para turma presencial.', $this->mensagens($this->registro($sem)));
        $this->assertNotSame([], $this->mensagens($this->registro($this->censo(['schedule_monday' => '07:00-07:00']))));
    }

    public function test_turma_de_aee_e_de_atividade_complementar(): void
    {
        $aee = $this->censo(['class_type' => 5, 'inep_stage_code' => null, 'special_class' => null, 'organization' => null, 'curricular_areas' => []]);
        $registro = $this->registro($aee, nome: 'AEE Sala de Recursos');
        $this->assertSame([], $this->mensagens($registro));
        $this->assertNull($registro->valores[23]);
        $this->assertNull($registro->valores[24]);

        $atividade = $this->censo(['class_type' => 4, 'inep_stage_code' => null, 'special_class' => null, 'organization' => null, 'curricular_areas' => [], 'complementary_activities' => [11002, 14001]]);
        $registro = $this->registro($atividade, nome: 'Coral');
        $this->assertSame([], $this->mensagens($registro));
        $this->assertSame('11002', $registro->valores[15]);
        $this->assertSame('14001', $registro->valores[16]);
        $this->assertNull($registro->valores[17]);

        $this->assertFalse($this->registro($this->censo(['class_type' => 4, 'inep_stage_code' => null, 'special_class' => null, 'organization' => null, 'curricular_areas' => [], 'complementary_activities' => [99999]]))->pronto());
        $this->assertFalse($this->registro($this->censo(['class_type' => 4, 'inep_stage_code' => null, 'special_class' => null, 'organization' => null, 'curricular_areas' => []]))->pronto(), 'sem atividade');
    }

    public function test_nome_da_turma_com_caractere_nao_aceito_vira_pendencia(): void
    {
        $this->assertContains('5: Nome da Turma: tem caractere não aceito pelo Educacenso: «/».', $this->mensagens($this->registro($this->censo(), nome: '3º Ano A/B')));
    }

    public function test_tabelas_conferem_anexo_6_com_a_regra_do_campo_29(): void
    {
        // Regra 2 do campo 29: alternância deve ser 0 nas etapas 1, 2, 3, 14 a 18 e 56; o Anexo 6 tem de concordar.
        $tabelas = Tabelas::do(2026);
        foreach ([1, 2, 3, 14, 15, 16, 17, 18, 56] as $etapa) {
            $this->assertFalse($tabelas->alternanciaPermitida($etapa), "etapa {$etapa}");
        }
        foreach (Registro20::ETAPAS_DA_REDE as $etapa) {
            $this->assertNotNull($tabelas->etapa($etapa), "etapa {$etapa} existe na tabela oficial");
        }
        $this->assertSame([], $tabelas->areasPermitidas(1));
        $this->assertSame([3, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 16, 23, 26, 27, 28, 30, 31, 99], $tabelas->areasPermitidas(14));
    }
}
