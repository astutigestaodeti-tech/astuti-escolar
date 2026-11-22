<?php

namespace Tests\Unit\Import;

use App\Import\Students\ClassOption;
use App\Import\Students\ImportedStudent;
use App\Import\Students\ImportIssue;
use App\Import\Students\ImportPreview;
use App\Import\Students\ImportTarget;
use App\Import\Students\ImportText;
use App\Import\Students\StudentSpreadsheetPreview;
use App\Import\Xlsx\XlsxReader;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/**
 * Criterio do kit Monte Sereno: importar as planilhas da pasta 02 e chegar
 * ao gabarito (pasta 01) so com conferencias humanas, sem redigitacao, e sem
 * inventar o que o arquivo nao traz.
 */
final class KitMonteSerenoStudentImportTest extends TestCase
{
    private const KIT = __DIR__.'/../../Fixtures/kit-monte-sereno/';

    private const FILES = [
        'E01' => 'E01_EMEI_Aurora_lista_criancas_2027.xlsx',
        'E02' => 'E02_EMEF_Sebastiao_alunos_2027.xlsx',
        'E03' => 'E03_Rural_Palmeiras.xlsx',
    ];

    /** @var array<string, array<string, string>> gabarito por nome */
    private array $expected = [];

    /** @var array<string, ImportPreview> */
    private array $previews = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach ($this->csv('alunos.csv') as $row) {
            $this->expected[$row['nome']] = $row;
        }

        $classes = [];
        foreach ($this->csv('turmas.csv') as $row) {
            $classes[$row['escola_id']][] = new ClassOption($row['turma_id'], $row['codigo'], $row['turno']);
        }

