<?php

namespace App\Http\Controllers;

use App\Models\Platform\Municipality;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Security\TwoFactorSession;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Inclusão e alteração manual de alunos pela secretaria da escola.
 *
 * Gravações exigem permissão de gestão na escola e o segundo fator confirmado
 * (middleware nas rotas POST/PUT). Toda gravação é auditada pelos eventos do
 * modelo, na mesma transação: sem auditoria, nada é gravado.
 */
class StudentRecordController extends Controller
{
    private const NAME_RULES = ['required', 'string', 'min:5', 'max:180', 'regex:/\A\p{L}[\p{L}\p{M}\' .-]*\z/u'];

    private const BIRTH_RULES = ['nullable', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before:today'];

    private const RA_RULES = ['nullable', 'string', 'max:32', 'regex:/\\A\\d{9,12}-[\\dX]\\/[A-Z]{2}\\z/'];

    private const ADDRESS_FIELDS = ['address_street', 'address_number', 'address_district', 'postal_code', 'residence_zone'];

    private const ADDRESS_RULES = [
        'address_street' => ['nullable', 'string', 'max:180'],
        'address_number' => ['nullable', 'string', 'max:20'],
        'address_district' => ['nullable', 'string', 'max:120'],
        'postal_code' => ['nullable', 'string', 'regex:/\\A\\d{5}-\\d{3}\\z/'],
        'residence_zone' => ['nullable', 'in:urban,rural'],
    ];

    private const VALIDATION_MESSAGES = [
        'ra.regex' => 'Informe o RA no formato da SED, por exemplo 000123456789-0/SP.',
        'postal_code.regex' => 'Informe o CEP no formato 00000-000.',
        'residence_zone.in' => 'Selecione a zona de residência: Urbana ou Rural.',
    ];

    private const VALIDATION_ATTRIBUTES = [
        'ra' => 'RA',
        'address_street' => 'Logradouro',
        'address_number' => 'Número',
        'address_district' => 'Bairro',
        'postal_code' => 'CEP',
        'residence_zone' => 'Zona de residência',
    ];

    public function create(Request $request, string $municipio, string $escola): View|RedirectResponse
    {
        [$municipality, $school] = $this->authorizeSchool($request, $escola);

        if ($redirect = $this->requireTwoFactor($request)) {
            return $redirect;
        }

        return view('students.form', [
            'municipality' => $municipality,
            'school' => $school,
            'student' => null,
            'enrollment' => null,
            'years' => $this->openYears(),
            'classes' => $this->classesOf($school),
        ]);
    }

    public function store(Request $request, string $municipio, string $escola): RedirectResponse
    {
        [$municipality, $school] = $this->authorizeSchool($request, $escola);

        $request->merge([
            'full_name' => $this->normalizeName($request->input('full_name')),
            'ra' => $this->normalizeRa($request->input('ra')),
        ]);
        $this->normalizeAddressInput($request);
        $data = $request->validate([
            'full_name' => self::NAME_RULES,
            'birth_date' => self::BIRTH_RULES,
            'ra' => self::RA_RULES,
            ...self::ADDRESS_RULES,
            'school_class_id' => ['nullable', 'uuid'],
            'school_year_id' => ['required', 'uuid'],
            'started_at' => ['nullable', 'date_format:Y-m-d'],
            'confirmar_duplicidade' => ['nullable', 'boolean'],
        ], self::VALIDATION_MESSAGES, self::VALIDATION_ATTRIBUTES);

        $this->assertRaAvailable($data['ra'] ?? null);

        $year = SchoolYear::query()->where('status', '!=', 'completed')->find($data['school_year_id']);
        if ($year === null) {
            throw ValidationException::withMessages(['school_year_id' => 'Escolha um ano letivo em andamento ou planejado.']);
        }

        $this->assertClassMatches($data['school_class_id'] ?? null, $school, $year->id);

        if (! empty($data['started_at'])
            && ($data['started_at'] < $year->start_date->format('Y-m-d') || $data['started_at'] > $year->end_date->format('Y-m-d'))) {
            throw ValidationException::withMessages(['started_at' => 'A data de início precisa estar dentro do ano letivo escolhido.']);
        }

        // Nome e nascimento iguais podem ser a mesma criança: nunca fundir
        // automaticamente, mas exigir que a pessoa confirme conscientemente.
        if (! $request->boolean('confirmar_duplicidade') && $this->possibleDuplicate($data['full_name'], $data['birth_date'] ?? null)) {
            return back()->withInput()->with('possivel_duplicidade', true)->withErrors([
                'full_name' => 'Já existe no município um aluno com o mesmo nome'.(empty($data['birth_date']) ? '' : ' e a mesma data de nascimento')
                    .'. Confira antes de continuar. Se for outra pessoa, marque a confirmação e salve de novo.',
            ]);
        }

        try {
            $student = DB::connection('tenant')->transaction(function () use ($data, $school, $year): Student {
                $student = Student::query()->create([
                    'id' => (string) Str::uuid(),
                    'full_name' => $data['full_name'],
                    'birth_date' => $data['birth_date'] ?? null,
                    'ra' => $data['ra'] ?? null,
                    'address_street' => $data['address_street'] ?? null,
                    'address_number' => $data['address_number'] ?? null,
                    'address_district' => $data['address_district'] ?? null,
                    'postal_code' => $data['postal_code'] ?? null,
                    'residence_zone' => $data['residence_zone'] ?? null,
                ]);

                StudentEnrollment::query()->create([
                    'id' => (string) Str::uuid(),
                    'student_id' => $student->id,
                    'school_id' => $school->id,
                    'school_year_id' => $year->id,
                    'school_class_id' => $data['school_class_id'] ?? null,
                    'status' => 'active',
                    'started_at' => $data['started_at'] ?? null,
                ]);

                return $student;
            });
        } catch (QueryException $exception) {
            $this->rethrowRaConflict($exception);
        }

        return redirect()
            ->route('school.students.show', ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id])
            ->with('status', 'Aluno cadastrado e matriculado nesta escola.');
    }

