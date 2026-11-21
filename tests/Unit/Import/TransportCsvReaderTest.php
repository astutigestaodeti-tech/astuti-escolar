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
        $this->assertSame(['ra' => '999000000001-6/SP', 'name' => 'João Ferreira', 'status' => 'active', 'line' => 2], $rows[0]);
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
        $this->expectExceptionMessage('RA e situação');

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
}
