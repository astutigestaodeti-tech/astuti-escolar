<?php

namespace App\Http\Controllers;

use App\Audit\AuditTrail;
use App\Models\Tenant\ClassTeacherAssignment;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\StaffMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClassTeacherAssignmentController extends Controller
{
    public function store(Request $request, string $municipio, string $escola, string $turma): RedirectResponse
    {
        [$municipality, $school] = $this->managedSchool($request, $escola);
        $data = $request->validate([
            'staff_member_id' => ['required', 'uuid'],
            'subject' => ['nullable', 'string', 'max:80'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'is_main' => ['nullable', 'boolean'],
        ], [
            'staff_member_id.required' => 'Escolha um professor da escola.',
            'staff_member_id.uuid' => 'Escolha um professor válido.',
            'subject.max' => 'A disciplina deve ter no máximo 80 caracteres.',
            'starts_on.required' => 'Informe a data de início da atribuição.',
            'starts_on.date_format' => 'Informe uma data de início válida.',
        ]);

        DB::connection('tenant')->transaction(function () use ($school, $turma, $data, $request): void {
            $class = SchoolClass::query()
                ->where('school_id', $school->id)->where('status', 'active')
                ->lockForUpdate()->findOrFail($turma);
            $staff = StaffMember::query()->where('school_id', $school->id)
                ->where('status', 'active')->where('role', 'teacher')
                ->lockForUpdate()->find($data['staff_member_id']);
            if ($staff === null) {
                throw ValidationException::withMessages([
                    'staff_member_id' => 'Escolha um professor ativo desta escola.',
                ]);
            }

            $main = $request->boolean('is_main');
            if ($main && ClassTeacherAssignment::query()
                ->where('school_class_id', $class->id)->where('is_main', true)
                ->whereNull('ends_on')->exists()) {
                throw ValidationException::withMessages([
                    'is_main' => 'Esta turma já tem professor principal em aberto. Encerre a atribuição anterior primeiro.',
                ]);
            }
            if (ClassTeacherAssignment::query()
                ->where('school_class_id', $class->id)
                ->where('staff_member_id', $staff->id)
                ->whereNull('ends_on')->exists()) {
                throw ValidationException::withMessages([
                    'staff_member_id' => 'Este professor já possui uma atribuição em aberto nesta turma.',
                ]);
            }

            $assignment = ClassTeacherAssignment::query()->create([
                'id' => (string) Str::uuid(),
                'school_class_id' => $class->id,
                'staff_member_id' => $staff->id,
                'subject' => isset($data['subject']) ? trim($data['subject']) : null,
                'is_main' => $main,
                'starts_on' => $data['starts_on'],
                'ends_on' => null,
            ]);

            app(AuditTrail::class)->record(
                'tenant', 'created', ClassTeacherAssignment::class, $assignment->id,
                null, $this->snapshot($assignment),
            );
        });

        return redirect()->route('school.classes.show', $this->params($municipality->slug, $school->id, $turma))
            ->with('status', 'Professor atribuído à turma.');
    }

    public function end(Request $request, string $municipio, string $escola, string $turma, string $atribuicao): RedirectResponse
    {
        [$municipality, $school] = $this->managedSchool($request, $escola);
        $data = $request->validate([
            'ends_on' => ['required', 'date_format:Y-m-d'],
        ], [
            'ends_on.required' => 'Informe a data de encerramento da atribuição.',
            'ends_on.date_format' => 'Informe uma data válida.',
        ]);

        DB::connection('tenant')->transaction(function () use ($school, $turma, $atribuicao, $data): void {
            $class = SchoolClass::query()
                ->where('school_id', $school->id)->where('status', 'active')
                ->lockForUpdate()->findOrFail($turma);
            $assignment = ClassTeacherAssignment::query()
                ->where('school_class_id', $class->id)
                ->whereNull('ends_on')->lockForUpdate()->findOrFail($atribuicao);
            if ($data['ends_on'] < $assignment->starts_on->toDateString()) {
                throw ValidationException::withMessages([
                    'ends_on' => 'A data final não pode ser anterior ao início da atribuição.',
                ]);
            }

            $before = $this->snapshot($assignment);
            $assignment->ends_on = $data['ends_on'];
            $assignment->save();

            app(AuditTrail::class)->record(
                'tenant', 'updated', ClassTeacherAssignment::class, $assignment->id,
                $before, $this->snapshot($assignment),
            );
        });

        return redirect()->route('school.classes.show', $this->params($municipality->slug, $school->id, $turma))
            ->with('status', 'Atribuição encerrada. O histórico da turma foi preservado.');
    }

    private function snapshot(ClassTeacherAssignment $assignment): array
    {
        return $assignment->only([
            'id', 'school_class_id', 'staff_member_id', 'subject',
            'is_main', 'starts_on', 'ends_on',
        ]);
    }

    private function managedSchool(Request $request, string $id): array
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($id);
        Gate::authorize('manageStudents', [$school, $municipality]);

        return [$municipality, $school];
    }

    private function params(string $slug, string $schoolId, string $classId): array
    {
        return ['municipio' => $slug, 'escola' => $schoolId, 'turma' => $classId];
    }
}
