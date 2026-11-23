<?php

namespace App\Http\Controllers;

use App\Academics\GradeAccess;
use App\Academics\YearEndResults;
use App\Audit\AuditTrail;
use App\Models\Tenant\ClassYearClosure;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\StudentFinalResult;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClassYearEndController extends Controller
{
    private const OPTIONS = ['aprovado', 'retido', 'cursando'];

    public function index(
        Request $request, string $municipio, string $escola, string $turma,
        GradeAccess $access, YearEndResults $results,
    ): Response {
        [$municipality, $school, $class] = $this->classFor($request, $escola, $turma, $access);
        abort_unless($access->isManagement($request->user(), $municipality, $school), 403);

        $data = $results->forClass($class);

        return response()->view('academics.year-end', [
            'municipality' => $municipality, 'school' => $school, 'class' => $class,
            'data' => $data, 'canConfirm' => $this->canConfirm($request, $municipality->id, $school->id),
            'canReopen' => $access->isMunicipalManager($request->user(), $municipality),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function minutes(
        Request $request, string $municipio, string $escola, string $turma,
        GradeAccess $access, YearEndResults $results,
    ): Response {
        [$municipality, $school, $class] = $this->classFor($request, $escola, $turma, $access);
        $canManage = $access->isManagement($request->user(), $municipality, $school);
        $canTeacherRead = $access->isAssignedTeacher($request->user(), $municipality, $school, $class);
        abort_unless($canManage || $canTeacherRead, 403);

        $data = $results->forClass($class);

        return response()->view('academics.final-minutes', compact('municipality', 'school', 'class', 'data'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function confirm(
        Request $request, string $municipio, string $escola, string $turma,
        GradeAccess $access, YearEndResults $results,
    ): RedirectResponse {
        [$municipality, $school, $class] = $this->classFor($request, $escola, $turma, $access);
        abort_unless($this->canConfirm($request, $municipality->id, $school->id), 403);

        $data = $request->validate([
            'all' => ['nullable', 'boolean'],
            'enrollment' => ['required_unless:all,1', 'nullable', 'uuid'],
            'result' => ['required_unless:all,1', 'nullable', Rule::in(self::OPTIONS)],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $all = (string) ($data['all'] ?? '') === '1';
        $reason = trim((string) ($data['reason'] ?? ''));

        $changed = DB::connection('tenant')->transaction(function () use ($request, $class, $results, $all, $data, $reason): int {
            $closure = ClassYearClosure::query()->lockForUpdate()->findOrFail($class->id);
            if ($closure->closed_at !== null) {
                throw ValidationException::withMessages(['result' => 'O ano letivo da turma está fechado. Somente a gestão municipal pode reabrir.']);
            }

            // A sugestão é refeita dentro da transação. Nunca confiar no valor
            // enviado pelo navegador como se fosse decisão já aprovada.
            $calculated = $results->forClass($class);
            $entries = $calculated['records']->filter(fn (array $row): bool => $row['enrollment']->status === 'active');
            if ($all === false) {
                $entries = $entries->filter(fn (array $row): bool => $row['enrollment']->id === $data['enrollment']);
                if ($entries->isEmpty()) {
                    throw ValidationException::withMessages(['enrollment' => 'Matrícula não ativa ou não pertencente a esta turma.']);
                }
            }

            $changed = 0;
            foreach ($entries as $row) {
                $existing = StudentFinalResult::query()
                    ->where('school_class_id', $class->id)
                    ->where('student_enrollment_id', $row['enrollment']->id)
                    ->lockForUpdate()->first();
                if ($all && $existing !== null) {
                    // "Todas" confirma somente pendentes; jamais substitui uma
                    // decisão individual da direção ou da secretaria.
                    continue;
                }

                $suggestion = $row['suggestion'];
                $decision = $all ? $suggestion : $data['result'];
                $override = $decision !== $suggestion ? $reason : null;
                if ($override !== null && mb_strlen($override) < 10) {
                    throw ValidationException::withMessages([
                        'reason' => 'Quando o resultado difere da sugestão, justifique em pelo menos 10 caracteres.',
                    ]);
                }

                $before = $existing?->only(['id', 'student_enrollment_id', 'suggested_result', 'result', 'override_reason', 'confirmed_by', 'confirmed_at']);
                $values = [
                    'suggested_result' => $suggestion, 'result' => $decision,
                    'override_reason' => $override,
                    'confirmed_by' => $request->user()->id, 'confirmed_at' => now(),
                ];

                if ($existing === null) {
                    $record = StudentFinalResult::query()->create([
                        'id' => (string) Str::uuid(), 'school_class_id' => $class->id,
                        'student_enrollment_id' => $row['enrollment']->id,
                        ...$values,
                    ]);
                } else {
                    if ($existing->suggested_result === $suggestion && $existing->result === $decision
                        && $existing->override_reason === $override) {
                        continue;
                    }
                    $existing->forceFill($values)->save();
                    $record = $existing;
                }
                app(AuditTrail::class)->record('tenant',
                    $before === null ? 'academic.final_result.confirmed' : 'academic.final_result.corrected',
                    StudentFinalResult::class, $record->id,
                    $before, $record->only(['id', 'student_enrollment_id', 'suggested_result', 'result', 'override_reason', 'confirmed_by', 'confirmed_at']));
                $changed++;
            }

            return $changed;
        });

        return redirect()->route('school.classes.year-end.index', $this->routeParams($municipality->slug, $school->id, $class->id))
            ->with('status', $changed === 0 ? 'Nenhum resultado pendente ou alterado.' : $changed.' resultado(s) confirmado(s) pela gestão da escola.');
    }

    public function close(
        Request $request, string $municipio, string $escola, string $turma,
        GradeAccess $access, YearEndResults $results,
    ): RedirectResponse {
        [$municipality, $school, $class] = $this->classFor($request, $escola, $turma, $access);
        abort_unless($this->canConfirm($request, $municipality->id, $school->id), 403);
        $payload = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']]);

        DB::connection('tenant')->transaction(function () use ($request, $class, $results, $payload): void {
            $closure = ClassYearClosure::query()->lockForUpdate()->findOrFail($class->id);
            if ($closure->closed_at !== null) {
                throw ValidationException::withMessages(['reason' => 'Esta turma já está fechada.']);
            }
            $data = $results->forClass($class);
            if ($data['allPeriodsClosed'] === false) {
                throw ValidationException::withMessages(['reason' => 'Feche todos os quatro bimestres ou três trimestres antes de encerrar a turma.']);
            }
            $activeIds = $data['records']->filter(fn (array $row): bool => $row['enrollment']->status === 'active')
                ->pluck('enrollment.id');
            if ($activeIds->isEmpty()) {
                throw ValidationException::withMessages(['reason' => 'Não há matrículas ativas para confirmar nesta turma.']);
            }
            $confirmed = StudentFinalResult::query()->where('school_class_id', $class->id)
                ->whereIn('student_enrollment_id', $activeIds)->count();
            if ($confirmed !== $activeIds->count()) {
                throw ValidationException::withMessages(['reason' => 'Confirme manualmente o resultado de cada matrícula ativa antes de fechar a turma.']);
            }

            $closure->forceFill([
                'closed_at' => now(), 'closed_by' => $request->user()->id,
                'close_reason' => trim($payload['reason']),
            ])->save();
            app(AuditTrail::class)->record('tenant', 'academic.year.closed', ClassYearClosure::class, $class->id,
                ['closed_at' => null], $closure->only(['school_class_id', 'closed_at', 'closed_by', 'close_reason']));
        });

        return redirect()->route('school.classes.year-end.index', $this->routeParams($municipality->slug, $school->id, $class->id))
            ->with('status', 'Ano letivo da turma fechado. Notas e resultados protegidos contra alterações.');
    }

    public function reopen(
        Request $request, string $municipio, string $escola, string $turma,
        GradeAccess $access,
    ): RedirectResponse {
        [$municipality, $school, $class] = $this->classFor($request, $escola, $turma, $access);
        abort_unless($access->isMunicipalManager($request->user(), $municipality), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']]);

        DB::connection('tenant')->transaction(function () use ($request, $class, $data): void {
            $closure = ClassYearClosure::query()->lockForUpdate()->findOrFail($class->id);
            if ($closure->closed_at === null) {
                throw ValidationException::withMessages(['reason' => 'Este ano letivo ainda não está fechado.']);
            }
            $before = $closure->only(['school_class_id', 'closed_at', 'closed_by', 'close_reason']);
            $closure->forceFill([
                'closed_at' => null, 'closed_by' => null, 'close_reason' => null,
                'reopened_at' => now(), 'reopened_by' => $request->user()->id,
                'reopen_reason' => trim($data['reason']),
            ])->save();
            app(AuditTrail::class)->record('tenant', 'academic.year.reopened', ClassYearClosure::class, $class->id,
                $before, $closure->only(['school_class_id', 'closed_at', 'reopened_at', 'reopened_by', 'reopen_reason']));
        });

        return redirect()->route('school.classes.year-end.index', $this->routeParams($municipality->slug, $school->id, $class->id))
            ->with('status', 'Turma reaberta pela gestão municipal, com motivo e auditoria.');
    }

    private function classFor(Request $request, string $schoolId, string $classId, GradeAccess $access): array
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($schoolId);
        Gate::authorize('view', [$school, $municipality]);
        $class = SchoolClass::query()->where('school_id', $school->id)->where('status', 'active')
            ->with('schoolYear')->findOrFail($classId);
        abort_unless($access->isElementary($class), 404);

        return [$municipality, $school, $class];
    }

    private function canConfirm(Request $request, string $municipalityId, string $schoolId): bool
    {
        if ($request->user()?->status !== 'active') {
            return false;
        }
        // Gestão municipal não confirma resultados de crianças. Somente os
        // vínculos escolares locais podem tomar a decisão pedagógica.
        return DB::connection('platform')->table('memberships as m')
            ->join('school_scopes as scope', 'scope.membership_id', '=', 'm.id')
            ->where('m.user_id', $request->user()->id)
            ->where('m.municipality_id', $municipalityId)
            ->where('m.status', 'active')
            ->whereIn('m.role', ['school_secretary', 'school_director'])
            ->where('scope.school_id', $schoolId)->exists();
    }

    private function routeParams(string $municipality, string $school, string $class): array
    {
        return ['municipio' => $municipality, 'escola' => $school, 'turma' => $class];
    }
}
