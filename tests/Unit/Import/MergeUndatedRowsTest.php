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

class MergeUndatedRowsTest extends TestCase
{
    private function preview(array $rows): array
    {
        $target = new ImportTarget('E03', [new ClassOption('t1', 'MULTI-1', 'Manhã')], new DateTimeImmutable('2026-11-23'));

        return (new StudentSpreadsheetPreview(new XlsxReader))->fromSheets([new XlsxSheet('Alunos', $rows)], $target)->students;
    }

    public function test_linha_so_com_idade_junta_com_a_linha_datada_do_mesmo_nome(): void
    {
        $students = $this->preview([
            1 => ['Nome', 'Nascimento', 'Turma'],
            2 => ['Emanuelly Rodrigues Lopes', '6 anos', 'MULTI-1'],
            3 => ['EMANUELLY RODRIGUES LOPES', '14/06/2020', 'MULTI-1'],
        ]);

        $this->assertCount(1, $students);
        $this->assertSame('2020-06-14', $students[0]->birthDate?->format('Y-m-d'));
        $fields = array_map(fn (ImportIssue $issue): string => $issue->field, $students[0]->issues);
        $this->assertContains('duplicidade', $fields);
        $this->assertNotContains('data_nascimento', $fields);
    }

    public function test_duas_datas_diferentes_para_o_mesmo_nome_nao_juntam_a_linha_sem_data(): void
    {
        $students = $this->preview([
            1 => ['Nome', 'Nascimento', 'Turma'],
            2 => ['Laura Ribeiro Pinto', '7 anos', 'MULTI-1'],
            3 => ['Laura Ribeiro Pinto', '23/02/2020', 'MULTI-1'],
            4 => ['Laura Ribeiro Pinto', '01/03/2019', 'MULTI-1'],
        ]);

        $this->assertCount(3, $students);
    }
}
