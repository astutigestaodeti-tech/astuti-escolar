<?php

namespace Tests\Unit\Educacenso;

use App\Educacenso\Leiaute;
use App\Educacenso\Texto;
use App\Educacenso\ValidadorDeCampo;
use Tests\TestCase;

class ValidadorDeCampoTest extends TestCase
{
    private function conferir(string $registro, int $campo, ?string $valor): array
    {
        return ValidadorDeCampo::conferir(Leiaute::do(2026)->campo($registro, $campo), $valor);
    }

    public function test_texto_fica_em_maiusculas_sem_acento_e_mantem_os_ordinais(): void
    {
        $this->assertSame('JOAO DA CONCEICAO', Texto::normalizar("  João   da\tConceição "));
        $this->assertSame('3º ANO A', Texto::normalizar('3º ano A'));
        $this->assertSame('EMEF 1ª SERIE', Texto::normalizar('EMEF 1ª série'));
        $this->assertNull(Texto::normalizar('   '));
        // Só o que o Inep documenta é convertido: o apóstrofo fica para a escola corrigir.
        $this->assertSame("D'AVILA", Texto::normalizar("D'Ávila"));
    }

    public function test_arquivo_em_iso_8859_1(): void
    {
        $this->assertSame("3\xBA ANO", Texto::iso88591('3º ANO'));
        $this->expectException(\InvalidArgumentException::class);
        Texto::iso88591('ANO 😀');
    }

    public function test_nome_da_pessoa_so_aceita_letras_e_espaco(): void
    {
        $this->assertSame([], $this->conferir('30', 6, 'JOAO DA SILVA'));
        $this->assertSame(['tem caractere não aceito pelo Educacenso: «\'»'], $this->conferir('30', 6, "MARIA D'AVILA"));
        $this->assertContains('não pode ter letras minúsculas nem acentos', $this->conferir('30', 6, 'José da Silva'));
        $this->assertSame(['é obrigatório'], $this->conferir('30', 6, null));
    }

    public function test_tamanho_fixo_e_maximo(): void
    {
        $this->assertSame([], $this->conferir('00', 2, '35123456'));
        $this->assertContains('deve ter exatamente 8 caracteres', $this->conferir('00', 2, '3512345'));
        $this->assertContains('deve ter só números', $this->conferir('00', 2, '3512345X'));
        $this->assertContains('deve ter no máximo 100 caracteres', $this->conferir('30', 6, str_repeat('A', 101)));
    }

    public function test_opcoes_da_planilha(): void
    {
        $this->assertSame([], $this->conferir('30', 12, '3'));
        $this->assertSame(['deve ser um destes códigos: 0, 1, 2, 3, 4, 5'], $this->conferir('30', 12, '9'));
        $this->assertSame([], $this->conferir('10', 139, '1'), 'campo numérico com opções listadas');
        $this->assertNotSame([], $this->conferir('10', 139, '2'));
    }

    public function test_data_no_formato_do_inep(): void
    {
        $this->assertSame([], $this->conferir('30', 7, '29/02/2016'));
        $this->assertNotSame([], $this->conferir('30', 7, '30/02/2016'));
        $this->assertNotSame([], $this->conferir('30', 7, '2016-02-29'));
    }

    public function test_horario_da_turma(): void
    {
        $this->assertSame([], $this->conferir('20', 8, '07:00-11:30'));
        $this->assertNotSame([], $this->conferir('20', 8, '07:03-11:30'), 'minutos múltiplos de 5');
        $this->assertSame(['o horário inicial deve ser menor que o final'], $this->conferir('20', 8, '13:00-07:00'));
        $this->assertNotSame([], $this->conferir('20', 8, '7:00-11:30'));
    }

    public function test_email_segue_a_regra_e_os_caracteres_do_leiaute(): void
    {
        $this->assertSame([], $this->conferir('00', 17, 'EMEF.CAMINHOS@EDUCACAO.GOV.BR'));
        $this->assertNotSame([], $this->conferir('00', 17, 'SEM-ARROBA.GOV.BR'));
        // O regex do Inep aceita hífen, mas a coluna de caracteres permitidos não: os dois valem.
        $this->assertSame(['tem caractere não aceito pelo Educacenso: «-»'], $this->conferir('00', 17, 'ESCOLA@MONTE-SERENO.GOV.BR'));
    }

    public function test_campos_que_so_o_inep_preenche_vao_vazios(): void
    {
        $this->assertSame([], $this->conferir('60', 7, null));
        $this->assertNotSame([], $this->conferir('60', 7, '123'));
    }

    public function test_separador_e_quebra_de_linha_nunca_entram_no_valor(): void
    {
        $this->assertSame(['contém separador ou quebra de linha'], $this->conferir('20', 5, 'TURMA|A'));
        $this->assertSame(['contém separador ou quebra de linha'], $this->conferir('20', 5, "TURMA\nA"));
    }
}