    public function edit(Request $request, string $municipio, string $escola, string $aluno): View|RedirectResponse
    {
        [$municipality, $school] = $this->authorizeSchool($request, $escola);
        $student = $this->activeStudentOf($school)->findOrFail($aluno);
        $query = $this->activeEnrollmentOf($school, $student->id);
        $selection = $request->validate(['matricula' => ['nullable', 'uuid']]);
        if (! empty($selection['matricula'])) {
            $query->whereKey($selection['matricula']);
        }
        $enrollment = $query->with('schoolYear')->firstOrFail();

        if ($redirect = $this->requireTwoFactor($request)) {
            return $redirect;
        }

        return view('students.form', [
            'municipality' => $municipality,
            'school' => $school,
            'student' => $student,
            'enrollment' => $enrollment,
            'studentVersion' => $student->row_token,
            'enrollmentVersion' => $enrollment->row_token,
            'years' => collect(),
            'classes' => $this->classesOf($school, $enrollment->school_year_id, $enrollment->school_class_id),
        ]);
    }

    public function update(Request $request, string $municipio, string $escola, string $aluno): RedirectResponse
    {
        [$municipality, $school] = $this->authorizeSchool($request, $escola);
        $this->activeStudentOf($school)->findOrFail($aluno);

        $raSubmitted = $request->exists('ra');
        $addressFieldsSubmitted = array_values(array_filter(
            self::ADDRESS_FIELDS,
            fn (string $field): bool => $request->exists($field),
        ));
        $request->merge([
            'full_name' => $this->normalizeName($request->input('full_name')),
            'ra' => $this->normalizeRa($request->input('ra')),
        ]);
        $this->normalizeAddressInput($request);
        $data = $request->validate([
            'full_name' => self::NAME_RULES,
            'birth_date' => self::BIRTH_RULES,
            'ra' => self::RA_RULES,
            ...self::ADDRESS_RULES,
            'school_class_id' => ['nullable', 'uuid'],
            'enrollment_id' => [$request->exists('school_class_id') ? 'required' : 'nullable', 'uuid'],
            'versao_matricula' => [$request->exists('school_class_id') ? 'required' : 'nullable', 'string', 'max:40'],
            'versao' => ['required', 'string', 'max:40'],
        ], self::VALIDATION_MESSAGES, self::VALIDATION_ATTRIBUTES);

        if ($raSubmitted) {
            $this->assertRaAvailable($data['ra'] ?? null, $aluno);
        }

        try {
            $changed = DB::connection('tenant')->transaction(function () use ($aluno, $school, $data, $request, $raSubmitted, $addressFieldsSubmitted): bool {
                $locked = Student::query()->selectRaw('students.*, xmin::text AS row_token')->lockForUpdate()->findOrFail($aluno);
                $enrollmentQuery = $this->activeEnrollmentOf($school, $aluno);
                if (! empty($data['enrollment_id'])) {
                    $enrollmentQuery->whereKey($data['enrollment_id']);
                }
                $enrollment = $enrollmentQuery->lockForUpdate()->firstOrFail();

                // As duas versões são conferidas antes de qualquer alteração.
                if ((string) $locked->row_token !== $data['versao']) {
                    throw ValidationException::withMessages([
                        'versao' => 'Este cadastro foi alterado por outra pessoa enquanto você editava. Abra a ficha de novo e repita a alteração.',
                    ]);
                }
                if ($request->exists('school_class_id')
                    && (string) $enrollment->row_token !== $data['versao_matricula']) {
                    throw ValidationException::withMessages([
                        'versao_matricula' => 'Esta matrícula foi alterada por outra pessoa. Abra a ficha de novo antes de escolher uma turma.',
                    ]);
                }

                if ($request->exists('school_class_id')) {
                    // A turma já vinculada pode ter sido encerrada; não se apaga
                    // seu histórico ao editar apenas outro dado do aluno.
                    if (($data['school_class_id'] ?? null) !== $enrollment->school_class_id) {
                        $this->assertClassMatches($data['school_class_id'] ?? null, $school, $enrollment->school_year_id);
                    }
                }

                $studentData = [
                    'full_name' => $data['full_name'],
                    'birth_date' => $data['birth_date'] ?? null,
                    'ra' => $raSubmitted ? ($data['ra'] ?? null) : $locked->ra,
                ];
                foreach ($addressFieldsSubmitted as $field) {
                    $studentData[$field] = $data[$field] ?? null;
                }
                $locked->fill($studentData);
                $studentDirty = $locked->isDirty();

                if ($request->exists('school_class_id')) {
                    $enrollment->fill(['school_class_id' => $data['school_class_id'] ?? null]);
                }
                $classDirty = $enrollment->isDirty();

                if ($studentDirty) {
                    $locked->save();
                }
                if ($classDirty) {
                    $enrollment->save();
                }

                return $studentDirty || $classDirty;
            });
        } catch (QueryException $exception) {
            $this->rethrowRaConflict($exception);
        }

        return redirect()
            ->route('school.students.show', ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $aluno])
            ->with('status', $changed ? 'Dados do aluno atualizados.' : 'Nenhuma alteração foi feita.');
    }

    /** @return array{0: Municipality, 1: School} */
    private function authorizeSchool(Request $request, string $escola): array
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($escola);
        Gate::authorize('manageStudents', [$school, $municipality]);

        return [$municipality, $school];
    }

    /** Páginas de formulário levam ao desafio do segundo fator e voltam depois. */
    private function requireTwoFactor(Request $request): ?RedirectResponse
    {
        if (app(TwoFactorSession::class)->isVerified($request, $request->user()->fresh())) {
            return null;
        }

        return redirect()->guest(route('two-factor.challenge'));
    }

    private function activeStudentOf(School $school): Builder
    {
        return Student::query()->selectRaw('students.*, xmin::text AS row_token')
            ->whereHas('enrollments', function (Builder $enrollments) use ($school): void {
                $enrollments->where('school_id', $school->id)->where('status', 'active');
            });
    }

    private function activeEnrollmentOf(School $school, string $studentId): Builder
    {
        return StudentEnrollment::query()->selectRaw('student_enrollments.*, xmin::text AS row_token')
            ->where('student_id', $studentId)
            ->where('school_id', $school->id)->where('status', 'active')
            ->orderByDesc('started_at')->orderByDesc('id');
    }

    private function classesOf(School $school, ?string $yearId = null, ?string $selectedClassId = null)
    {
        return SchoolClass::query()->where('school_id', $school->id)
            ->when($yearId, fn (Builder $query) => $query->where('school_year_id', $yearId))
            ->where(function (Builder $query) use ($selectedClassId): void {
                $query->whereIn('status', ['active', 'planned']);
                if ($selectedClassId !== null) {
                    // Uma turma encerrada continua visível no histórico da matrícula.
                    $query->orWhere('id', $selectedClassId);
                }
            })
            ->with('schoolYear')->orderBy('name')->get();
    }

    private function assertClassMatches(?string $classId, School $school, string $yearId): void
    {
        if ($classId === null) {
            return;
        }

        if (! SchoolClass::query()->where('school_id', $school->id)
            ->where('school_year_id', $yearId)->whereIn('status', ['active', 'planned'])
            ->whereKey($classId)->exists()) {
            throw ValidationException::withMessages([
                'school_class_id' => 'Escolha uma turma ativa ou planejada desta escola e do mesmo ano letivo.',
            ]);
        }
    }

    private function assertRaAvailable(?string $ra, ?string $exceptStudentId = null): void
    {
        if ($ra === null) {
            return;
        }

        $query = Student::query()->whereRaw("replace(upper(ra), '-', '') = replace(upper(?), '-', '')", [$ra]);
        if ($exceptStudentId !== null) {
            $query->where('id', '!=', $exceptStudentId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'ra' => 'Este RA já está cadastrado. Confira o registro existente antes de prosseguir.',
            ]);
        }
    }

    /** Preserva a mensagem de cadastro em corridas concorrentes pelo mesmo RA. */
    private function rethrowRaConflict(QueryException $exception): never
    {
        if ($exception->getCode() === '23505'
            && str_contains($exception->getMessage(), 'students_ra_normalized_unique')) {
            throw ValidationException::withMessages([
                'ra' => 'Este RA já está cadastrado. Confira o registro existente antes de prosseguir.',
            ]);
        }

        throw $exception;
    }

    /** Normaliza os dados opcionais sem aceitar letras ou pontuação indevida no CEP. */
    private function normalizeAddressInput(Request $request): void
    {
        $request->merge([
            'address_street' => $this->normalizeAddressPart($request->input('address_street')),
            'address_number' => $this->normalizeAddressPart($request->input('address_number')),
            'address_district' => $this->normalizeAddressPart($request->input('address_district')),
            'postal_code' => $this->normalizePostalCode($request->input('postal_code')),
        ]);
    }

    private function normalizeAddressPart(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = preg_replace('/\\s+/u', ' ', trim($value));

        return $value === '' ? null : $value;
    }

    private function normalizePostalCode(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return preg_match('/\\A\\d{8}\\z/', $value) === 1
            ? substr($value, 0, 5).'-'.substr($value, 5)
            : $value;
    }

    private function normalizeRa(mixed $ra): mixed
    {
        if (! is_string($ra)) {
            return $ra;
        }

        $ra = strtoupper(preg_replace('/\\s+/', '', trim($ra)));
        if (preg_match('/\\A(\\d{9,12})([\\dX]\\/[A-Z]{2})\\z/', $ra, $matches) === 1) {
            return $matches[1].'-'.$matches[2];
        }

        return $ra === '' ? null : $ra;
    }

    private function openYears()
    {
        return SchoolYear::query()->where('status', '!=', 'completed')->orderByDesc('year')->get();
    }

    /** Remove espaços sobrando; não altera maiúsculas nem acentos digitados. */
    private function normalizeName(mixed $name): mixed
    {
        return is_string($name) ? preg_replace('/\s+/u', ' ', trim($name)) : $name;
    }

    private function possibleDuplicate(string $name, ?string $birthDate): bool
    {
        $query = Student::query()->whereRaw('public.f_unaccent(lower(full_name)) = public.f_unaccent(lower(?))', [$name]);

        if ($birthDate !== null) {
            $query->whereDate('birth_date', $birthDate);
        }

        return $query->exists();
    }
}
