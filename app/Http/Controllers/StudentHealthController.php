<?php

namespace App\Http\Controllers;

use App\Audit\AuditTrail;
use App\Models\Tenant\School;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentHealthAlert;
use App\Models\Tenant\StudentHealthProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Ficha de saúde mínima: tipo sanguíneo opcional e alertas com o que fazer.
 * Dado sensível de criança (LGPD art. 11 e 14): só a gestão vê e edita tudo;
 * quem só consulta a escola vê os alertas graves, na ficha e na chamada.
 * Sem diagnóstico, CID ou laudo. A auditoria registra a mudança, não o valor.
 */
class StudentHealthController extends Controller
{
    public function edit(Request $request, string $municipio, string $escola, string $aluno): View
    {
        [$municipality, $school, $student] = $this->authorizedStudent($request, $escola, $aluno);
        $profile = StudentHealthProfile::query()->find($student->id);
        $alerts = StudentHealthAlert::query()->where('student_id', $student->id)
            ->orderByRaw("CASE WHEN severity = 'critical' THEN 0 ELSE 1 END")->orderBy('created_at')->get();

        return view('health.edit', compact('municipality', 'school', 'student', 'profile', 'alerts'));
    }

    public function updateProfile(Request $request, string $municipio, string $escola, string $aluno): RedirectResponse
    {
        [$municipality, $school, $student] = $this->authorizedStudent($request, $escola, $aluno);
        foreach (['blood_type', 'informed_by', 'informed_on'] as $field) {
            $value = $request->input($field);
            $request->merge([$field => is_string($value) ? (trim($value) ?: null) : $value]);
        }
        $data = $request->validate([
            'blood_type' => ['nullable', Rule::in([...StudentHealthProfile::BLOOD_TYPES, 'unknown'])],
            'informed_by' => ['nullable', 'string', 'max:120'],
            'informed_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ], [
            'blood_type.in' => 'Escolha um tipo sanguíneo da lista.',
            'informed_by.max' => 'Use até 120 caracteres em "Informado por".',
            'informed_on.date_format' => 'Informe a data no formato dia/mês/ano.',
            'informed_on.before_or_equal' => 'A data da informação não pode estar no futuro.',
        ]);

        DB::connection('tenant')->transaction(function () use ($student, $data, $request): void {
            $profile = StudentHealthProfile::query()->lockForUpdate()->find($student->id)
                ?? new StudentHealthProfile(['student_id' => $student->id]);
            $before = $profile->exists ? $this->profileSnapshot($profile) : null;
            $profile->fill([...$data, 'updated_by' => $request->user()?->getAuthIdentifier()]);
            if (! $profile->exists || $profile->isDirty(['blood_type', 'informed_by', 'informed_on'])) {
                $profile->save();
                app(AuditTrail::class)->record('tenant', $before === null ? 'health_profile.created' : 'health_profile.updated',
                    StudentHealthProfile::class, $student->id, $before, $this->profileSnapshot($profile));
            }
        });

        return $this->back($municipality->slug, $school->id, $student->id, 'Ficha de saúde atualizada.');
    }

