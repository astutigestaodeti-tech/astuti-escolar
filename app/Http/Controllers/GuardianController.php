<?php

namespace App\Http\Controllers;

use App\Audit\AuditTrail;
use App\Models\Tenant\Guardian;
use App\Models\Tenant\School;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentGuardian;
use App\Validation\Cpf;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GuardianController extends Controller
{
    public const RELATIONSHIPS = [
        'mother' => 'Mãe',
        'father' => 'Pai',
        'grandmother' => 'Avó',
        'grandfather' => 'Avô',
        'uncle' => 'Tio',
        'aunt' => 'Tia',
        'brother' => 'Irmão',
        'sister' => 'Irmã',
        'other' => 'Outro',
    ];

    public function create(Request $request, string $municipio, string $escola, string $aluno): View
    {
        [$municipality, $school, $student] = $this->authorizedStudent($request, $escola, $aluno);

        return view('guardians.form', [
            'municipality' => $municipality,
            'school' => $school,
            'student' => $student,
            'link' => null,
            'guardian' => null,
            'relationships' => self::RELATIONSHIPS,
            'linkedStudentsCount' => 0,
        ]);
    }

    public function store(Request $request, string $municipio, string $escola, string $aluno): RedirectResponse
    {
        [$municipality, $school] = $this->authorizedStudent($request, $escola, $aluno);
        $data = $this->validatedData($request);

        try {
            [$reused, $principalAnterior, $guardianName, $contactUpdated] = DB::connection('tenant')->transaction(function () use ($aluno, $school, $data): array {
                $this->lockedStudent($aluno, $school);

                $guardian = $data['cpf'] === null
                    ? null
                    : Guardian::query()->where('cpf', $data['cpf'])->lockForUpdate()->first();
                $reused = $guardian !== null;
                $contactUpdated = false;

                if ($guardian === null) {
                    if (empty($data['phone'])) {
                        throw ValidationException::withMessages(['phone' => 'Informe um telefone para contato.']);
                    }

                    $guardianData = $this->guardianFields($data);
                    $guardianData['id'] = (string) Str::uuid();

                    if ($data['cpf'] !== null) {
                        // UNIQUE(cpf) impede duplicidade mesmo com cadastros simultâneos.
                        $created = DB::connection('tenant')->table('guardians')->insertOrIgnore([
                            ...$guardianData,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $guardian = Guardian::query()->where('cpf', $data['cpf'])->lockForUpdate()->firstOrFail();
                        $reused = $created !== 1;
                        if (! $reused) {
                            app(AuditTrail::class)->record('tenant', 'created', Guardian::class, $guardian->id, null, $this->guardianSnapshot($guardian));
                        }
                    } else {
                        $guardian = Guardian::query()->create($guardianData);
                        app(AuditTrail::class)->record('tenant', 'created', Guardian::class, $guardian->id, null, $this->guardianSnapshot($guardian));
                    }
                }

                if (StudentGuardian::query()->where('student_id', $aluno)->where('guardian_id', $guardian->id)->exists()) {
                    throw ValidationException::withMessages(['cpf' => 'Este responsável já está vinculado ao aluno. Use Editar na ficha.']);
                }

                if ($reused) {
                    if ($this->normalizedName($data['full_name']) !== $this->normalizedName($guardian->full_name)) {
                        throw ValidationException::withMessages([
                            'cpf' => 'Este CPF já está cadastrado na rede com outro nome. Confira o documento.',
                        ]);
                    }

                    $beforeGuardian = $this->guardianSnapshot($guardian);
                    foreach (['phone', 'email', 'phone_secondary', 'workplace', 'work_phone'] as $field) {
                        if (filled($data[$field] ?? null) && $guardian->{$field} !== $data[$field]) {
                            $guardian->{$field} = $data[$field];
                        }
                    }
                    if ($guardian->isDirty()) {
                        $guardian->save();
                        $contactUpdated = true;
                        app(AuditTrail::class)->record('tenant', 'updated', Guardian::class, $guardian->id, $beforeGuardian, $this->guardianSnapshot($guardian));
                    }
                }

                $principalAnterior = $data['is_primary_contact']
                    ? $this->demotePreviousPrimary($aluno)
                    : null;

                $link = StudentGuardian::query()->create([
                    'id' => (string) Str::uuid(),
                    'student_id' => $aluno,
                    'guardian_id' => $guardian->id,
                    ...$this->linkFields($data),
                ]);

                app(AuditTrail::class)->record('tenant', 'created', StudentGuardian::class, $link->id, null, $this->linkSnapshot($link));

                return [$reused, $principalAnterior, $guardian->full_name, $contactUpdated];
            });
        } catch (QueryException $exception) {
            $this->rethrowUniqueViolation($exception);
        }

        $message = match (true) {
            $reused && $contactUpdated => 'Responsável já cadastrado na rede: o vínculo foi feito e os dados de contato foram atualizados.',
            $reused => 'Responsável já cadastrado na rede: o vínculo foi feito com os dados de contato que já estavam no cadastro.',
            default => 'Responsável vinculado ao aluno com sucesso.',
        };
        if ($principalAnterior !== null) {
            $promotion = "{$guardianName} passou a ser o contato principal (antes: {$principalAnterior}).";
            $message = $reused ? $message.' '.$promotion : $promotion;
        }

        if ($request->input('depois') === 'outro') {
            return redirect()->route('school.students.guardians.create', [
                'municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $aluno,
            ])->with('status', $message.' Agora informe o próximo responsável.');
        }

        return redirect()->route('school.students.show', [
            'municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $aluno,
        ])->with('status', $message);
    }

    public function edit(Request $request, string $municipio, string $escola, string $aluno, string $vinculo): View
    {
        [$municipality, $school, $student] = $this->authorizedStudent($request, $escola, $aluno);
        $link = StudentGuardian::query()->where('student_id', $student->id)->with('guardian')->findOrFail($vinculo);
        $linkedStudentsCount = StudentGuardian::query()->where('guardian_id', $link->guardian_id)->count();

        return view('guardians.form', [
            'municipality' => $municipality,
            'school' => $school,
            'student' => $student,
            'link' => $link,
            'guardian' => $link->guardian,
            'relationships' => self::RELATIONSHIPS,
            'linkedStudentsCount' => $linkedStudentsCount,
        ]);
    }

    public function update(Request $request, string $municipio, string $escola, string $aluno, string $vinculo): RedirectResponse
    {
        [$municipality, $school] = $this->authorizedStudent($request, $escola, $aluno);
        $data = $this->validatedData($request, editing: true);

        try {
            [$principalAnterior, $guardianName] = DB::connection('tenant')->transaction(function () use ($aluno, $school, $vinculo, $data): array {
                $this->lockedStudent($aluno, $school);
                $link = StudentGuardian::query()->where('student_id', $aluno)->lockForUpdate()->findOrFail($vinculo);
                $guardian = Guardian::query()->lockForUpdate()->findOrFail($link->guardian_id);

                if ($data['cpf'] !== null && Guardian::query()
                    ->where('cpf', $data['cpf'])->where('id', '!=', $guardian->id)->exists()) {
                    throw ValidationException::withMessages([
                        'cpf' => 'Este CPF já pertence a outro responsável. Para vincular a mesma pessoa a outro aluno, use Adicionar responsável.',
                    ]);
                }

                $beforeGuardian = $this->guardianSnapshot($guardian);
                $guardian->fill($this->guardianFields($data));
                if ($guardian->isDirty()) {
                    $guardian->save();
                    app(AuditTrail::class)->record('tenant', 'updated', Guardian::class, $guardian->id, $beforeGuardian, $this->guardianSnapshot($guardian));
                }

                $principalAnterior = $data['is_primary_contact']
                    ? $this->demotePreviousPrimary($aluno, $link->id)
                    : null;

                $beforeLink = $this->linkSnapshot($link);
                $link->fill($this->linkFields($data));
                if ($link->isDirty()) {
                    $link->save();
                    app(AuditTrail::class)->record('tenant', 'updated', StudentGuardian::class, $link->id, $beforeLink, $this->linkSnapshot($link));
                }

                return [$principalAnterior, $guardian->full_name];
            });
        } catch (QueryException $exception) {
            $this->rethrowUniqueViolation($exception);
        }

        $message = $principalAnterior === null
            ? 'Dados do responsável atualizados com sucesso.'
            : "{$guardianName} passou a ser o contato principal (antes: {$principalAnterior}).";

        return redirect()->route('school.students.show', [
            'municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $aluno,
        ])->with('status', $message);
    }

    public function unlinkForm(Request $request, string $municipio, string $escola, string $aluno, string $vinculo): View
    {
        [$municipality, $school, $student] = $this->authorizedStudent($request, $escola, $aluno);
        $link = StudentGuardian::query()->where('student_id', $student->id)->with('guardian')->findOrFail($vinculo);

        return view('guardians.unlink', [
            'municipality' => $municipality,
            'school' => $school,
            'student' => $student,
            'link' => $link,
            'guardian' => $link->guardian,
            'relationship' => self::RELATIONSHIPS[$link->relationship] ?? 'Outro',
        ]);
    }

    public function unlink(Request $request, string $municipio, string $escola, string $aluno, string $vinculo): RedirectResponse
    {
        [$municipality, $school] = $this->authorizedStudent($request, $escola, $aluno);
        $reason = $request->input('motivo');
        $request->merge(['motivo' => is_string($reason) ? trim($reason) : $reason]);

        $data = $request->validate([
            'motivo' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'motivo.required' => 'Informe o motivo do desvínculo.',
            'motivo.string' => 'Informe um motivo válido para o desvínculo.',
            'motivo.min' => 'O motivo deve ter pelo menos 10 caracteres.',
            'motivo.max' => 'O motivo deve ter no máximo 500 caracteres.',
        ], ['motivo' => 'Motivo']);

        [$guardianName, $stillLinked] = DB::connection('tenant')->transaction(function () use ($aluno, $school, $vinculo, $data): array {
            $this->lockedStudent($aluno, $school);
            $link = StudentGuardian::query()->where('student_id', $aluno)->lockForUpdate()->findOrFail($vinculo);
            $guardian = Guardian::query()->findOrFail($link->guardian_id);
            $before = $this->linkSnapshot($link);

            $link->delete();

            app(AuditTrail::class)->record(
                'tenant',
                'deleted',
                StudentGuardian::class,
                $link->id,
                $before,
                ['motivo' => $data['motivo']],
            );

            $stillLinked = StudentGuardian::query()->where('guardian_id', $guardian->id)->exists();

            return [$guardian->full_name, $stillLinked];
        });

        // Texto neutro: o responsável pode ser mãe, pai, avô, tia...
        $message = "O vínculo de {$guardianName} com o aluno foi removido.";
        if ($stillLinked) {
            $message .= ' O cadastro continua disponível para os irmãos.';
        }

        return redirect()->to(route('school.students.show', [
            'municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $aluno,
        ]).'#responsaveis')->with('status', $message);
    }

    private function authorizedStudent(Request $request, string $escola, string $aluno): array
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($escola);
        Gate::authorize('manageStudents', [$school, $municipality]);

        $student = Student::query()->whereHas('enrollments', function ($query) use ($school): void {
            $query->where('school_id', $school->id)->where('status', 'active');
        })->find($aluno);
        abort_unless($student, 403, 'Este aluno não tem matrícula ativa nesta escola.');

        return [$municipality, $school, $student];
    }

    private function lockedStudent(string $aluno, School $school): void
    {
        $student = Student::query()->whereHas('enrollments', function ($query) use ($school): void {
            $query->where('school_id', $school->id)->where('status', 'active');
        })->lockForUpdate()->find($aluno);
        abort_unless($student, 403, 'A matrícula do aluno não está mais ativa nesta escola.');
    }

    private function validatedData(Request $request, bool $editing = false): array
    {
        $name = $request->input('full_name');
        $cpf = $request->input('cpf');
        $phone = $request->input('phone');
        $email = $request->input('email');
        $digits = fn (mixed $value): mixed => is_string($value) ? (preg_replace('/\D/', '', $value) ?: null) : $value;
        $workplace = $request->input('workplace');

        $request->merge([
            'full_name' => is_string($name) ? preg_replace('/\s+/u', ' ', trim($name)) : $name,
            'cpf' => is_string($cpf) && trim($cpf) !== '' ? (Cpf::normalize($cpf) ?? trim($cpf)) : $cpf,
            'phone' => is_string($phone) ? preg_replace('/\D/', '', $phone) : $phone,
            'email' => is_string($email) ? trim($email) : $email,
            'phone_secondary' => $digits($request->input('phone_secondary')),
            'workplace' => is_string($workplace) ? (preg_replace('/\s+/u', ' ', trim($workplace)) ?: null) : $workplace,
            'work_phone' => $digits($request->input('work_phone')),
            'is_legal_guardian' => $request->boolean('is_legal_guardian'),
            'is_primary_contact' => $request->boolean('is_primary_contact'),
        ]);

        return $request->validate([
            'full_name' => ['required', 'string', 'max:180'],
            'cpf' => ['nullable', 'string', new Cpf],
            'phone' => [$editing ? 'required' : 'nullable', 'string', 'regex:/\A\d{10,11}\z/D'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone_secondary' => ['nullable', 'string', 'regex:/\A\d{10,11}\z/D'],
            'workplace' => ['nullable', 'string', 'max:120'],
            'work_phone' => ['nullable', 'string', 'regex:/\A\d{10,11}\z/D'],
            'relationship' => ['required', Rule::in(array_keys(self::RELATIONSHIPS))],
            'is_legal_guardian' => ['boolean'],
            'is_primary_contact' => ['boolean'],
        ], [
            'full_name.required' => 'Informe o nome completo do responsável.',
            'phone.required' => 'Informe um telefone para contato.',
            'phone.regex' => 'Informe um telefone com DDD e 10 ou 11 números.',
            'email.email' => 'Informe um e-mail válido.',
            'phone_secondary.regex' => 'O outro telefone precisa ter DDD e 10 ou 11 números.',
            'work_phone.regex' => 'O telefone do trabalho precisa ter DDD e 10 ou 11 números.',
            'workplace.max' => 'O local de trabalho aceita até 120 caracteres.',
            'relationship.required' => 'Selecione o parentesco com o aluno.',
            'relationship.in' => 'Selecione um parentesco válido.',
        ], [
            'full_name' => 'Nome completo', 'cpf' => 'CPF', 'phone' => 'Telefone',
            'email' => 'E-mail', 'relationship' => 'Parentesco',
            'phone_secondary' => 'Outro telefone', 'workplace' => 'Onde trabalha', 'work_phone' => 'Telefone do trabalho',
            'is_legal_guardian' => 'Responsável legal', 'is_primary_contact' => 'Contato principal',
        ]);
    }

    private function guardianFields(array $data): array
    {
        $fields = array_intersect_key($data, array_flip(['full_name', 'cpf', 'phone', 'email', 'phone_secondary', 'workplace', 'work_phone']));
        // O mesmo número repetido como "outro telefone" não acrescenta contato.
        if (($fields['phone_secondary'] ?? null) !== null && $fields['phone_secondary'] === ($fields['phone'] ?? null)) {
            $fields['phone_secondary'] = null;
        }

        return $fields;
    }

    private function linkFields(array $data): array
    {
        return array_intersect_key($data, array_flip(['relationship', 'is_legal_guardian', 'is_primary_contact']));
    }

    private function guardianSnapshot(Guardian $guardian): array
    {
        return $guardian->only(['id', 'full_name', 'cpf', 'phone', 'email', 'phone_secondary', 'workplace', 'work_phone']);
    }

    private function linkSnapshot(StudentGuardian $link): array
    {
        return $link->only(['id', 'student_id', 'guardian_id', 'relationship', 'is_legal_guardian', 'is_primary_contact']);
    }

    private function normalizedName(string $name): string
    {
        $collapsed = preg_replace('/\\s+/u', ' ', trim($name)) ?? trim($name);

        return Str::lower(Str::ascii($collapsed));
    }

    /**
     * A linha do aluno já está bloqueada; primeiro libera o índice parcial,
     * depois o chamador marca o novo principal, tudo na mesma transação.
     */
    private function demotePreviousPrimary(string $studentId, ?string $exceptLink = null): ?string
    {
        $previous = StudentGuardian::query()
            ->where('student_id', $studentId)
            ->where('is_primary_contact', true)
            ->with('guardian');

        if ($exceptLink !== null) {
            $previous->where('id', '!=', $exceptLink);
        }

        $previous = $previous->lockForUpdate()->first();
        if ($previous === null) {
            return null;
        }

        $before = $this->linkSnapshot($previous);
        $previous->is_primary_contact = false;
        $previous->save();
        app(AuditTrail::class)->record('tenant', 'updated', StudentGuardian::class, $previous->id, $before, $this->linkSnapshot($previous));

        return $previous->guardian->full_name;
    }

    private function rethrowUniqueViolation(QueryException $exception): never
    {
        if (($exception->errorInfo[0] ?? '') === '23505') {
            throw ValidationException::withMessages([
                'cpf' => 'Este vínculo ou contato principal já existe. Confira os responsáveis cadastrados para o aluno.',
            ]);
        }

        throw $exception;
    }
}
