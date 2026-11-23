<?php

namespace App\Http\Controllers;

use App\Audit\AuditTrail;
use App\Models\Tenant\School;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentSpecialEducation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Educação especial e AEE no padrão do Censo Escolar. Mesmo acesso da ficha
 * de saúde (direção, secretaria e gestão municipal), segundo fator para
 * gravar e auditoria só com os nomes dos campos alterados.
 */
class StudentSpecialEducationController extends Controller
{
    private const FIELDS = ['conditions', 'exam_resources', 'aee_status', 'aee_school_id', 'aee_started_on', 'support_professional'];

    public function edit(Request $request, string $municipio, string $escola, string $aluno): Response
    {
        [$municipality, $school, $student] = $this->authorizedStudent($request, $escola, $aluno);

        return response()->view('special-education.edit', [
            'municipality' => $municipality,
            'school' => $school,
            'student' => $student,
            'record' => StudentSpecialEducation::query()->find($student->id),
            'schools' => School::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, string $municipio, string $escola, string $aluno): RedirectResponse
    {
        [$municipality, $school, $student] = $this->authorizedStudent($request, $escola, $aluno);

        $data = $request->validate([
            'conditions' => ['nullable', 'array'],
            'conditions.*' => ['string', Rule::in(array_keys(StudentSpecialEducation::CONDITIONS))],
            'exam_resources' => ['nullable', 'array'],
            'exam_resources.*' => ['string', Rule::in(array_keys(StudentSpecialEducation::EXAM_RESOURCES))],
            'aee_status' => ['nullable', Rule::in(array_keys(StudentSpecialEducation::AEE_STATUSES))],
            'aee_school_id' => ['nullable', 'required_if:aee_status,participa', 'uuid', Rule::exists('tenant.schools', 'id')->where('status', 'active')],
            'aee_started_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'support_professional' => ['nullable', Rule::in(['sim', 'nao'])],
        ], [
            'conditions.*.in' => 'Escolha as categorias da lista do Censo.',
            'exam_resources.*.in' => 'Escolha os recursos da lista.',
            'aee_status.in' => 'Escolha a situação do AEE na lista.',
            'aee_school_id.required_if' => 'Informe a escola onde o aluno frequenta o AEE.',
            'aee_school_id.exists' => 'Escolha uma escola ativa da rede para o AEE.',
            'aee_started_on.date_format' => 'Informe a data de início no formato dia/mês/ano.',
            'aee_started_on.before_or_equal' => 'O início do AEE não pode estar no futuro.',
        ]);

        $participa = ($data['aee_status'] ?? null) === 'participa';
        $values = [
            'conditions' => array_values(array_unique($data['conditions'] ?? [])),
            'exam_resources' => array_values(array_unique($data['exam_resources'] ?? [])),
            'aee_status' => $data['aee_status'] ?? null,
            'aee_school_id' => $participa ? $data['aee_school_id'] : null,
            'aee_started_on' => $participa ? ($data['aee_started_on'] ?? null) : null,
            'support_professional' => match ($data['support_professional'] ?? null) {
                'sim' => true,
                'nao' => false,
                default => null,
            },
        ];

        DB::connection('tenant')->transaction(function () use ($student, $values, $request): void {
            $record = StudentSpecialEducation::query()->lockForUpdate()->find($student->id)
                ?? new StudentSpecialEducation(['student_id' => $student->id]);
            $created = ! $record->exists;
            $record->fill([...$values, 'updated_by' => $request->user()?->getAuthIdentifier()]);
            $changed = array_values(array_filter(self::FIELDS, fn (string $field): bool => $record->isDirty($field)));
            if ($created || $changed !== []) {
                $record->save();
                app(AuditTrail::class)->record('tenant', $created ? 'special_education.created' : 'special_education.updated',
                    StudentSpecialEducation::class, $student->id, null, ['student_id' => $student->id, 'changed_fields' => $changed]);
            }
        });

        return redirect()->to(route('school.students.show', ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id]).'#educacao-especial')
            ->with('status', 'Educação especial atualizada.');
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
}
