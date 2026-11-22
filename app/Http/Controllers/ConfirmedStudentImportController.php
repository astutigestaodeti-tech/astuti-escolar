<?php

namespace App\Http\Controllers;

use App\Address\BrazilianStates;
use App\Audit\AuditTrail;
use App\Import\Ocr\DocumentTableReader;
use App\Import\Ocr\OcrReadException;
use App\Import\Students\ClassOption;
use App\Import\Students\ImportedGuardianLinker;
use App\Import\Students\ImportedStudent;
use App\Import\Students\ImportIssue;
use App\Import\Students\ImportTarget;
use App\Import\Students\StudentSpreadsheetPreview;
use App\Import\Xlsx\XlsxReadException;
use App\Models\Platform\Municipality;
use App\Models\Tenant\Guardian;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\StudentGuardian;
use App\Security\TwoFactorSession;
use App\Validation\Cpf;
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

    private const CENSUS_FIELDS = ['social_name', 'sex', 'race_color', 'nationality', 'birthplace_city', 'birthplace_state', 'cpf'];

    private const ADDRESS_LIMITS = ['address_street' => 180, 'address_number' => 20, 'address_district' => 120, 'postal_code' => 9, 'residence_zone' => 5];

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

        // O mesmo CPF em dois alunos da planilha não identifica ninguém: nenhum dos dois o recebe.
        $cpfCount = array_count_values(array_filter(array_map(fn (ImportedStudent $student): ?string => $student->census['cpf'] ?? null, $preview->students)));

        $rows = [];
        foreach ($preview->students as $student) {
            $confirmation = [];
            $pending = [];
            foreach ($student->issues as $issue) {
                if ($issue->kind === ImportIssue::CONFIRM) {
                    $confirmation[] = ['field' => $issue->field, 'message' => $issue->message];
                } else {
                    // Observações e pistas pessoais não vão para a prévia persistível.
                    $pending[] = [
                        'field' => $issue->field,
                        'message' => $issue->field === 'observacao'
                            ? 'Observação não importada nesta etapa.'
                            : $issue->message,
                    ];
                }
            }

            $birthDate = $student->birthDate?->format('Y-m-d');
            $ra = $this->canonicalRa($student->ra);
            $match = $this->existingStatus($student->name, $birthDate, $ra);
            $address = $this->importedAddress($student, $pending);
            $census = $student->census;
            $candidate = $match === 'existing'
                ? $this->eligibleAddressStudent($student->name, $birthDate, $ra, $school->id, $year->id)
                : null;
            if (isset($census['cpf'])) {
                // Se o CPF já tem dono na rede, só o próprio aluno o mantém; sem dono,
                // repetido na planilha não identifica ninguém.
                $owner = Student::query()->where('cpf', $census['cpf'])->value('id');
                if ($owner !== null && $owner !== $candidate?->id) {
                    unset($census['cpf']);
                    $pending[] = ['field' => 'cpf', 'message' => 'Este CPF já está no cadastro de outro aluno da rede; não foi importado. Confira o documento.'];
                } elseif ($owner === null && ($cpfCount[$census['cpf']] ?? 0) > 1) {
                    unset($census['cpf']);
                    $pending[] = ['field' => 'cpf', 'message' => 'O mesmo CPF aparece em mais de um aluno desta planilha; não foi importado. Confira os documentos e informe na ficha.'];
                }
            }

            $guardian = $this->importedGuardian($student->guardian, $pending);

            // Aluno já cadastrado: endereço, Censo e responsável só completam o que está vazio, com marcação.
            $addressTargetId = null;
            $completable = [...$address, ...$census];
            $guardianMissing = $candidate !== null && $guardian !== null
                && ! StudentGuardian::query()->where('student_id', $candidate->id)->exists();
            if ($candidate !== null && (($guardianMissing)
                || (array_filter($completable, fn (?string $value): bool => $value !== null) !== []
                    && $this->missingAddressFields($candidate, $completable) !== []))) {
                $addressTargetId = $candidate->id;
            }

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
                'address' => $address,
                'census' => $census,
                'guardian' => $guardian,
                'address_target_id' => $addressTargetId,
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
            'completar_enderecos' => ['nullable', 'array', 'max:500'],
            'completar_enderecos.*' => ['integer', 'min:0', 'max:499'],
        ]);

        // Aceita somente a representação Base64 canônica do lote cifrado.
        // Um sufixo ignorado pelo decodificador não pode validar a mesma prévia.
        $encoded = base64_decode($data['lote'], true);
        if ($encoded === false || base64_encode($encoded) !== $data['lote']) {
            throw ValidationException::withMessages(['lote' => 'Prévia inválida ou alterada. Envie a planilha novamente.']);
        }

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
        $completeAddresses = array_values(array_unique(array_map('intval', $data['completar_enderecos'] ?? [])));
        $acceptedRows = [];
        $ignoredCount = 0;

        foreach ($payload['rows'] as $index => $row) {
            if (! is_array($row) || ! is_array($row['confirmation'] ?? null)) {
                throw ValidationException::withMessages(['lote' => 'Os dados da conferência são inválidos.']);
            }

            $this->validatedAddress($row);
            $this->validatedCensus($row);
            $this->validatedGuardian($row);

            if ($this->hasConfirmation($row, 'ja_cadastrado')) {
                $ignoredCount++;
                if (in_array($index, $completeAddresses, true) && Str::isUuid($row['address_target_id'] ?? '') === false) {
                    throw ValidationException::withMessages(['completar_enderecos' => 'Este aluno não pode receber uma atualização de endereço. Refaça a conferência.']);
                }

                continue;
            }
            if (in_array($index, $completeAddresses, true)) {
                throw ValidationException::withMessages(['completar_enderecos' => 'Só é possível completar o endereço de um aluno já cadastrado na escola.']);
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

        if (array_diff($completeAddresses, array_keys($payload['rows'])) !== []) {
            throw ValidationException::withMessages(['completar_enderecos' => 'A seleção de endereços contém linhas inválidas.']);
        }

        $completedAddresses = 0;
        $linkedGuardians = 0;
        try {
            DB::connection('tenant')->transaction(function () use ($payload, $acceptedRows, $ignoredCount, $completeAddresses, &$completedAddresses, &$linkedGuardians, $school, $request): void {
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
                $addressUpdates = [];
                foreach ($payload['rows'] as $index => $row) {
                    $match = $this->existingStatus($row['name'], $row['birth_date'], $row['ra']);
                    if ($this->hasConfirmation($row, 'ja_cadastrado')) {
                        if ($match !== 'existing') {
                            throw ValidationException::withMessages(['lote' => 'Um cadastro mudou depois da prévia. Refaça a conferência antes de confirmar.']);
                        }

                        if (in_array($index, $completeAddresses, true)) {
                            $existing = $this->eligibleAddressStudent($row['name'], $row['birth_date'], $row['ra'], $school->id, $year->id, true);
                            if ($existing === null || $existing->id !== $row['address_target_id']) {
                                throw ValidationException::withMessages(['lote' => 'A identificação ou o vínculo escolar mudou depois da prévia. Refaça a conferência; nenhum endereço foi alterado.']);
                            }
                            $missing = $this->missingAddressFields($existing, [...$this->validatedAddress($row), ...$this->validatedCensus($row)]);
                            $this->assertCpfStillFree($missing['cpf'] ?? null, $existing->id);
                            $guardian = $this->validatedGuardian($row);
                            if ($guardian !== null && StudentGuardian::query()->where('student_id', $existing->id)->exists()) {
                                $guardian = null;
                            }
                            if ($missing !== [] || $guardian !== null) {
                                $addressUpdates[] = [$existing, $missing, $guardian];
                            }
                        }

                        continue;
                    }

                    $this->validateStudent($row, $classIds);
                    $this->assertCpfStillFree($this->validatedCensus($row)['cpf'] ?? null);
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

                $guardians = new ImportedGuardianLinker;
                foreach ($addressUpdates as [$existing, $missing, $guardian]) {
                    if ($missing !== []) {
                        $existing->fill($missing);
                        $existing->save();
                    }
                    if ($guardian !== null) {
                        $guardians->link($existing, $guardian);
                    }
                    $completedAddresses++;
                }

                foreach ($acceptedRows as $row) {
                    $student = Student::query()->create([
                        'id' => (string) Str::uuid(),
                        'full_name' => $row['name'],
                        'birth_date' => $row['birth_date'],
                        'ra' => $row['ra'],
                        ...$this->validatedAddress($row),
                        ...array_filter($this->validatedCensus($row), fn (?string $value): bool => $value !== null),
                    ]);
                    if (($guardian = $this->validatedGuardian($row)) !== null) {
                        $guardians->link($student, $guardian);
                    }
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
                    'address_completed_count' => $completedAddresses,
                    'guardian_link_count' => $guardians->linked,
                ]);
                $linkedGuardians = $guardians->linked;
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23505') {
                throw ValidationException::withMessages(['lote' => 'O lote, um RA ou um CPF já foi cadastrado. Confira os dados antes de tentar novamente.']);
            }

            throw $exception;
        }

        return redirect()->route('school.imports.students.index', ['municipio' => $municipality->slug, 'escola' => $school->id])
            ->with('status', count($acceptedRows).' alunos e matrículas importados; '.$ignoredCount.' já cadastrados ignorados; '.$completedAddresses.' '.($completedAddresses === 1 ? 'cadastro completado' : 'cadastros completados').' (endereço, Censo e responsável); '
                .$linkedGuardians.' '.($linkedGuardians === 1 ? 'responsável vinculado' : 'responsáveis vinculados').', tudo com auditoria.');
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

    /**
     * Endereço disponível na fonte; campos longos ou incompletos não são
     * truncados nem inferidos: continuam pendentes para conferência manual.
     *
     * @param  list<array{field: string, message: string}>  $pending
     * @return array<string, ?string>
     */
    private function importedAddress(ImportedStudent $student, array &$pending): array
    {
        $address = [
            'address_street' => $student->street,
            'address_number' => $student->number,
            'address_district' => $student->district,
            'postal_code' => $student->postalCode,
            'residence_zone' => $student->residenceZone,
        ];

        foreach (self::ADDRESS_LIMITS as $field => $limit) {
            if ($address[$field] !== null && mb_strlen($address[$field]) > $limit) {
                $address[$field] = null;
                $pending[] = ['field' => 'endereco', 'message' => 'Um campo de endereço ultrapassa o limite; complete manualmente após a importação.'];
            }
        }

        return $address;
    }

    /**
     * Dados do Censo vindos da conferência cifrada, revalidados como no
     * cadastro manual. Qualquer valor fora das opções invalida a prévia.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, ?string>
     */
    private function validatedCensus(array $row): array
    {
        $source = $row['census'] ?? [];
        if (! is_array($source) || array_diff(array_keys($source), self::CENSUS_FIELDS) !== []) {
            throw ValidationException::withMessages(['lote' => 'Dados do Censo inválidos na conferência. Reenvie o arquivo.']);
        }

        $census = [];
        foreach (self::CENSUS_FIELDS as $field) {
            $value = $source[$field] ?? null;
            $valid = $value === null || (is_string($value) && match ($field) {
                'social_name' => mb_strlen($value) >= 3 && mb_strlen($value) <= 180 && preg_match('/\A\p{L}[\p{L}\p{M}\' .-]*\z/u', $value) === 1,
                'birthplace_city' => mb_strlen($value) <= 120 && preg_match('/\A\p{L}[\p{L}\p{M}\' .-]*\z/u', $value) === 1,
                'sex' => isset(StudentRecordController::SEX_LABELS[$value]),
                'race_color' => isset(StudentRecordController::RACE_LABELS[$value]),
                'nationality' => isset(StudentRecordController::NATIONALITY_LABELS[$value]),
                'birthplace_state' => isset(BrazilianStates::NAMES[$value]),
                'cpf' => Cpf::normalize($value) === $value && Cpf::isValid($value),
            });
            if (! $valid) {
                throw ValidationException::withMessages(['lote' => 'Dados do Censo inválidos na conferência. Reenvie o arquivo.']);
            }
            $census[$field] = $value;
        }

        return $census;
    }

    /**
     * Responsável da planilha para a prévia. Sem telefone com DDD só entra se
     * o CPF já existir na rede; CPF de outra pessoa nunca é usado.
     *
     * @param  array{name: string, relationship: string, phone: ?string, cpf: ?string}|null  $guardian
     * @param  list<array{field: string, message: string}>  $pending
     * @return array{name: string, relationship: string, phone: ?string, cpf: ?string}|null
     */
    private function importedGuardian(?array $guardian, array &$pending): ?array
    {
        if ($guardian === null) {
            return null;
        }

        $existing = $guardian['cpf'] === null ? null : Guardian::query()->where('cpf', $guardian['cpf'])->first();
        if ($existing !== null && ImportedGuardianLinker::normalizedName($existing->full_name) !== ImportedGuardianLinker::normalizedName($guardian['name'])) {
            $pending[] = ['field' => 'responsavel', 'message' => 'O CPF informado para '.$guardian['name'].' já está cadastrado na rede com outro nome; o responsável não foi vinculado. Confira o documento.'];

            return null;
        }
        if ($existing === null && $guardian['phone'] === null) {
            $pending[] = ['field' => 'responsavel', 'message' => $guardian['name'].' não foi cadastrado como responsável porque falta telefone com DDD. Cadastre na ficha.'];

            return null;
        }

        return $guardian;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{name: string, relationship: string, phone: ?string, cpf: ?string}|null
     */
    private function validatedGuardian(array $row): ?array
    {
        $guardian = $row['guardian'] ?? null;
        if ($guardian === null) {
            return null;
        }

        $valid = is_array($guardian)
            && array_keys($guardian) === ['name', 'relationship', 'phone', 'cpf']
            && is_string($guardian['name']) && mb_strlen($guardian['name']) >= 5 && mb_strlen($guardian['name']) <= 180
            && preg_match('/\A\p{L}[\p{L}\p{M}\' .-]*\z/u', $guardian['name']) === 1
            && in_array($guardian['relationship'], ImportedGuardianLinker::RELATIONSHIPS, true)
            && ($guardian['phone'] === null || (is_string($guardian['phone']) && preg_match('/\A\d{10,11}\z/', $guardian['phone']) === 1))
            && ($guardian['cpf'] === null || (is_string($guardian['cpf']) && Cpf::normalize($guardian['cpf']) === $guardian['cpf'] && Cpf::isValid($guardian['cpf'])))
            && ($guardian['phone'] !== null || $guardian['cpf'] !== null);
        if (! $valid) {
            throw ValidationException::withMessages(['lote' => 'Dados de responsável inválidos na conferência. Reenvie o arquivo.']);
        }

        return $guardian;
    }

    /** Sob o lock da importação: o CPF não pode ter ido para outro aluno depois da prévia. */
    private function assertCpfStillFree(?string $cpf, ?string $exceptStudentId = null): void
    {
        if ($cpf === null) {
            return;
        }

        $taken = Student::query()->where('cpf', $cpf)
            ->when($exceptStudentId !== null, fn ($query) => $query->whereKeyNot($exceptStudentId))
            ->exists();
        if ($taken) {
            throw ValidationException::withMessages(['lote' => 'Um CPF desta planilha foi cadastrado em outro aluno depois da prévia. Refaça a conferência; nenhum registro foi gravado.']);
        }
    }

    /** @param array<string, mixed> $row
     * @return array<string, ?string>
     */
    private function validatedAddress(array $row): array
    {
        $source = $row['address'] ?? [];
        if (is_array($source) === false) {
            throw ValidationException::withMessages(['lote' => 'Endereço inválido na conferência. Reenvie o arquivo.']);
        }

        $address = [];
        foreach (self::ADDRESS_LIMITS as $field => $limit) {
            $value = $source[$field] ?? null;
            if ($value !== null && (is_string($value) === false || trim($value) === '' || mb_strlen($value) > $limit)) {
                throw ValidationException::withMessages(['lote' => 'Endereço inválido na conferência. Reenvie o arquivo.']);
            }
            $address[$field] = $value;
        }
        if (($address['postal_code'] !== null && preg_match('/\A\d{5}-\d{3}\z/', $address['postal_code']) !== 1)
            || ($address['residence_zone'] !== null && in_array($address['residence_zone'], ['urban', 'rural'], true) === false)) {
            throw ValidationException::withMessages(['lote' => 'CEP ou zona de residência inválidos na conferência. Reenvie o arquivo.']);
        }

        return $address;
    }

    /** @param array<string, ?string> $address
     * @return array<string, string>
     */
    private function missingAddressFields(Student $student, array $address): array
    {
        $missing = [];
        foreach ($address as $field => $value) {
            if ($value !== null && ($student->getAttribute($field) === null || trim((string) $student->getAttribute($field)) === '')) {
                $missing[$field] = $value;
            }
        }

        return $missing;
    }

    /**
     * RA, ou nome sem acento mais nascimento, deve identificar uma única pessoa
     * vinculada à escola e ao ano. Nunca usa só nome para completar endereço.
     */
    private function eligibleAddressStudent(string $name, ?string $birthDate, ?string $ra, string $schoolId, string $yearId, bool $lock = false): ?Student
    {
        if ($ra === null && $birthDate === null) {
            return null;
        }

        $query = Student::query()->whereRaw('public.f_unaccent(lower(full_name)) = public.f_unaccent(lower(?))', [$name]);
        if ($birthDate !== null) {
            $query->whereDate('birth_date', $birthDate);
        }
        if ($ra !== null) {
            $query->whereRaw("replace(upper(ra), '-', '') = replace(upper(?), '-', '')", [$ra]);
        }
        if ($lock) {
            $query->lockForUpdate();
        }

        $matches = $query->limit(2)->get();
        if ($matches->count() !== 1) {
            return null;
        }

        $student = $matches->first();
        // A escola de origem não pode alterar endereço após transferência ou
        // encerramento. A linha da matrícula também fica travada no POST.
        $enrollment = StudentEnrollment::query()->where('student_id', $student->id)
            ->where('school_id', $schoolId)->where('school_year_id', $yearId)
            ->where('status', 'active')->whereNull('ended_at');
        if ($lock) {
            $enrollment->lockForUpdate();
        }

        return $enrollment->first(['id']) !== null ? $student : null;
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
