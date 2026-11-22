<?php

namespace App\Http\Controllers;

use App\Audit\AuditTrail;
use App\Import\Ocr\DocumentTableReader;
use App\Import\Ocr\OcrReadException;
use App\Import\Students\ClassOption;
use App\Import\Students\ImportIssue;
use App\Import\Students\ImportTarget;
use App\Import\Students\StudentSpreadsheetPreview;
use App\Import\Xlsx\XlsxReadException;
use App\Models\Platform\Municipality;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Security\TwoFactorSession;
use Closure;
use DateTimeImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;

/**
 * Conferência humana antes da importação. A planilha nunca é armazenada.
 * A prévia viaja cifrada e autenticada no formulário por uma hora. Um recibo
 * municipal sem dados pessoais impede reaplicação do mesmo lote.
 */
class ConfirmedStudentImportController extends Controller
{
    private const MAX_STUDENTS = 500;

    public function index(Request $request, string $municipio, string $escola): View|RedirectResponse
    {
        [$municipality, $school] = $this->authorizeSchool($request, $escola);

        if ($redirect = $this->requireTwoFactor($request)) {
            return $redirect;
        }

        $years = SchoolYear::query()->whereIn('status', ['active', 'planned'])->orderByDesc('year')->get();

        return view('imports.upload', compact('municipality', 'school', 'years'));
    }

    public function preview(Request $request, string $municipio, string $escola, StudentSpreadsheetPreview $previewer): View
    {
        [$municipality, $school] = $this->authorizeSchool($request, $escola);
        $data = $request->validate([
            'arquivo' => ['required', 'file', 'max:10240', $this->documentRule()],
            'school_year_id' => ['required', 'uuid'],
        ]);

        $year = SchoolYear::query()->whereIn('status', ['active', 'planned'])->find($data['school_year_id']);
        if ($year === null) {
            throw ValidationException::withMessages(['school_year_id' => 'Escolha um ano letivo em andamento ou planejado.']);
        }

        $classes = $this->classes($school, $year);
        if ($classes === []) {
            throw ValidationException::withMessages(['school_year_id' => 'Cadastre as turmas desta escola antes de importar alunos.']);
        }

        try {
            $target = new ImportTarget($school->id, $classes, new DateTimeImmutable('now'));
            $path = $data['arquivo']->getRealPath();
            // Planilha vai direto; PDF e foto passam pela leitura local (OCR),
            // e as linhas de leitura duvidosa chegam marcadas para conferir.
            $preview = $this->signature($path) === 'xlsx'
                ? $previewer->fromFile($path, $target)
                : $previewer->fromSheets(app(DocumentTableReader::class)->read($path), $target);
        } catch (XlsxReadException|OcrReadException $exception) {
            throw ValidationException::withMessages(['arquivo' => $exception->getMessage()]);
        }

        if (count($preview->students) === 0 || count($preview->students) > self::MAX_STUDENTS) {
            throw ValidationException::withMessages(['arquivo' => 'O arquivo deve conter entre 1 e 500 alunos, numa tabela com o cabeçalho "Nome" ou "Aluno". Divida lotes maiores por escola.']);
        }

        $classNames = [];
        foreach ($classes as $class) {
            $classNames[$class->id] = $class->code;
        }

        $rows = [];
        foreach ($preview->students as $student) {
            $confirmation = [];
            $pending = [];
            foreach ($student->issues as $issue) {
                if ($issue->kind === ImportIssue::CONFIRM) {
                    $confirmation[] = ['field' => $issue->field, 'message' => $issue->message];
                } else {
                    // Observações e pistas pessoais não vão para a prévia persistível.
                    $pending[] = $issue->field === 'observacao'
                        ? 'Observação não importada nesta etapa.'
                        : $issue->message;
                }
            }

            $birthDate = $student->birthDate?->format('Y-m-d');
            $ra = $this->canonicalRa($student->ra);
            $match = $this->existingStatus($student->name, $birthDate, $ra);

            if ($match === 'existing') {
                $confirmation[] = ['field' => 'ja_cadastrado', 'message' => 'Já cadastrado: esta linha será ignorada.'];
            } elseif ($match === 'homonym') {
                $confirmation[] = ['field' => 'homonimo', 'message' => 'Possível homônimo. Confirme que é outra pessoa.'];
            }

            $rows[] = [
                'name' => $student->name,
                'birth_date' => $birthDate,
                'ra' => $ra,
                'class_id' => $student->classId,
                'class_name' => $classNames[$student->classId ?? ''] ?? null,
                'confirmation' => $confirmation,
                'pending' => $pending,
                'sources' => $student->sources,
            ];
        }

        $payload = [
            'version' => 1,
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'year_id' => $year->id,
            'actor_id' => $request->user()->id,
            'expires_at' => now()->addHour()->getTimestamp(),
            'rows' => $rows,
        ];
        $token = Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return view('imports.review', compact('municipality', 'school', 'year', 'rows', 'token'));
    }

