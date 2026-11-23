<?php

namespace App\Http\Controllers;

use App\Academics\GradeAccess;
use App\Audit\AuditTrail;
use App\Models\Tenant\AcademicPeriod;
use App\Models\Tenant\CurricularComponent;
use App\Models\Tenant\GradeEntry;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\StudentEnrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClassGradesController extends Controller
{
    public function index(Request $request, string $municipio, string $escola, string $turma, GradeAccess $access): Response
    {
        [$municipality, $school, $class] = $this->authorizedClass($request, $escola, $turma, $access);

        $selection = $request->validate([
            'periodo' => ['nullable', 'uuid'],
            'componente' => ['nullable', 'uuid'],
        ]);

        $periods = AcademicPeriod::query()
            ->where('school_year_id', $class->school_year_id)->orderBy('number')->get();
        $components = CurricularComponent::query()->where('active', true)
            ->orderBy('display_order')->orderBy('name')->get();

        $period = isset($selection['periodo'])
            ? $periods->firstWhere('id', $selection['periodo'])
            : $periods->first();
        $component = isset($selection['componente'])
            ? $components->firstWhere('id', $selection['componente'])
            : $components->first();

        if ((isset($selection['periodo']) && $period === null)
            || (isset($selection['componente']) && $component === null)) {
            abort(404);
        }

        $enrollments = StudentEnrollment::query()
            ->where('school_id', $school->id)
            ->where('school_year_id', $class->school_year_id)
            ->where('school_class_id', $class->id)
            ->where('status', 'active')
            ->whereHas('student', fn ($query) => $query->whereNull('deleted_at'))
            ->with('student:id,full_name')
            ->get()
            ->sortBy(fn (StudentEnrollment $enrollment): string => Str::lower(Str::ascii($enrollment->student?->full_name ?? '')).'|'.$enrollment->id)
            ->values();

        $grades = $period === null || $component === null || $enrollments->isEmpty()
            ? collect()
            : GradeEntry::query()
                ->where('school_class_id', $class->id)
                ->where('academic_period_id', $period->id)
                ->where('curricular_component_id', $component->id)
                ->whereIn('student_enrollment_id', $enrollments->pluck('id'))
                ->get()
                ->keyBy('student_enrollment_id');

        $isManagement = $access->isManagement($request->user(), $municipality, $school);
        $assigned = $access->isAssignedTeacher($request->user(), $municipality, $school, $class);
        $canWrite = $access->canWrite($request->user(), $municipality, $school, $class)
            && $period !== null && $component !== null
            && ($period->closed_at === null || $isManagement);

        $readOnlyReason = null;
        if (! $canWrite) {
            $readOnlyReason = $period?->closed_at !== null
                ? 'Período fechado: professores só consultam. A direção, a secretaria e a gestão podem corrigir mediante motivo e segundo fator recente.'
                : ($isManagement || $assigned
                    ? 'Cadastre os períodos e componentes para iniciar os lançamentos.'
                    : 'Sua conta está em consulta. Para lançar, é necessário vínculo teacher nesta escola e o mesmo e-mail institucional do servidor com atribuição vigente à turma.');
        }

        return response()->view('academics.grades', [
            'municipality' => $municipality,
            'school' => $school,
            'class' => $class,
            'periods' => $periods,
            'components' => $components,
            'period' => $period,
            'component' => $component,
            'enrollments' => $enrollments,
            'grades' => $grades,
            'isManagement' => $isManagement,
            'canWrite' => $canWrite,
            'readOnlyReason' => $readOnlyReason,
            'isMunicipalManager' => $access->isMunicipalManager($request->user(), $municipality),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(
        Request $request,
        string $municipio,
        string $escola,
        string $turma,
        GradeAccess $access,
    ): RedirectResponse {
        return $this->writeGrades($request, $escola, $turma, $access, false);
    }

    public function correct(
        Request $request,
        string $municipio,
        string $escola,
        string $turma,
        GradeAccess $access,
    ): RedirectResponse {
        return $this->writeGrades($request, $escola, $turma, $access, true);
    }

    private function writeGrades(
        Request $request,
        string $escola,
        string $turma,
        GradeAccess $access,
        bool $override,
    ): RedirectResponse {
        [$municipality, $school, $class] = $this->authorizedClass($request, $escola, $turma, $access);
        abort_unless($access->canWrite($request->user(), $municipality, $school, $class), 403);
        if ($override) {
            abort_unless($access->isManagement($request->user(), $municipality, $school), 403);
        }

        $data = $request->validate([
            'periodo' => ['required', 'uuid'],
            'componente' => ['required', 'uuid'],
            'grades' => ['required', 'array', 'max:2000'],
            'grades.*' => ['required', 'array'],
            'grades.*.grade' => ['nullable', 'string', 'regex:/\A(?:10(?:[.,]0)?|[0-9](?:[.,][0-9])?)\z/D'],
            'grades.*.absences' => ['nullable', 'integer', 'between:0,32767'],
            'reason' => $override ? ['required', 'string', 'min:10', 'max:500'] : ['nullable', 'string', 'max:500'],
        ], [
            'grades.*.grade.regex' => 'Informe nota entre 0 e 10, com no máximo uma casa decimal.',
        ]);

        $changed = DB::connection('tenant')->transaction(function () use ($request, $class, $school, $data, $override): int {
            $period = AcademicPeriod::query()
                ->where('school_year_id', $class->school_year_id)
                ->lockForUpdate()->findOrFail($data['periodo']);
            $component = CurricularComponent::query()->where('active', true)->findOrFail($data['componente']);

            if ($override !== ($period->closed_at !== null)) {
                throw ValidationException::withMessages([
                    'periodo' => $override
                        ? 'Este período ainda está aberto. Use o lançamento normal.'
                        : 'O período foi fechado. A correção exige permissão de gestão, motivo e segundo fator recente.',
                ]);
            }

            $ids = array_keys($data['grades']);
            $enrollments = StudentEnrollment::query()
                ->whereIn('id', $ids)
                ->where('school_id', $school->id)
                ->where('school_year_id', $class->school_year_id)
                ->where('school_class_id', $class->id)
                ->where('status', 'active')
                ->whereHas('student', fn ($query) => $query->whereNull('deleted_at'))
                ->lockForUpdate()->get(['id'])
                ->keyBy('id');
            if ($enrollments->count() !== count($ids)) {
                throw ValidationException::withMessages([
                    'grades' => 'A lista mudou ou contém matrícula de outra turma. Atualize a página.',
                ]);
            }

            $existing = GradeEntry::query()
                ->where('school_class_id', $class->id)
                ->where('academic_period_id', $period->id)
                ->where('curricular_component_id', $component->id)
                ->whereIn('student_enrollment_id', $ids)
                ->lockForUpdate()->get()->keyBy('student_enrollment_id');

            $changed = 0;
            foreach ($data['grades'] as $enrollmentId => $value) {
                $rawGrade = $value['grade'] ?? null;
                $grade = $rawGrade === null || $rawGrade === ''
                    ? null
                    : number_format((float) str_replace(',', '.', (string) $rawGrade), 1, '.', '');
                $absences = isset($value['absences']) && $value['absences'] !== ''
                    ? (int) $value['absences'] : null;
                $entry = $existing->get($enrollmentId);
                $isNew = $entry === null;
                $old = $entry === null ? ['grade' => null, 'absences' => null] : [
                    'grade' => $entry->grade, 'absences' => $entry->absences,
                ];

                if ($old['grade'] === $grade && $old['absences'] === $absences) {
                    continue;
                }
                if ($entry === null && $grade === null && $absences === null) {
                    continue;
                }

                $fields = [
                    'grade' => $grade, 'absences' => $absences,
                    'updated_by' => $request->user()->id,
                ];
                if ($entry === null) {
                    $entry = GradeEntry::query()->create([
                        'id' => (string) Str::uuid(),
                        'student_enrollment_id' => $enrollmentId,
                        'school_class_id' => $class->id,
                        'academic_period_id' => $period->id,
                        'curricular_component_id' => $component->id,
                        'recorded_by' => $request->user()->id,
                        ...$fields,
                    ]);
                } else {
                    $entry->fill($fields)->save();
                }

                app(AuditTrail::class)->record(
                    'tenant', $isNew ? 'academic.grade.created' : 'academic.grade.updated',
                    GradeEntry::class, $entry->id,
                    ['grade' => $old['grade'], 'absences' => $old['absences']],
                    [
                        'grade' => $grade, 'absences' => $absences,
                        'student_enrollment_id' => $enrollmentId,
                        'academic_period_id' => $period->id,
                        'curricular_component_id' => $component->id,
                        'change_reason' => $override ? trim($data['reason']) : null,
                    ],
                );
                $changed++;
            }

            return $changed;
        });

        return redirect()->route('school.classes.grades.index', [
            'municipio' => $municipality->slug,
            'escola' => $school->id,
            'turma' => $class->id,
            'periodo' => $data['periodo'],
            'componente' => $data['componente'],
        ])->with('status', $changed === 0
            ? 'Nenhuma nota ou falta foi alterada.'
            : $changed.' registro(s) atualizado(s) com auditoria.');
    }

    private function authorizedClass(Request $request, string $schoolId, string $classId, GradeAccess $access): array
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($schoolId);
        Gate::authorize('view', [$school, $municipality]);

        $class = SchoolClass::query()->where('school_id', $school->id)
            ->where('status', 'active')
            ->with('schoolYear')
            ->findOrFail($classId);
        abort_unless($access->isElementary($class), 404);

        return [$municipality, $school, $class];
    }
}
