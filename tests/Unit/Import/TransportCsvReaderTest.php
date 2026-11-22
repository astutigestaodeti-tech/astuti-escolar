<?php

namespace Tests\Unit\Import;

use App\Import\Transport\TransportCsvException;
use App\Import\Transport\TransportCsvReader;
use PHPUnit\Framework\TestCase;

class TransportCsvReaderTest extends TestCase
{
    private function ler(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'astuti-csv-');
        file_put_contents($path, $content);

        try {
            return (new TransportCsvReader)->read($path);
        } finally {
            unlink($path);
        }
    }

    public function test_ler_csv_windows_1252_com_acentos_e_estados_canônicos(): void
    {
        $csv = "RA;NOME;SITUAÇÃO\r\n999000000001-6/SP;João Ferreira;SIM\r\n999000000002-8/SP;Érica Silva;NÃO\r\n999000000003-0/SP;Álvaro Dias;CANCELADO\r\n";
        $rows = $this->ler(iconv('UTF-8', 'Windows-1252', $csv));

        $this->assertCount(3, $rows);
        $this->assertSame(['ra' => '999000000001-6/SP', 'name' => 'João Ferreira', 'birth_date' => null, 'school' => null, 'status' => 'active', 'line' => 2], $rows[0]);
        $this->assertSame('not_requested', $rows[1]['status']);
        $this->assertSame('Érica Silva', $rows[1]['name']);
        $this->assertSame('cancelled', $rows[2]['status']);
    }

    public function test_ler_csv_utf8_com_virgula_e_bom(): void
    {
        $rows = $this->ler("\xEF\xBB\xBFRegistro do aluno,Nome do aluno,Utiliza transporte escolar\n999000000004-1/SP,João Pedro,Ativo\n");

        $this->assertCount(1, $rows);
        $this->assertSame('999000000004-1/SP', $rows[0]['ra']);
        $this->assertSame('active', $rows[0]['status']);
    }

    public function test_duplicidade_de_ra_bloqueia_lote_sem_escolha_arbitraria(): void
    {
        $this->expectException(TransportCsvException::class);
        $this->expectExceptionMessage('RA repetido');

        $this->ler("RA;SITUAÇÃO\n999000000001-6/SP;ATIVO\n999000000001-6/SP;CANCELADO\n");
    }

    public function test_rejeita_situacao_desconhecida_sem_assumir_status(): void
    {
        $this->expectException(TransportCsvException::class);
        $this->expectExceptionMessage('Situação de transporte desconhecida');

        $this->ler("RA;SITUAÇÃO\n999000000001-6/SP;A DEFINIR\n");
    }

    public function test_rejeita_arquivo_sem_colunas_essenciais(): void
    {
        $this->expectException(TransportCsvException::class);
        $this->expectExceptionMessage('O CSV precisa da coluna Situação e de uma forma de identificar o aluno');

        $this->ler("ALUNO;TELEFONE\nEstudante fictício;000000000\n");
    }

    public function test_rejeita_duas_colunas_de_status_sem_escolher_uma_arbitrariamente(): void
    {
        $this->expectException(TransportCsvException::class);
        $this->expectExceptionMessage('Cabeçalho ambíguo');

        $this->ler("RA;Status;Situação do Transporte\n999000000001-6/SP;ATIVO;CANCELADO\n");
    }

    public function test_rejeita_byte_indefinido_do_windows_1252_com_erro_de_validacao(): void
    {
        $this->expectException(TransportCsvException::class);
        $this->expectExceptionMessage('caracteres inválidos');

        $this->ler("RA;SITUAÇÃO\n999000000001-6/SP;SIM\x81\n");
    }

    public function test_rejeita_aspas_abertas_que_poderiam_ocultar_linhas_seguidas(): void
    {
        $this->expectException(TransportCsvException::class);
        $this->expectExceptionMessage('Aspas não fechadas');

        $this->ler("RA;SITUACAO;OBSERVACAO\n999000000001-6/SP;SIM;\"continua\n999000000002-8/SP;CANCELADO;outro aluno\n");
    }

    public function test_aceita_campo_extra_entre_aspas_com_separador_sem_importar_essa_informacao(): void
    {
        $rows = $this->ler("RA;SITUACAO;OBSERVACAO\n999000000001-6/SP;SIM;\"uso; eventual\"\n");

        $this->assertCount(1, $rows);
        $this->assertSame('active', $rows[0]['status']);
        $this->assertArrayNotHasKey('observacao', $rows[0]);
    }

    public function test_le_a_lista_da_secretaria_do_kit_sem_ra(): void
    {
        $rows = (new TransportCsvReader)->read(__DIR__.'/../../Fixtures/kit-monte-sereno/SME_lista_transporte_escolar.csv');

        $this->assertCount(28, $rows);
        $this->assertSame(['active' => 26, 'cancelled' => 2], array_count_values(array_column($rows, 'status')));
        $this->assertSame([null], array_values(array_unique(array_column($rows, 'ra'))));
        $this->assertSame(['ra' => null, 'name' => 'Yasmin Costa Dias', 'birth_date' => '2023-10-01', 'school' => 'EMEI AURORA', 'status' => 'active', 'line' => 2], $rows[0]);
        $this->assertNotContains(null, array_column($rows, 'birth_date'));
    }

    public function test_sem_ra_nem_nome_explica_as_colunas_reconhecidas(): void
    {
        $this->expectException(TransportCsvException::class);
        $this->expectExceptionMessage('Colunas reconhecidas: situação do transporte, escola.');

        $this->ler("SITUACAO;ESCOLA;LINHA\nATIVO;EMEF;LINHA 01\n");
    }

    public function test_data_impossivel_nao_vira_data(): void
    {
        $rows = $this->ler("NOME;DT NASC;SITUACAO\nAluno Ficticio;31/02/2019;SIM\n");

        $this->assertNull($rows[0]['birth_date']);
    }
}
