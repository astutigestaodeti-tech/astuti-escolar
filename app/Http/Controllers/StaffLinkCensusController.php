<?php

namespace App\Http\Controllers;

use App\Audit\AuditTrail;
use App\Educacenso\Leiaute;
use App\Educacenso\OpcoesDeCampo;
use App\Educacenso\Registro40;
use App\Educacenso\Registro50;
use App\Educacenso\Tabelas;
use App\Models\Tenant\ClassTeacherAssignment;
use App\Models\Tenant\ClassTeacherAssignmentCensus;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolCensus;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolClassCensus;
use App\Models\Tenant\StaffManagerCensus;
use App\Models\Tenant\StaffMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class StaffLinkCensusController extends Controller
{
    public function manager(Request $request, string $municipio, string $escola, string $servidor): RedirectResponse
    {
        [$municipality, $school] = $this->school($request, $escola);
        $data = $request->validate([
            'position' => ['required', 'integer', 'between:1,2'],
            'access_criterion' => ['nullable', 'integer', 'between:1,7'],
            'employment_status' => ['nullable', 'integer', 'between:1,4'],
        ]);
        DB::connection('tenant')->transaction(function () use ($school, $servidor, $data, $request): void {
            $member = StaffMember::query()->where('school_id', $school->id)->lockForUpdate()->findOrFail($servidor);
            abort_unless(StaffManagerCensus::eligible($member), 403);
            $record = StaffManagerCensus::query()->find($member->id) ?? new StaffManagerCensus(['staff_member_id' => $member->id]);
            $record->fill(['position' => (int) $data['position'], 'access_criterion' => isset($data['access_criterion']) ? (int) $data['access_criterion'] : null,
                'employment_status' => isset($data['employment_status']) ? (int) $data['employment_status'] : null]);
            $registro = Registro40::para($school, $member, $record, SchoolCensus::query()->find($school->id));
            $this->validateLink($registro->pendencias, [5 => 'position', 6 => 'access_criterion', 7 => 'employment_status']);
            $changed = array_keys(array_intersect_key($record->getDirty(), array_flip(['position', 'access_criterion', 'employment_status'])));
            if (! $record->exists || $changed !== []) {
                $action = $record->exists ? 'updated' : 'created';
                $record->updated_by = $request->user()?->getAuthIdentifier();
                $record->save();
                app(AuditTrail::class)->record('tenant', 'staff_manager_census.'.$action, StaffManagerCensus::class, $member->id, null, ['staff_member_id' => $member->id, 'changed_fields' => $changed]);
            }
        });

        return redirect()->route('school.staff.census.edit', ['municipio' => $municipality->slug, 'escola' => $school->id, 'servidor' => $servidor])
            ->with('status', 'Vínculo de gestão no Censo atualizado.');
    }

    public function edit(Request $request, string $municipio, string $escola, string $turma, string $atribuicao): Response
    {
        [$municipality, $school] = $this->school($request, $escola);
        $class = $this->classOf($school, $turma);
        $assignment = $this->assignmentOf($class, $atribuicao);
        $classCensus = SchoolClassCensus::query()->find($class->id);
        $record = ClassTeacherAssignmentCensus::query()->find($assignment->id);
        $ano = Tabelas::anoMaisRecente();
        $leiaute = Leiaute::do($ano);
        $schoolCensus = SchoolCensus::query()->find($school->id);

        return response()->view('census.assignment-form', [
            'municipality' => $municipality, 'school' => $school, 'class' => $class, 'assignment' => $assignment,
            'record' => $record, 'classCensus' => $classCensus,
            'functions' => OpcoesDeCampo::de($leiaute->campo('50', 7)),
            'employment' => OpcoesDeCampo::de($leiaute->campo('50', 8)),
            'components' => Registro50::componentesOferecidos($classCensus, Tabelas::do($ano)),
            'registro' => Registro50::para($school, $assignment, $record, $classCensus, $schoolCensus, $ano),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, string $municipio, string $escola, string $turma, string $atribuicao): RedirectResponse
    {
        [$municipality, $school] = $this->school($request, $escola);
        $data = $request->validate([
            'class_function' => ['required', 'integer', 'between:1,9'],
            'employment_status' => ['nullable', 'integer', 'between:1,4'],
            'components' => ['nullable', 'array', 'list', 'max:25'],
            'components.*' => ['required', 'integer', 'distinct', 'between:1,99'],
        ]);
        DB::connection('tenant')->transaction(function () use ($school, $turma, $atribuicao, $data, $request): void {
            $class = $this->classOf($school, $turma, true);
            $assignment = $this->assignmentOf($class, $atribuicao, true);
            $classCensus = SchoolClassCensus::query()->find($class->id);
            $record = ClassTeacherAssignmentCensus::query()->find($assignment->id) ?? new ClassTeacherAssignmentCensus(['assignment_id' => $assignment->id]);
            $record->fill(['class_function' => (int) $data['class_function'], 'employment_status' => isset($data['employment_status']) ? (int) $data['employment_status'] : null,
                'components' => array_map('intval', $data['components'] ?? [])]);
            if ($classCensus === null && $record->components !== []) {
                throw ValidationException::withMessages(['components' => 'Cadastre primeiro os componentes oferecidos pela turma no Censo.']);
            }
            $registro = Registro50::para($school, $assignment, $record, $classCensus, SchoolCensus::query()->find($school->id));
            $this->validateLink($registro->pendencias, [7 => 'class_function', 8 => 'employment_status'] + array_fill_keys(range(9, 33), 'components'));
            $changed = array_keys(array_intersect_key($record->getDirty(), array_flip(['class_function', 'employment_status', 'components'])));
            if (! $record->exists || $changed !== []) {
                $action = $record->exists ? 'updated' : 'created';
                $record->updated_by = $request->user()?->getAuthIdentifier();
                $record->save();
                app(AuditTrail::class)->record('tenant', 'class_teacher_assignment_census.'.$action, ClassTeacherAssignmentCensus::class, $assignment->id, null, ['assignment_id' => $assignment->id, 'changed_fields' => $changed]);
            }
        });

        return redirect()->route('school.classes.teachers.census.edit', ['municipio' => $municipality->slug, 'escola' => $school->id, 'turma' => $turma, 'atribuicao' => $atribuicao])
            ->with('status', 'Vínculo profissional no Censo atualizado.');
    }

    private function validateLink(array $pendencias, array $campos): void
    {
        $errors = [];
        foreach ($pendencias as $pendencia) {
            if ($pendencia->recusa() && isset($campos[$pendencia->campo])) {
                $errors[$campos[$pendencia->campo]][] = $pendencia->mensagem;
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function school(Request $request, string $id): array
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($id);
        Gate::authorize('manageStudents', [$school, $municipality]);

        return [$municipality, $school];
    }

    private function classOf(School $school, string $id, bool $lock = false): SchoolClass
    {
        return SchoolClass::query()->where('school_id', $school->id)->where('status', 'active')
            ->when($lock, fn ($query) => $query->lockForUpdate())->findOrFail($id);
    }

    private function assignmentOf(SchoolClass $class, string $id, bool $lock = false): ClassTeacherAssignment
    {
        return ClassTeacherAssignment::query()->where('school_class_id', $class->id)
            ->whereHas('staffMember', fn ($query) => $query->where('school_id', $class->school_id)->whereNull('deleted_at'))
            ->when($lock, fn ($query) => $query->lockForUpdate())->findOrFail($id);
    }
}
