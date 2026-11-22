<?php

namespace Tests\Unit\Import;

use App\Import\Students\ClassOption;
use App\Import\Students\ImportIssue;
use App\Import\Students\ImportTarget;
use App\Import\Students\StudentSpreadsheetPreview;
use App\Import\Xlsx\XlsxReader;
use App\Import\Xlsx\XlsxSheet;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class StudentAddressPreviewTest extends TestCase
{
    public function test_colunas_individuais_de_endereco_geram_cep_formatado_e_zona_normalizada(): void
    {
        $preview = (new StudentSpreadsheetPreview(new XlsxReader))->fromSheets([
            new XlsxSheet('Alunos', [
                1 => ['Nome', 'Data de nascimento', 'Turma', 'Logradouro', 'Número', 'Bairro', 'CEP', 'Zona de residência'],
                2 => ['Ana Clara Fictícia', '01/02/2018', '1A', 'Rua das Flores', '45', 'Centro', '01234567', 'Urbana'],
            ]),
        ], $this->target());

        $this->assertCount(1, $preview->students);
        $student = $preview->students[0];
        $this->assertSame('Rua das Flores', $student->street);
        $this->assertSame('45', $student->number);
        $this->assertSame('Centro', $student->district);
        $this->assertSame('01234-567', $student->postalCode);
        $this->assertSame('urban', $student->residenceZone);
    }

    public function test_cep_e_zona_invalidos_nao_viram_endereco_e_sao_marcados_pendentes(): void
    {
        $preview = (new StudentSpreadsheetPreview(new XlsxReader))->fromSheets([
            new XlsxSheet('Alunos', [
                1 => ['Nome', 'Data de nascimento', 'Turma', 'Endereço completo', 'CEP', 'Zona de residência'],
                2 => ['Breno Oliveira Fictício', '03/04/2018', '1A', 'R. do Sol, 10 - Centro', '1234', 'Indefinida'],
            ]),
        ], $this->target());

        $student = $preview->students[0];
        $this->assertSame('Rua do Sol', $student->street);
        $this->assertSame('10', $student->number);
        $this->assertSame('Centro', $student->district);
        $this->assertNull($student->postalCode);
        $this->assertNull($student->residenceZone);
        $pending = array_map(fn ($issue): string => $issue->field, $student->issuesOf(ImportIssue::PENDING));
        $this->assertContains('cep', $pending);
        $this->assertContains('zona_residencia', $pending);
    }

    public function test_mora_em_nao_substitui_bairro_do_endereco_completo(): void
    {
        $preview = (new StudentSpreadsheetPreview(new XlsxReader))->fromSheets([
            new XlsxSheet('Alunos', [
                1 => ['Nome', 'Data de nascimento', 'Turma', 'Endereço completo', 'Mora em'],
                2 => ['Ana Clara Fictícia', '01/02/2018', '1A', 'Rua das Flores, 45 - Centro', 'Zona Rural'],
            ]),
        ], $this->target());

        $this->assertSame('Centro', $preview->students[0]->district);
    }

    public function test_mora_em_sozinho_nao_define_bairro_automaticamente(): void
    {
        $preview = (new StudentSpreadsheetPreview(new XlsxReader))->fromSheets([
            new XlsxSheet('Alunos', [
                1 => ['Nome', 'Data de nascimento', 'Turma', 'Endereço completo', 'Mora em'],
                2 => ['Ana Clara Fictícia', '01/02/2018', '1A', 'Rua das Flores, 45', 'Zona Rural'],
            ]),
        ], $this->target());

        $this->assertNull($preview->students[0]->district);
    }

    public function test_bairro_separado_divergente_nao_substitui_bairro_do_endereco_completo(): void
    {
        $preview = (new StudentSpreadsheetPreview(new XlsxReader))->fromSheets([
            new XlsxSheet('Alunos', [
                1 => ['Nome', 'Data de nascimento', 'Turma', 'Endereço completo', 'Bairro'],
                2 => ['Ana Clara Fictícia', '01/02/2018', '1A', 'Rua das Flores, 45 - Centro', 'Jardim Novo'],
            ]),
        ], $this->target());

        $student = $preview->students[0];
        $this->assertNull($student->district);
        $pending = array_map(fn ($issue): string => $issue->field, $student->issuesOf(ImportIssue::PENDING));
        $this->assertContains('endereco', $pending);
    }

    public function test_bairro_separado_completa_endereco_sem_bairro_na_coluna_principal(): void
    {
        $preview = (new StudentSpreadsheetPreview(new XlsxReader))->fromSheets([
            new XlsxSheet('Alunos', [
                1 => ['Nome', 'Data de nascimento', 'Turma', 'Endereço completo', 'Bairro'],
                2 => ['Ana Clara Fictícia', '01/02/2018', '1A', 'Rua das Flores, 45', 'Centro'],
            ]),
        ], $this->target());

        $this->assertSame('Centro', $preview->students[0]->district);
    }

    public function test_bairro_divergente_na_segunda_linha_repetida_nao_e_aproveitado(): void
    {
        $preview = (new StudentSpreadsheetPreview(new XlsxReader))->fromSheets([
            new XlsxSheet('Alunos', [
                1 => ['Nome', 'Data de nascimento', 'Turma', 'Endereço completo', 'Bairro'],
                2 => ['Ana Clara Fictícia', '01/02/2018', '1A', 'Rua das Flores, 45 - Centro', 'Centro'],
                3 => ['Ana Clara Fictícia', '01/02/2018', '1A', 'Rua das Flores, 45 - Jardim Novo', 'Centro'],
            ]),
        ], $this->target());

        $this->assertCount(1, $preview->students);
        $student = $preview->students[0];
        $this->assertCount(2, $student->sources);
        $this->assertNull($student->district);
        $this->assertContains('endereco', array_map(fn (ImportIssue $issue): string => $issue->field, $student->issuesOf(ImportIssue::PENDING)));
        $this->assertContains('Bairros divergentes nas colunas de endereço. O bairro não foi importado; confira na ficha.', array_map(fn (ImportIssue $issue): string => $issue->message, $student->issuesOf(ImportIssue::PENDING)));
    }

    public function test_bairro_divergente_na_primeira_linha_repetida_nao_e_repreenchido(): void
    {
        $preview = (new StudentSpreadsheetPreview(new XlsxReader))->fromSheets([
            new XlsxSheet('Alunos', [
                1 => ['Nome', 'Data de nascimento', 'Turma', 'Endereço completo', 'Bairro'],
                2 => ['Ana Clara Fictícia', '01/02/2018', '1A', 'Rua das Flores, 45 - Jardim Novo', 'Centro'],
                3 => ['Ana Clara Fictícia', '01/02/2018', '1A', 'Rua das Flores, 45 - Centro', 'Centro'],
            ]),
        ], $this->target());

        $this->assertNull($preview->students[0]->district);
        $this->assertContains('endereco', array_map(fn (ImportIssue $issue): string => $issue->field, $preview->students[0]->issuesOf(ImportIssue::PENDING)));
    }

    public function test_bairros_diferentes_em_linhas_repetidas_ficam_pendentes(): void
    {
        $preview = (new StudentSpreadsheetPreview(new XlsxReader))->fromSheets([
            new XlsxSheet('Alunos', [
                1 => ['Nome', 'Data de nascimento', 'Turma', 'Endereço completo'],
                2 => ['Ana Clara Fictícia', '01/02/2018', '1A', 'Rua das Flores, 45 - Centro'],
                3 => ['Ana Clara Fictícia', '01/02/2018', '1A', 'Rua das Flores, 45 - Jardim Novo'],
            ]),
        ], $this->target());

        $student = $preview->students[0];
        $this->assertNull($student->district);
        $this->assertContains('endereco', array_map(fn (ImportIssue $issue): string => $issue->field, $student->issuesOf(ImportIssue::PENDING)));
    }

    private function target(): ImportTarget
    {
        return new ImportTarget('escola-ficticia', [
            new ClassOption('turma-ficticia', '1A', 'Manhã'),
        ], new DateTimeImmutable('2026-11-22'));
    }
}