        $preview = new StudentSpreadsheetPreview(new XlsxReader);
        $reference = new DateTimeImmutable('2027-07-30', new DateTimeZone('UTC'));
        foreach (self::FILES as $school => $file) {
            $this->previews[$school] = $preview->fromFile(self::KIT.$file, new ImportTarget($school, $classes[$school], $reference));
        }
    }

    public function test_as_tres_escolas_chegam_aos_115_alunos_do_gabarito_sem_sobra_nem_falta(): void
    {
        $this->assertCount(40, $this->previews['E01']->students);
        $this->assertCount(58, $this->previews['E02']->students);
        $this->assertCount(17, $this->previews['E03']->students);

        $names = array_map(fn (ImportedStudent $s): string => $s->name, $this->all());
        $this->assertSame([], array_values(array_diff($names, array_keys($this->expected))), 'Nomes que nao existem no gabarito.');
        $this->assertSame([], array_values(array_diff(array_keys($this->expected), $names)), 'Alunos do gabarito que faltaram.');
        $this->assertCount(115, array_unique($names));
    }

    public function test_cada_aluno_cai_na_escola_e_na_turma_do_gabarito(): void
    {
        foreach ($this->all() as $student) {
            $row = $this->expected[$student->name];
            $this->assertSame($row['escola_id'], $student->schoolId, $student->name);
            $this->assertSame($row['turma_id'], $student->classId, $student->name);
        }
    }

    public function test_datas_em_todos_os_formatos_batem_e_idade_sozinha_fica_pendente(): void
    {
        $onlyAge = 0;
        foreach ($this->all() as $student) {
            $row = $this->expected[$student->name];
            if ($student->birthDate === null) {
                $onlyAge++;
                $this->assertArrayHasKey('idade_informada', $student->hints, $student->name);
                $this->assertSame('E03', $student->schoolId, 'So a escola rural manda idade no lugar da data.');
                $this->assertContains('data_nascimento', $this->fields($student, ImportIssue::PENDING));

                continue;
            }

            $this->assertSame($row['data_nascimento'], $student->birthDate->format('Y-m-d'), $student->name);
        }

        // Nenhuma data inventada: so as linhas com "N anos" ficam sem data.
        $this->assertSame(6, $onlyAge);
    }

    public function test_nomes_em_caixa_alta_voltam_com_acentos_e_espacos_sobrando_somem(): void
    {
        $e01 = array_map(fn (ImportedStudent $s): string => $s->name, $this->previews['E01']->students);
        $this->assertContains('João Pedro Nunes Vieira', $e01);
        $this->assertContains('Vitória Lopes Machado', $e01);
        $this->assertContains('Agatha Araújo Pinto', $e01);

        $this->assertSame('Ana Maria da Silva dos Santos', ImportText::personName('  ANA MARIA DA  SILVA DOS SANTOS '));
        $this->assertSame('Helena Lima Toledo', ImportText::personName('Helena Lima Toledo '));
    }

    public function test_ra_transporte_e_endereco_conferem_quando_a_planilha_traz(): void
    {
        foreach ($this->all() as $student) {
            $row = $this->expected[$student->name];

            if ($student->ra !== null) {
                $this->assertSame($row['ra_ficticio'], $student->ra, $student->name);
            } else {
                $this->assertContains('ra', $this->fields($student, ImportIssue::PENDING), $student->name);
            }

            if ($student->usesTransport !== null) {
                $this->assertSame($row['utiliza_transporte_escolar'] === 'Sim', $student->usesTransport, $student->name);
            }

            if ($student->schoolId === 'E02') {
                $this->assertSame([$row['logradouro'], $row['numero'], $row['bairro']], [$student->street, $student->number, $student->district], $student->name);
            }

            if ($student->district !== null) {
                $this->assertSame($row['bairro'], $student->district, $student->name);
            }

            if ($student->grade !== null) {
                $this->assertSame($row['serie'], $student->grade, $student->name);
            }
        }

        // A EMEI nao manda RA nem transporte: tudo pendente, nada inventado.
        foreach ($this->previews['E01']->students as $student) {
            $this->assertNull($student->ra);
            $this->assertNull($student->usesTransport);
            $this->assertContains('transporte', $this->fields($student, ImportIssue::PENDING));
        }

        // A escola rural informa a serie de cada aluno, diferente da turma multisseriada.
        foreach ($this->previews['E03']->students as $student) {
            $this->assertNotNull($student->grade);
        }
    }

    public function test_duplicidades_viram_conferencia_e_nao_juncao_silenciosa(): void
    {
        $confirm = array_values(array_filter(
            $this->previews['E02']->students,
            fn (ImportedStudent $s): bool => in_array('duplicidade', $this->fields($s, ImportIssue::CONFIRM), true),
        ));

        $this->assertEqualsCanonicalizing(['Laura Souza Gonçalves', 'Heitor Ramos Barbosa'], array_map(fn (ImportedStudent $s): string => $s->name, $confirm));
        foreach ($confirm as $student) {
            $this->assertCount(2, $student->sources);
            $this->assertSame('conferir', $student->status());
            // O RA que faltava numa das linhas vem da outra, e nao fica pendente.
            $this->assertSame($this->expected[$student->name]['ra_ficticio'], $student->ra);
            $this->assertNotContains('ra', $this->fields($student, ImportIssue::PENDING));
        }

        // Nenhum outro aluno do kit exige conferencia.
        $others = array_filter($this->all(), fn (ImportedStudent $s): bool => $s->status() === 'conferir' && ! in_array($s, $confirm, true));
        $this->assertSame([], array_values(array_map(fn (ImportedStudent $s): string => $s->name, $others)));
    }

    public function test_linha_de_total_e_linhas_em_branco_nao_viram_aluno(): void
    {
        $reasons = array_column($this->previews['E02']->ignored, 'reason');
        $this->assertCount(1, $reasons);
        $this->assertStringContainsString('Linha de total', $reasons[0]);
        $this->assertSame([], $this->previews['E01']->ignored);
        $this->assertSame([], $this->previews['E03']->ignored);
    }

    public function test_observacao_de_inclusao_fica_guardada_para_conferencia(): void
    {
        $withNotes = array_filter($this->previews['E01']->students, fn (ImportedStudent $s): bool => isset($s->hints['observacao']));
        $this->assertNotEmpty($withNotes);
        foreach ($withNotes as $student) {
            $this->assertContains('observacao', $this->fields($student, ImportIssue::PENDING));
        }
    }

    /** @return list<ImportedStudent> */
    private function all(): array
    {
        return array_merge(...array_map(fn (ImportPreview $p): array => $p->students, array_values($this->previews)));
    }

    /** @return list<string> */
    private function fields(ImportedStudent $student, string $kind): array
    {
        return array_map(fn (ImportIssue $issue): string => $issue->field, $student->issuesOf($kind));
    }

    /** @return list<array<string, string>> */
    private function csv(string $file): array
    {
        $handle = fopen(self::KIT.$file, 'r');
        $header = fgetcsv($handle, escape: '');
        $rows = [];
        while (($line = fgetcsv($handle, escape: '')) !== false) {
            $rows[] = array_combine($header, $line);
        }
        fclose($handle);

        return $rows;
    }
}
