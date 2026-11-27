<?php

namespace Tests\Unit\Import;

use App\Import\Students\ImportText;
use App\Import\Xlsx\XlsxReader;
use App\Import\Xlsx\XlsxReadException;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ImportTextTest extends TestCase
{
    private function reference(): DateTimeImmutable
    {
        return new DateTimeImmutable('2027-07-30', new DateTimeZone('UTC'));
    }

    /** @return array<string, array{mixed, string}> */
    public static function datas(): array
    {
        return [
            'dd/mm/aaaa' => ['03/06/2023', '2023-06-03'],
            'd/m/aaaa' => ['2/4/2021', '2021-04-02'],
            'dd/mm/aa' => ['21/07/22', '2022-07-21'],
            'ano de dois digitos do seculo passado' => ['05/03/98', '1998-03-05'],
            'dd-mm-aaaa' => ['16-11-2020', '2020-11-16'],
            'aaaa-mm-dd' => ['2019-11-29', '2019-11-29'],
            'serie do Excel como inteiro' => [43970, '2020-05-19'],
            'serie do Excel como texto' => ['43970', '2020-05-19'],
            'data do Excel' => [new DateTimeImmutable('2018-08-30 00:00:00'), '2018-08-30'],
        ];
    }

    #[DataProvider('datas')]
    public function test_formatos_de_data_que_as_escolas_usam(mixed $valor, string $esperado): void
    {
        $resultado = ImportText::birthDate($valor, $this->reference());

        $this->assertNull($resultado['issue']);
        $this->assertSame($esperado, $resultado['date']?->format('Y-m-d'));
    }

    public function test_nascimento_preserva_data_civil_e_limite_do_dia_de_referencia(): void
    {
        $reference = new DateTimeImmutable('2027-07-30 00:00:00', new DateTimeZone('Asia/Tokyo'));
        foreach (['30/07/2027', new DateTimeImmutable('2027-07-30 00:00:00', new DateTimeZone('Asia/Tokyo'))] as $value) {
            $result = ImportText::birthDate($value, $reference);
            $this->assertNull($result['issue']);
            $this->assertSame('2027-07-30', $result['date']?->format('Y-m-d'));
        }

        $future = ImportText::birthDate(new DateTimeImmutable('2027-07-31 00:00:00', new DateTimeZone('Asia/Tokyo')), $reference);
        $this->assertNull($future['date']);
        $this->assertNotNull($future['issue']);

        $missing = ImportText::birthDate(null, $reference);
        $this->assertNull($missing['date']);
        $this->assertNotNull($missing['issue']);
    }

    public function test_idade_nao_vira_data(): void
    {
        $resultado = ImportText::birthDate('8 anos', $this->reference());

        $this->assertNull($resultado['date']);
        $this->assertSame(8, $resultado['age']);
        $this->assertStringContainsString('pendente', $resultado['issue']);
    }

    /** @return array<string, array{mixed}> */
    public static function datasInvalidas(): array
    {
        return [
            'dia impossivel' => ['31/02/2020'],
            'futuro' => ['01/01/2030'],
            'texto' => ['sem data'],
            'vazio' => [''],
            'nulo' => [null],
        ];
    }

    #[DataProvider('datasInvalidas')]
    public function test_data_invalida_fica_pendente_com_motivo(mixed $valor): void
    {
        $resultado = ImportText::birthDate($valor, $this->reference());

        $this->assertNull($resultado['date']);
        $this->assertNotNull($resultado['issue']);
    }

    public function test_turma_escrita_de_varios_jeitos_vira_a_mesma_chave(): void
    {
        foreach (['1º A', '1A', '1° ano A', '1º ANO - A', ' 1 a '] as $rotulo) {
            $this->assertSame('1A', ImportText::classKey($rotulo), $rotulo);
        }

        $this->assertSame('MIIA', ImportText::classKey('MII-A'));
        $this->assertNotSame(ImportText::classKey('1A'), ImportText::classKey('1B'));
    }

    public function test_endereco_numa_coluna_so_com_abreviacoes(): void
    {
        $this->assertSame(
            ['street' => 'Rua do Sol Nascente', 'number' => '1491', 'district' => 'Vila Nova Serena'],
            ImportText::address('R. do Sol Nascente, 1491 - Vila Nova Serena'),
        );
        $this->assertSame(
            ['street' => 'Avenida Serena', 'number' => '130', 'district' => 'Vila Esperança'],
            ImportText::address('Av. Serena, 130 - Vila Esperança'),
        );
        $this->assertSame(
            ['street' => 'Rua sem numero', 'number' => null, 'district' => null],
            ImportText::address('Rua sem numero'),
        );
    }

    public function test_ra_so_no_formato_da_sed(): void
    {
        $this->assertSame('999000000041-7/SP', ImportText::ra(' 999000000041-7/sp '));
        $this->assertNull(ImportText::ra('41'));
        $this->assertNull(ImportText::ra(null));
    }

    public function test_arquivo_que_nao_e_xlsx_e_recusado_com_mensagem_clara(): void
    {
        $falso = tempnam(sys_get_temp_dir(), 'imp');
        file_put_contents($falso, "Nome;Nascimento\nAna;01/01/2020\n");

        try {
            $this->expectException(XlsxReadException::class);
            $this->expectExceptionMessage('nao e uma planilha .xlsx valida');
            (new XlsxReader)->read($falso);
        } finally {
            @unlink($falso);
        }
    }
}
