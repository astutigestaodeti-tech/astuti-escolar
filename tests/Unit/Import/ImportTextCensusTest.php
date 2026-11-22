<?php

namespace Tests\Unit\Import;

use App\Import\Students\ImportText;
use App\Import\Students\StudentSpreadsheetPreview;
use PHPUnit\Framework\TestCase;

class ImportTextCensusTest extends TestCase
{
    public function test_sexo_cor_raca_e_nacionalidade_como_as_escolas_escrevem(): void
    {
        $this->assertSame('female', ImportText::sex('F'));
        $this->assertSame('male', ImportText::sex(' MASCULINO '));
        $this->assertNull(ImportText::sex('Outro'));

        $this->assertSame('indigena', ImportText::raceColor('INDÍGENA'));
        $this->assertSame('preta', ImportText::raceColor('preto'));
        $this->assertSame('nao_declarada', ImportText::raceColor('Não declarada'));
        $this->assertNull(ImportText::raceColor('Negra'));

        $this->assertSame('brasileira', ImportText::nationality('Brasileiro'));
        $this->assertSame('naturalizada', ImportText::nationality('Brasileira naturalizada'));
        $this->assertSame('estrangeira', ImportText::nationality('ESTRANGEIRO'));
        $this->assertNull(ImportText::nationality('Portuguesa'));
    }

    public function test_naturalidade_separa_cidade_e_uf_valida(): void
    {
        $this->assertSame(['city' => 'Recife', 'state' => 'PE'], ImportText::birthplace('RECIFE/PE'));
        $this->assertSame(['city' => 'São José dos Campos', 'state' => 'SP'], ImportText::birthplace('SÃO JOSÉ DOS CAMPOS - SP'));
        $this->assertSame(['city' => 'Belo Horizonte', 'state' => 'MG'], ImportText::birthplace('Belo Horizonte (MG)'));
        $this->assertSame(['city' => 'Monte Sereno', 'state' => null], ImportText::birthplace('Monte Sereno'));
        // "XX" não é UF: fica tudo como cidade e a UF continua pendente.
        $this->assertSame(['city' => null, 'state' => null], ImportText::birthplace('Cidade/XX'));
        $this->assertSame(['city' => null, 'state' => null], ImportText::birthplace(''));
    }

    public function test_cabecalhos_do_censo_nao_confundem_cpf_do_responsavel(): void
    {
        $this->assertSame('student_cpf', StudentSpreadsheetPreview::fieldForHeader('CPF do aluno'));
        $this->assertSame('cpf', StudentSpreadsheetPreview::fieldForHeader('CPF Resp.'));
        $this->assertSame('race_color', StudentSpreadsheetPreview::fieldForHeader('Cor/Raça'));
        $this->assertSame('birthplace', StudentSpreadsheetPreview::fieldForHeader('Naturalidade'));
        $this->assertSame('social_name', StudentSpreadsheetPreview::fieldForHeader('Nome social'));
        $this->assertSame('name', StudentSpreadsheetPreview::fieldForHeader('Nome'));
    }
}
