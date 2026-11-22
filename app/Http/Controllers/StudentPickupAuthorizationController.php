<?php

namespace App\Http\Controllers;

use App\Audit\AuditTrail;
use App\Models\Tenant\School;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentPickupAuthorization;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class StudentPickupAuthorizationController extends Controller
{
    public function create(Request $request, string $municipio, string $escola, string $aluno): View
    {
        [$municipality, $school, $student] = $this->authorizedStudent($request, $escola, $aluno);

        return view('pickups.form', compact('municipality', 'school', 'student') + ['authorization' => null]);
    }

    public function store(Request $request, string $municipio, string $escola, string $aluno): RedirectResponse
    {
        [$municipality, $school] = $this->authorizedStudent($request, $escola, $aluno);
        $data = $this->validatedData($request);

        DB::connection('tenant')->transaction(function () use ($aluno, $school, $data): void {
            $this->lockedStudent($aluno, $school);

            $authorization = StudentPickupAuthorization::query()->create([
                'id' => (string) Str::uuid(),
                'student_id' => $aluno,
                ...$data,
            ]);

            app(AuditTrail::class)->record(
                'tenant', 'created', StudentPickupAuthorization::class, $authorization->id,
                null, $this->snapshot($authorization),
            );
        });

        return $this->backToStudent($municipality->slug, $school->id, $aluno, 'Autorização de retirada cadastrada com sucesso.');
    }

    public function edit(Request $request, string $municipio, string $escola, string $aluno, string $autorizacao): View
    {
        [$municipality, $school, $student] = $this->authorizedStudent($request, $escola, $aluno);
        $authorization = StudentPickupAuthorization::query()->where('student_id', $aluno)->findOrFail($autorizacao);

        return view('pickups.form', compact('municipality', 'school', 'student', 'authorization'));
    }

    public function update(Request $request, string $municipio, string $escola, string $aluno, string $autorizacao): RedirectResponse
    {
        [$municipality, $school] = $this->authorizedStudent($request, $escola, $aluno);
        $data = $this->validatedData($request);

        DB::connection('tenant')->transaction(function () use ($aluno, $school, $autorizacao, $data): void {
            $this->lockedStudent($aluno, $school);
            $authorization = StudentPickupAuthorization::query()
                ->where('student_id', $aluno)->lockForUpdate()->findOrFail($autorizacao);

            $before = $this->snapshot($authorization);
            $authorization->fill($data);
            if ($authorization->isDirty()) {
                $authorization->save();
                app(AuditTrail::class)->record(
                    'tenant', 'updated', StudentPickupAuthorization::class, $authorization->id,
                    $before, $this->snapshot($authorization),
                );
            }
        });

        return $this->backToStudent($municipality->slug, $school->id, $aluno, 'Autorização de retirada atualizada com sucesso.');
    }

    public function removeForm(Request $request, string $municipio, string $escola, string $aluno, string $autorizacao): View
    {
        [$municipality, $school, $student] = $this->authorizedStudent($request, $escola, $aluno);
        $authorization = StudentPickupAuthorization::query()->where('student_id', $aluno)->findOrFail($autorizacao);

        return view('pickups.remove', compact('municipality', 'school', 'student', 'authorization'));
    }

    public function remove(Request $request, string $municipio, string $escola, string $aluno, string $autorizacao): RedirectResponse
    {
        [$municipality, $school] = $this->authorizedStudent($request, $escola, $aluno);

        $reason = $request->input('motivo');
        $request->merge(['motivo' => is_string($reason) ? trim($reason) : $reason]);
        $data = $request->validate([
            'motivo' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'motivo.required' => 'Informe o motivo da remoção da autorização.',
            'motivo.string' => 'Informe um motivo válido.',
            'motivo.min' => 'O motivo deve ter pelo menos 10 caracteres.',
            'motivo.max' => 'O motivo deve ter no máximo 500 caracteres.',
        ], ['motivo' => 'Motivo']);

        DB::connection('tenant')->transaction(function () use ($aluno, $school, $autorizacao, $data): void {
            $this->lockedStudent($aluno, $school);
            $authorization = StudentPickupAuthorization::query()
                ->where('student_id', $aluno)->lockForUpdate()->findOrFail($autorizacao);
            $before = $this->snapshot($authorization);

            $authorization->delete();

            app(AuditTrail::class)->record(
                'tenant', 'deleted', StudentPickupAuthorization::class, $authorization->id,
                $before, ['motivo' => $data['motivo']],
            );
        });

        return $this->backToStudent($municipality->slug, $school->id, $aluno, 'Autorização de retirada removida. A pessoa não consta mais na lista de autorizados.');
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

    private function validatedData(Request $request): array
    {
        $name = $request->input('full_name');
        $relationship = $request->input('relationship');
        $phone = $request->input('phone');
        $document = $request->input('document');
        $notes = $request->input('notes');

        $request->merge([
            'full_name' => is_string($name) ? preg_replace('/\s+/u', ' ', trim($name)) : $name,
            'relationship' => is_string($relationship) ? (trim($relationship) ?: null) : $relationship,
            'phone' => is_string($phone) ? preg_replace('/\D/', '', $phone) : $phone,
            'document' => is_string($document) ? (trim($document) ?: null) : $document,
            'notes' => is_string($notes) ? (trim($notes) ?: null) : $notes,
            'expires_at' => $request->input('expires_at') === '' ? null : $request->input('expires_at'),
        ]);

        return $request->validate([
            'full_name' => ['required', 'string', 'max:180'],
            'relationship' => ['nullable', 'string', 'max:80'],
            'phone' => ['required', 'string', 'regex:/\A\d{10,11}\z/D'],
            'document' => ['nullable', 'string', 'max:40'],
            'expires_at' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:255'],
        ], [
            'full_name.required' => 'Informe o nome completo de quem pode buscar o aluno.',
            'full_name.max' => 'O nome completo deve ter no máximo 180 caracteres.',
            'relationship.max' => 'Informe uma relação com até 80 caracteres.',
            'phone.required' => 'Informe um telefone com DDD.',
            'phone.regex' => 'Informe um telefone com DDD e 10 ou 11 números.',
            'document.max' => 'O documento deve ter no máximo 40 caracteres.',
            'expires_at.date_format' => 'Informe uma validade no formato dia/mês/ano.',
            'notes.max' => 'A observação deve ter no máximo 255 caracteres.',
        ], [
            'full_name' => 'Nome completo',
            'relationship' => 'Parentesco ou relação',
            'phone' => 'Telefone',
            'document' => 'Documento para conferência',
            'expires_at' => 'Autorizado até',
            'notes' => 'Observação',
        ]);
    }

    private function snapshot(StudentPickupAuthorization $authorization): array
    {
        return $authorization->only(['id', 'student_id', 'full_name', 'relationship', 'phone', 'document', 'expires_at', 'notes']);
    }

    private function backToStudent(string $municipio, string $escola, string $aluno, string $message): RedirectResponse
    {
        return redirect()->to(route('school.students.show', [
            'municipio' => $municipio, 'escola' => $escola, 'aluno' => $aluno,
        ]).'#quem-pode-buscar')->with('status', $message);
    }
}