    public function storeAlert(Request $request, string $municipio, string $escola, string $aluno): RedirectResponse
    {
        [$municipality, $school, $student] = $this->authorizedStudent($request, $escola, $aluno);
        foreach (['alert_text', 'care_instructions'] as $field) {
            $value = $request->input($field);
            $request->merge([$field => is_string($value) ? (preg_replace('/\s+/u', ' ', trim($value)) ?: null) : $value]);
        }
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(StudentHealthAlert::KINDS))],
            'severity' => ['required', Rule::in(array_keys(StudentHealthAlert::SEVERITIES))],
            'alert_text' => ['required', 'string', 'min:3', 'max:160'],
            'care_instructions' => [Rule::requiredIf($request->input('severity') === 'critical'), 'nullable', 'string', 'max:400'],
        ], [
            'kind.required' => 'Escolha o tipo de alerta.',
            'kind.in' => 'Escolha o tipo de alerta.',
            'severity.required' => 'Escolha se o alerta é grave ou de atenção.',
            'severity.in' => 'Escolha se o alerta é grave ou de atenção.',
            'alert_text.required' => 'Descreva o alerta em poucas palavras. Ex.: alergia a amendoim.',
            'alert_text.min' => 'Descreva o alerta em poucas palavras. Ex.: alergia a amendoim.',
            'alert_text.max' => 'Use até 160 caracteres na descrição do alerta.',
            'care_instructions.required' => 'Para alerta grave, diga o que o professor deve fazer.',
            'care_instructions.max' => 'Use até 400 caracteres em "O que fazer".',
        ]);

        DB::connection('tenant')->transaction(function () use ($student, $data, $request): void {
            Student::query()->lockForUpdate()->findOrFail($student->id);
            $alert = StudentHealthAlert::query()->create([
                'id' => (string) Str::uuid(),
                'student_id' => $student->id,
                ...$data,
                'created_by' => $request->user()?->getAuthIdentifier(),
            ]);
            app(AuditTrail::class)->record('tenant', 'health_alert.created', StudentHealthAlert::class, $alert->id, null, $this->alertSnapshot($alert));
        });

        return $this->back($municipality->slug, $school->id, $student->id, 'Alerta de saúde adicionado.');
    }

    public function destroyAlert(Request $request, string $municipio, string $escola, string $aluno, string $alerta): RedirectResponse
    {
        [$municipality, $school, $student] = $this->authorizedStudent($request, $escola, $aluno);
        $reason = $request->input('motivo');
        $request->merge(['motivo' => is_string($reason) ? trim($reason) : $reason]);
        $data = $request->validate(['motivo' => ['required', 'string', 'min:10', 'max:300']], [
            'motivo.required' => 'Informe por que o alerta está sendo removido.',
            'motivo.min' => 'O motivo deve ter pelo menos 10 caracteres.',
            'motivo.max' => 'O motivo deve ter no máximo 300 caracteres.',
        ]);

        DB::connection('tenant')->transaction(function () use ($student, $alerta): void {
            $alert = StudentHealthAlert::query()->where('student_id', $student->id)->lockForUpdate()->findOrFail($alerta);
            $before = $this->alertSnapshot($alert);
            $alert->delete();
            // O motivo pode conter dado de saúde: a auditoria guarda só que houve motivo.
            app(AuditTrail::class)->record('tenant', 'health_alert.removed', StudentHealthAlert::class, $alert->id, $before, ['motivo' => '[REDACTED]']);
        });

        return $this->back($municipality->slug, $school->id, $student->id, 'Alerta de saúde removido.');
    }

    /** @return array{0: mixed, 1: School, 2: Student} */
    private function authorizedStudent(Request $request, string $escola, string $aluno): array
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($escola);
        Gate::authorize('manageHealth', [$school, $municipality]);

        $student = Student::query()->whereHas('enrollments', fn ($query) => $query->where('school_id', $school->id)->where('status', 'active'))->find($aluno);
        abort_unless($student, 403, 'Este aluno não tem matrícula ativa nesta escola.');

        return [$municipality, $school, $student];
    }

    private function profileSnapshot(StudentHealthProfile $profile): array
    {
        return [
            'student_id' => $profile->student_id,
            'blood_type' => $profile->blood_type,
            'informed_by' => $profile->informed_by,
            'informed_on' => $profile->informed_on?->toDateString(),
        ];
    }

    private function alertSnapshot(StudentHealthAlert $alert): array
    {
        return $alert->only(['id', 'student_id', 'kind', 'severity', 'alert_text', 'care_instructions']);
    }

    private function back(string $municipio, string $escola, string $aluno, string $message): RedirectResponse
    {
        return redirect()->route('school.students.health.edit', ['municipio' => $municipio, 'escola' => $escola, 'aluno' => $aluno])
            ->with('status', $message);
    }
}
