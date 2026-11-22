<?php

namespace App\Console\Commands;

use App\Import\Ocr\DocumentTableReader;
use App\Import\Ocr\OcrReadException;
use App\Import\Students\ClassOption;
use App\Import\Students\ImportedStudent;
use App\Import\Students\ImportIssue;
use App\Import\Students\ImportPreview;
use App\Import\Students\ImportTarget;
use App\Import\Students\StudentSpreadsheetPreview;
use App\Import\Xlsx\XlsxReadException;
use App\Models\Platform\Municipality;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Tenancy\TenantContext;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Command;

/**
 * Previa da importacao de alunos de UMA escola. Somente leitura: mostra o que
 * seria cadastrado, o que precisa de conferencia e o que ficaria pendente.
 */
class PreviewStudentImport extends Command
{
    protected $signature = 'astuti:importar-alunos-previa
        {arquivo : Planilha .xlsx, PDF (digital ou escaneado) ou foto JPG/PNG da lista da escola}
        {--escola= : Identificador (id) ou codigo INEP da escola de destino}
        {--ano= : Ano letivo das turmas (padrao: o ano letivo aberto mais recente)}
        {--municipio= : Slug do municipio (padrao: o unico municipio da instalacao)}
        {--referencia= : Data de referencia AAAA-MM-DD para validar nascimentos (padrao: hoje)}';

    protected $description = 'Mostra a previa da importacao de alunos de uma escola, sem gravar nada.';

    public function handle(StudentSpreadsheetPreview $previewer, TenantContext $context): int
    {
        $path = (string) $this->argument('arquivo');
        $schoolOption = trim((string) $this->option('escola'));
        if ($schoolOption === '') {
            $this->error('Informe a escola de destino com --escola=. A escola nunca e deduzida do nome do arquivo.');

            return self::FAILURE;
        }

        $reference = $this->reference();
        if ($reference === null) {
            $this->error('Data de referencia invalida. Use AAAA-MM-DD.');

            return self::FAILURE;
        }

        $slug = $this->option('municipio');
        $municipality = $slug !== null
            ? Municipality::query()->where('slug', $slug)->where('status', 'active')->first()
            : (Municipality::query()->where('status', 'active')->count() === 1 ? Municipality::query()->where('status', 'active')->first() : null);
        if ($municipality === null) {
            $this->error('Municipio nao encontrado. Nesta instalacao, informe --municipio= se houver mais de um.');

            return self::FAILURE;
        }

        $result = $context->runFor($municipality, function () use ($schoolOption, $previewer, $path, $reference): ImportPreview|string {
            $school = School::query()
                ->where(fn ($query) => $query->where('inep_code', $schoolOption)
                    ->when(preg_match('/^[0-9a-f-]{36}$/i', $schoolOption) === 1, fn ($q) => $q->orWhere('id', $schoolOption)))
                ->first();
            if ($school === null) {
                return 'Escola nao encontrada neste municipio.';
            }

            $year = $this->schoolYear();
            if ($year === null) {
                return 'Nenhum ano letivo aberto encontrado. Informe --ano=.';
            }

            $classes = SchoolClass::query()
                ->where('school_id', $school->id)
                ->where('school_year_id', $year->id)
                ->orderBy('name')
                ->get()
                ->map(fn (SchoolClass $class): ClassOption => new ClassOption($class->id, $class->name, $class->shift))
                ->all();
            if ($classes === []) {
                return 'A escola nao tem turmas cadastradas no ano letivo '.$year->year.'. Cadastre as turmas antes de importar os alunos.';
            }

            $this->info('Escola: '.$school->name.' | Ano letivo: '.$year->year.' | Turmas: '.implode(', ', array_map(fn (ClassOption $c): string => $c->code, $classes)));

            try {
                $target = new ImportTarget($school->id, $classes, $reference);
                $head = is_file($path) ? (string) file_get_contents($path, false, null, 0, 4) : '';

                // Planilha (.xlsx e um zip) vai direto; PDF e foto passam pela leitura local.
                return $head === "PK\x03\x04"
                    ? $previewer->fromFile($path, $target)
                    : $previewer->fromSheets(app(DocumentTableReader::class)->read($path), $target);
            } catch (XlsxReadException|OcrReadException $exception) {
                return $exception->getMessage();
            }
        });

        if (is_string($result)) {
            $this->error($result);

            return self::FAILURE;
        }

        $this->report($result);

        return self::SUCCESS;
    }

    private function reference(): ?DateTimeImmutable
    {
        $option = $this->option('referencia');
        if ($option === null) {
            return new DateTimeImmutable('today', new DateTimeZone('UTC'));
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $option, new DateTimeZone('UTC'));

        return $date !== false && $date->format('Y-m-d') === $option ? $date : null;
    }

    private function schoolYear(): ?SchoolYear
    {
        $query = SchoolYear::query();
        if ($this->option('ano') !== null) {
            return $query->where('year', (int) $this->option('ano'))->first();
        }

        return $query->where('status', '!=', 'completed')->orderByDesc('year')->first();
    }

    private function report(ImportPreview $preview): void
    {
        $rows = array_map(function (ImportedStudent $student): array {
            $issues = array_map(
                fn (ImportIssue $issue): string => ($issue->kind === ImportIssue::CONFIRM ? '[conferir] ' : '').$issue->message,
                array_filter($student->issues, fn (ImportIssue $issue): bool => $issue->field !== 'cep'),
            );

            return [
                $student->status(),
                $student->name,
                $student->birthDate?->format('d/m/Y') ?? '-',
                $student->ra ?? '-',
                implode("\n", $issues),
                implode("\n", $student->sources),
            ];
        }, $preview->students);

        $this->table(['Situacao', 'Aluno', 'Nascimento', 'RA', 'Conferir ou pendente', 'Origem'], $rows);

        foreach ($preview->ignored as $ignored) {
            $this->line('Ignorado - '.$ignored['source'].': '.$ignored['reason']);
        }

        $summary = $preview->summary();
        $this->newLine();
        $this->info(sprintf(
            'Total: %d alunos | prontos: %d | conferir: %d | com pendencias: %d | linhas ignoradas: %d',
            count($preview->students),
            $summary['pronto'],
            $summary['conferir'],
            $summary['pendente'],
            count($preview->ignored),
        ));
        $this->line('Nenhum dado foi gravado. O CEP nao vem em nenhuma planilha e fica pendente em todos os alunos.');
    }
}
