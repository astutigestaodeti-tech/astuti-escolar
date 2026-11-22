<?php

namespace Tests\Feature\Import;

use App\Import\Ocr\DocumentTableReader;
use App\Import\Ocr\OcrReadException;
use App\Import\Students\ClassOption;
use App\Import\Students\ImportedStudent;
use App\Import\Students\ImportIssue;
use App\Import\Students\ImportPreview;
use App\Import\Students\ImportTarget;
use App\Import\Students\StudentSpreadsheetPreview;
use App\Import\Xlsx\XlsxReader;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Leitura de listas em PDF e foto (OCR local) contra o gabarito do kit.
 *
 * Regra central: um dado lido errado nunca passa sem aviso. Cada aluno que
 * nao exige conferencia precisa bater com o gabarito em nome, data, RA e turma.
 */
class OcrDocumentImportTest extends TestCase
{
    private const KIT = __DIR__.'/../../Fixtures/kit-monte-sereno/';

    /** @var array<string, array<string, string>> */
    private array $expected = [];

    /** @var array<string, list<ClassOption>> */
    private array $classes = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['tesseract', 'pdftoppm', 'pdftotext', 'pdfinfo'] as $binary) {
            if (trim((string) shell_exec('command -v '.$binary)) === '') {
                if (getenv('GITHUB_ACTIONS') === 'true') {
                    $this->fail("O CI precisa do programa {$binary} para testar o OCR.");
                }
                $this->markTestSkipped("Programa {$binary} ausente neste computador.");
            }
        }

        foreach ($this->csv('alunos.csv') as $row) {
            $this->expected[$row['nome']] = $row;
        }
        foreach ($this->csv('turmas.csv') as $row) {
            $this->classes[$row['escola_id']][] = new ClassOption($row['turma_id'], $row['codigo'], $row['turno']);
        }
    }

    private function preview(string $school, string $file): ImportPreview
    {
        $sheets = (new DocumentTableReader)->read(self::KIT.'ocr/'.$file);

        return (new StudentSpreadsheetPreview(new XlsxReader))->fromSheets(
            $sheets,
            new ImportTarget($school, $this->classes[$school], new DateTimeImmutable('2027-07-30', new DateTimeZone('UTC'))),
        );
    }

    /** @return list<string> */
    private function confirmFields(ImportedStudent $student): array
    {
        return array_map(fn (ImportIssue $issue): string => $issue->field, $student->issuesOf(ImportIssue::CONFIRM));
    }

    /** Nenhum erro de leitura passa calado: ou bate com o gabarito, ou esta em conferir ou pendente. */
    private function assertNoSilentError(ImportPreview $preview): void
    {
        foreach ($preview->students as $student) {
            $confirm = $this->confirmFields($student);
            $row = $this->expected[$student->name] ?? null;

            if (in_array('leitura', $confirm, true)) {
                continue;
            }

            $this->assertNotNull($row, 'Nome lido errado sem pedir conferencia: '.$student->name);
            if ($student->birthDate !== null) {
                $this->assertSame($row['data_nascimento'], $student->birthDate->format('Y-m-d'), $student->name);
            } else {
                $this->assertContains('data_nascimento', array_map(fn (ImportIssue $i): string => $i->field, $student->issuesOf(ImportIssue::PENDING)));
            }
            if ($student->ra !== null) {
                $this->assertSame($row['ra_ficticio'], $student->ra, $student->name);
            }
            if (! in_array('turma', $confirm, true)) {
                $this->assertSame($row['turma_id'], $student->classId, $student->name);
            }
        }
    }

    public function test_pdf_digital_da_emei_le_as_40_criancas_sem_ocr_e_sem_conferencia_de_leitura(): void
    {
        $preview = $this->preview('E01', 'lista_digital_E01.pdf');

        $this->assertCount(40, $preview->students);
        foreach ($preview->students as $student) {
            $row = $this->expected[$student->name];
            $this->assertSame($row['data_nascimento'], $student->birthDate?->format('Y-m-d'));
            $this->assertSame($row['turma_id'], $student->classId);
            $this->assertNotContains('leitura', $this->confirmFields($student), 'Texto digital nao tem duvida de leitura.');
        }
    }

    public function test_lista_escaneada_da_emef_le_os_58_alunos_e_marca_as_leituras_duvidosas(): void
    {
        $preview = $this->preview('E02', 'lista_impressa_E02.pdf');

        $this->assertCount(58, $preview->students);
        $this->assertNoSilentError($preview);

        $doubtful = array_filter($preview->students, fn (ImportedStudent $s): bool => in_array('leitura', $this->confirmFields($s), true));
        $this->assertNotEmpty($doubtful, 'O documento de teste tem ruido: alguma leitura precisa de conferencia.');
        $this->assertLessThan(58, count($doubtful), 'Leituras limpas nao devem exigir conferencia.');

        foreach ($doubtful as $student) {
            $this->assertSame('conferir', $student->status());
        }
    }

    public function test_foto_da_lista_rural_le_os_alunos_com_serie_e_turma(): void
    {
        $preview = $this->preview('E03', 'foto_lista_E03.jpg');

        $this->assertCount(9, $preview->students);
        $this->assertNoSilentError($preview);
        foreach ($preview->students as $student) {
            $this->assertSame($this->expected[$student->name]['serie'] ?? null, $student->grade, $student->name);
        }
    }

    public function test_formato_e_reconhecido_pelo_conteudo_e_nao_pelo_nome(): void
    {
        $falso = tempnam(sys_get_temp_dir(), 'ocr').'.pdf';
        file_put_contents($falso, "Nome;Nascimento\nAna;01/01/2020\n");

        try {
            $this->expectException(OcrReadException::class);
            $this->expectExceptionMessage('Envie um PDF ou uma foto em JPG ou PNG.');
            (new DocumentTableReader)->read($falso);
        } finally {
            @unlink($falso);
        }
    }

    public function test_copias_temporarias_do_documento_sao_apagadas(): void
    {
        (new DocumentTableReader)->read(self::KIT.'ocr/lista_impressa_E02.pdf');

        $this->assertSame([], File::directories(storage_path('app/private/ocr')));
    }

    public function test_servidor_sem_tesseract_recebe_orientacao_clara(): void
    {
        Process::fake(['*' => Process::result(errorOutput: 'tesseract: not found', exitCode: 127)]);

        $this->expectException(OcrReadException::class);
        $this->expectExceptionMessage('O leitor de documentos nao esta instalado neste servidor');
        (new DocumentTableReader)->read(self::KIT.'ocr/foto_lista_E03.jpg');
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