    public function confirm(Request $request, string $municipio, string $escola): RedirectResponse
    {
        [$municipality, $school] = $this->authorizeSchool($request, $escola);

        try {
            return $this->confirmBatch($request, $municipality, $school);
        } catch (ValidationException $exception) {
            // A conferencia e a resposta de um POST: "voltar" para ela daria
            // 405. A recusa volta para a pagina de envio, com a mensagem.
            throw $exception->redirectTo(route('school.imports.students.index', ['municipio' => $municipality->slug, 'escola' => $school->id]));
        }
    }

    private function confirmBatch(Request $request, Municipality $municipality, School $school): RedirectResponse
    {
        $data = $request->validate([
            'lote' => ['required', 'string', 'max:500000'],
            'confirmados' => ['nullable', 'array', 'max:500'],
            'confirmados.*' => ['integer', 'min:0', 'max:499'],
            'homonimos' => ['nullable', 'array', 'max:500'],
            'leituras' => ['nullable', 'array', 'max:500'],
            'leituras.*' => ['integer', 'min:0', 'max:499'],
            'homonimos.*' => ['integer', 'min:0', 'max:499'],
        ]);

        try {
            $payload = json_decode(Crypt::decryptString($data['lote']), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            throw ValidationException::withMessages(['lote' => 'Prévia inválida ou alterada. Envie a planilha novamente.']);
        }

        if (! is_array($payload) || ($payload['version'] ?? null) !== 1
            || ! Str::isUuid($payload['id'] ?? '')
            || ($payload['school_id'] ?? null) !== $school->id
            || ($payload['actor_id'] ?? null) !== $request->user()->id
            || ! is_int($payload['expires_at'] ?? null) || $payload['expires_at'] < now()->getTimestamp()
            || ! is_array($payload['rows'] ?? null)
            || count($payload['rows']) < 1 || count($payload['rows']) > self::MAX_STUDENTS) {
            throw ValidationException::withMessages(['lote' => 'Esta conferência expirou ou não pertence à sua conta e escola. Envie o arquivo novamente.']);
        }

        $confirmed = array_values(array_unique(array_map('intval', $data['confirmados'] ?? [])));
        $homonyms = array_values(array_unique(array_map('intval', $data['homonimos'] ?? [])));
        $readings = array_values(array_unique(array_map('intval', $data['leituras'] ?? [])));
        $acceptedRows = [];
        $ignoredCount = 0;

        foreach ($payload['rows'] as $index => $row) {
            if (! is_array($row) || ! is_array($row['confirmation'] ?? null)) {
                throw ValidationException::withMessages(['lote' => 'Os dados da conferência são inválidos.']);
            }

            if ($this->hasConfirmation($row, 'ja_cadastrado')) {
                $ignoredCount++;

                continue;
            }

            foreach ($row['confirmation'] as $issue) {
                if (! is_array($issue)) {
                    throw ValidationException::withMessages(['lote' => 'Os dados da conferência são inválidos.']);
                }

                if (($issue['field'] ?? null) === 'duplicidade' && ! in_array($index, $confirmed, true)) {
                    throw ValidationException::withMessages(['confirmados' => 'Confirme explicitamente cada repetição de aluno antes de gravar.']);
                }

                if (($issue['field'] ?? null) === 'homonimo' && ! in_array($index, $homonyms, true)) {
                    throw ValidationException::withMessages(['homonimos' => 'Marque explicitamente que o possível homônimo é outra pessoa.']);
                }

                if (($issue['field'] ?? null) === 'leitura' && ! in_array($index, $readings, true)) {
                    throw ValidationException::withMessages(['leituras' => 'Confira com o documento e marque cada leitura duvidosa antes de gravar.']);
                }

                if (! in_array($issue['field'] ?? null, ['duplicidade', 'homonimo', 'leitura'], true)) {
                    throw ValidationException::withMessages(['lote' => 'Há conflitos de turma ou informações na planilha. Corrija o arquivo e envie novamente.']);
                }
            }

            $acceptedRows[] = $row;
        }

        try {
            DB::connection('tenant')->transaction(function () use ($payload, $acceptedRows, $ignoredCount, $school, $request): void {
                $year = SchoolYear::query()->whereIn('status', ['active', 'planned'])->lockForUpdate()->find($payload['year_id'] ?? null);
                if ($year === null || School::query()->where('status', 'active')->whereKey($school->id)->lockForUpdate()->first() === null) {
                    throw ValidationException::withMessages(['lote' => 'Escola ou ano letivo deixou de estar disponível. Refaça a conferência.']);
                }

                // A serialização de importações desta rede impede duas prévias
                // simultâneas de criarem o mesmo aluno ainda sem RA.
                DB::connection('tenant')->select('SELECT pg_advisory_xact_lock(hashtext(?))', ['astuti-import-students']);

                if (DB::connection('tenant')->table('student_import_receipts')->where('id', $payload['id'])->exists()) {
                    throw ValidationException::withMessages(['lote' => 'Este lote já foi importado. Nenhum registro foi repetido.']);
                }

                $classIds = SchoolClass::query()->where('school_id', $school->id)
                    ->where('school_year_id', $year->id)
                    ->whereIn('status', ['active', 'planned'])->pluck('id')->all();

                // Rechecar sob lock impede que outra importação reuse o RA
                // ou introduza um homônimo sem confirmação humana.
                foreach ($payload['rows'] as $row) {
                    $match = $this->existingStatus($row['name'], $row['birth_date'], $row['ra']);
                    if ($this->hasConfirmation($row, 'ja_cadastrado')) {
                        if ($match !== 'existing') {
                            throw ValidationException::withMessages(['lote' => 'Um cadastro mudou depois da prévia. Refaça a conferência antes de confirmar.']);
                        }

                        continue;
                    }

                    $this->validateStudent($row, $classIds);
                    if ($match === 'existing') {
                        throw ValidationException::withMessages(['lote' => 'Um aluno ou RA foi cadastrado depois da prévia. Refaça a conferência; nenhum registro foi gravado.']);
                    }
                    if ($match === 'homonym' && ! $this->hasConfirmation($row, 'homonimo')) {
                        throw ValidationException::withMessages(['lote' => 'Um possível homônimo apareceu depois da prévia. Refaça a conferência antes de gravar.']);
                    }
                }

                DB::connection('tenant')->table('student_import_receipts')->insert([
                    'id' => $payload['id'],
                    'school_id' => $school->id,
                    'school_year_id' => $year->id,
                    'created_by' => $request->user()->id,
                    'student_count' => count($acceptedRows),
                    'ignored_count' => $ignoredCount,
                    'created_at' => now(),
                ]);

                foreach ($acceptedRows as $row) {
                    $student = Student::query()->create([
                        'id' => (string) Str::uuid(),
                        'full_name' => $row['name'],
                        'birth_date' => $row['birth_date'],
                        'ra' => $row['ra'],
                    ]);
                    StudentEnrollment::query()->create([
                        'id' => (string) Str::uuid(),
                        'student_id' => $student->id,
                        'school_id' => $school->id,
                        'school_year_id' => $year->id,
                        'school_class_id' => $row['class_id'],
                        'status' => 'active',
                        // A planilha não informa a data de matrícula.
                        'started_at' => null,
                    ]);
                }

                app(AuditTrail::class)->record('tenant', 'student_import.completed', 'student_import_receipt', $payload['id'], null, [
                    'id' => $payload['id'],
                    'school_id' => $school->id,
                    'school_year_id' => $year->id,
                    'student_count' => count($acceptedRows),
                    'ignored_count' => $ignoredCount,
                ]);
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23505') {
                throw ValidationException::withMessages(['lote' => 'O lote ou um RA já foi cadastrado. Confira os dados antes de tentar novamente.']);
            }

            throw $exception;
        }

        return redirect()->route('school.imports.students.index', ['municipio' => $municipality->slug, 'escola' => $school->id])
            ->with('status', count($acceptedRows).' alunos e matrículas importados; '.$ignoredCount.' já cadastrados ignorados com auditoria.');
    }

    /** @return array{0: mixed, 1: School} */
    private function authorizeSchool(Request $request, string $id): array
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($id);
        Gate::authorize('manageStudents', [$school, $municipality]);

        return [$municipality, $school];
    }

    private function requireTwoFactor(Request $request): ?RedirectResponse
    {
        return app(TwoFactorSession::class)->isVerified($request, $request->user()->fresh())
            ? null
            : redirect()->guest(route('two-factor.challenge'));
    }

    /** @return list<ClassOption> */
    private function classes(School $school, SchoolYear $year): array
    {
        return SchoolClass::query()->where('school_id', $school->id)->where('school_year_id', $year->id)
            ->whereIn('status', ['active', 'planned'])->orderBy('name')->get()
            ->map(fn (SchoolClass $class): ClassOption => new ClassOption($class->id, $class->name, $class->shift))->all();
    }

    /** @param array<string, mixed> $row */
    private function hasConfirmation(array $row, string $field): bool
    {
        foreach ($row['confirmation'] as $issue) {
            if (is_array($issue) && ($issue['field'] ?? null) === $field) {
                return true;
            }
        }

        return false;
    }

    /**
     * RA igual ou nome e nascimento iguais indicam cadastro existente.
     * Data ausente impede identificar com segurança duas pessoas homônimas.
     */
    private function existingStatus(string $name, ?string $birthDate, ?string $ra): string
    {
        if ($ra !== null && Student::query()
            ->whereRaw("replace(upper(ra), '-', '') = replace(upper(?), '-', '')", [$ra])
            ->exists()) {
            return 'existing';
        }

        $sameName = Student::query()
            ->whereRaw('public.f_unaccent(lower(full_name)) = public.f_unaccent(lower(?))', [$name]);

        if ($birthDate === null) {
            return $sameName->exists() ? 'homonym' : 'new';
        }

        if ((clone $sameName)->whereDate('birth_date', $birthDate)->exists()) {
            return 'existing';
        }

        return (clone $sameName)->whereNull('birth_date')->exists() ? 'homonym' : 'new';
    }

    private function canonicalRa(?string $ra): ?string
    {
        if ($ra !== null && preg_match('/\A(\d{9,12})-?([\dX]\/\w{2})\z/', $ra, $match) === 1) {
            return $match[1].'-'.strtoupper($match[2]);
        }

        return $ra;
    }

    /** @param list<string> $classIds */
    private function validateStudent(array $row, array $classIds): void
    {
        if (! is_string($row['name'] ?? null) || mb_strlen($row['name']) < 5 || mb_strlen($row['name']) > 180
            || ! in_array($row['class_id'] ?? null, $classIds, true)
            || ! (is_null($row['birth_date'] ?? null) || (is_string($row['birth_date']) && preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $row['birth_date']) === 1))
            || ! (is_null($row['ra'] ?? null) || (is_string($row['ra']) && preg_match('/\A\d{9,12}-[\dX]\/[A-Z]{2}\z/', $row['ra']) === 1))) {
            throw ValidationException::withMessages(['lote' => 'A conferência está desatualizada ou contém turma/dados inválidos. Envie a planilha novamente.']);
        }
    }

    /** Tipo pelo conteudo do arquivo: planilha .xlsx (zip), PDF, JPEG ou PNG. */
    private function signature(string $path): ?string
    {
        $head = (string) file_get_contents($path, false, null, 0, 8);

        return match (true) {
            str_starts_with($head, "PK\x03\x04") => 'xlsx',
            str_starts_with($head, '%PDF-') => 'pdf',
            str_starts_with($head, "\xFF\xD8\xFF") => 'jpg',
            str_starts_with($head, "\x89PNG\r\n\x1A\n") => 'png',
            default => null,
        };
    }

    private function documentRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $extension = $value instanceof UploadedFile ? strtolower($value->getClientOriginalExtension()) : '';
            $kind = $value instanceof UploadedFile && $value->isValid() ? $this->signature($value->getRealPath()) : null;
            $expected = ['xlsx' => ['xlsx'], 'pdf' => ['pdf'], 'jpg' => ['jpg', 'jpeg'], 'png' => ['png']];

            if ($kind === null || ! in_array($extension, $expected[$kind], true)) {
                $fail('Envie a planilha (.xlsx), um PDF ou uma foto da lista (JPG ou PNG).');
            }
        };
    }
}
