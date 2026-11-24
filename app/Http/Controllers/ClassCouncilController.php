<?php

namespace App\Http\Controllers;

use App\Academics\CouncilRoster;
use App\Academics\GradeAccess;
use App\Academics\PedagogicalAccess;
use App\Audit\AuditTrail;
use App\Models\Tenant\AcademicPeriod;
use App\Models\Tenant\ClassCouncil;
use App\Models\Tenant\ClassCouncilStudent;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Security\TwoFactorSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ClassCouncilController extends Controller
{
    public function __construct(private readonly PedagogicalAccess $access, private readonly GradeAccess $grades, private readonly CouncilRoster $roster) {}

    public function index(Request $request, string $municipio, string $escola, string $turma): Response
    {
        [$municipality, $school, $class, $director] = $this->context($request, $escola, $turma);
        $periods = AcademicPeriod::query()->where('school_year_id', $class->school_year_id)->orderBy('number')->get();
        $period = $request->filled('periodo') ? $periods->firstWhere('id', $request->query('periodo')) : $periods->first();
        abort_if($request->filled('periodo') && $period === null, 404);
        $council = $period === null ? null : ClassCouncil::query()->where('school_class_id', $class->id)->where('academic_period_id', $period->id)->first();
        $rows = $period === null ? [] : $this->roster->forPeriod($class, $period, $council);

        return response()->view('pedagogical.council', compact('municipality', 'school', 'class', 'director', 'periods', 'period', 'council', 'rows'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function save(Request $request, string $municipio, string $escola, string $turma): RedirectResponse
    {
        [$municipality, $school, $class, $director] = $this->context($request, $escola, $turma);
        $validator = Validator::make($request->all(), [
            'periodo' => ['required', 'uuid'],
            'meeting_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'participants' => ['nullable', 'string', 'max:500'],
            'class_actions' => ['nullable', 'string', 'max:1000'],
            'students' => ['nullable', 'array', 'max:2000'],
            'students.*' => ['array:opinion,referral,observation'],
            'students.*.opinion' => ['nullable', 'string', 'max:500'],
            'students.*.referral' => ['nullable', Rule::in(array_keys(ClassCouncilStudent::REFERRALS))],
            'students.*.observation' => ['nullable', 'string', 'max:500'],
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator);
        }
        $data = $validator->validated();
        if (! $director) {
            abort_if($request->hasAny(['meeting_date', 'participants', 'class_actions']), 403);
            foreach ($data['students'] ?? [] as $values) {
                abort_if(array_key_exists('referral', $values) || array_key_exists('observation', $values), 403);
            }
        }
        DB::connection('tenant')->transaction(function () use ($request, $class, $director, $data): void {
            SchoolClass::query()->lockForUpdate()->findOrFail($class->id);
            $period = AcademicPeriod::query()->where('school_year_id', $class->school_year_id)->lockForUpdate()->findOrFail($data['periodo']);
            $council = ClassCouncil::query()->where('school_class_id', $class->id)->where('academic_period_id', $period->id)->lockForUpdate()->first();
            abort_if($council?->status === 'completed', 409, 'Conselho concluído. Solicite reabertura à direção.');
            $students = $data['students'] ?? [];
            $ids = array_keys($students);
            abort_if(collect($ids)->contains(fn ($id) => ! Str::isUuid((string) $id)), 422);
            $allowed = $this->roster->enrollments($class, $period)->whereIn('id', $ids)->lockForUpdate()->pluck('id')->all();
            abort_unless(count($allowed) === count($ids), 403);
            $new = $council === null;
            $council ??= new ClassCouncil(['id' => Str::uuid()->toString(), 'school_class_id' => $class->id, 'academic_period_id' => $period->id]);
            $council->status = 'in_progress';
            if ($director) {
                $council->fill(array_intersect_key($data, array_flip(['meeting_date', 'participants', 'class_actions'])));
            }
            $fields = array_keys($council->getDirty());
            if ($council->isDirty()) {
                $council->save();
                $this->audit($council, $new ? 'created' : 'updated', $fields);
            }
            $records = ClassCouncilStudent::query()->where('class_council_id', $council->id)->whereIn('student_enrollment_id', $ids)->lockForUpdate()->get()->keyBy('student_enrollment_id');
            foreach ($students as $id => $values) {
                $values = array_intersect_key($values, array_flip($director ? ['opinion', 'referral', 'observation'] : ['opinion']));
                $record = $records->get($id);
                if ($record === null && count(array_filter($values, fn ($v) => filled($v))) === 0) {
                    continue;
                }
                $record ??= new ClassCouncilStudent(['id' => Str::uuid()->toString(), 'class_council_id' => $council->id, 'student_enrollment_id' => $id]);
                $record->fill($values);
                if ($record->isDirty()) {
                    $changed = array_keys($record->getDirty());
                    $record->updated_by = $request->user()->id;
                    $record->save();
                    app(AuditTrail::class)->record('tenant', 'pedagogical.council.student.updated', ClassCouncilStudent::class, $record->id, null, ['changed_fields' => implode(',', $changed)]);
                }
            }
        });

        return redirect()->route('pedagogical.council.index', ['municipio' => $municipality->slug, 'escola' => $school->id, 'turma' => $class->id, 'periodo' => $data['periodo']])->with('status', 'Conselho atualizado.');
    }

    public function transition(Request $request, string $municipio, string $escola, string $turma): RedirectResponse
    {
        [$municipality, $school, $class, $director] = $this->context($request, $escola, $turma);
        abort_unless($director, 403);
        // A confirmação recente não deve guardar o motivo em texto na sessão.
        if (! app(TwoFactorSession::class)->isRecentlyVerified($request, $request->user())) {
            abort(403, 'Confirme novamente o segundo fator antes de concluir ou reabrir o conselho.');
        }
        $validator = Validator::make($request->all(), [
            'periodo' => ['required', 'uuid'],
            'acao' => ['required', Rule::in(['complete', 'reopen'])],
            'motivo' => ['required_if:acao,reopen', 'nullable', 'string', 'min:10', 'max:500'],
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator);
        }
        $data = $validator->validated();
        DB::connection('tenant')->transaction(function () use ($request, $class, $data): void {
            SchoolClass::query()->lockForUpdate()->findOrFail($class->id);
            $period = AcademicPeriod::query()->where('school_year_id', $class->school_year_id)->lockForUpdate()->findOrFail($data['periodo']);
            $council = ClassCouncil::query()->where('school_class_id', $class->id)->where('academic_period_id', $period->id)->lockForUpdate()->firstOrFail();
            if ($data['acao'] === 'complete') {
                abort_unless($council->status === 'in_progress' && $council->meeting_date !== null, 422, 'Informe a data do conselho antes de concluir.');
                $council->fill(['status' => 'completed', 'closed_at' => now(), 'closed_by' => $request->user()->id]);
            } else {
                abort_unless($council->status === 'completed', 409);
                $council->fill(['status' => 'in_progress', 'closed_at' => null, 'closed_by' => null, 'reopened_at' => now(),
                    'reopened_by' => $request->user()->id, 'reopen_reason' => trim($data['motivo'])]);
            }
            $fields = array_keys($council->getDirty());
            $council->save();
            $this->audit($council, $data['acao'] === 'complete' ? 'completed' : 'reopened', $fields);
        });

        return redirect()->route('pedagogical.council.index', ['municipio' => $municipality->slug, 'escola' => $school->id, 'turma' => $class->id, 'periodo' => $data['periodo']])->with('status', 'Situação do conselho atualizada.');
    }

    private function audit(ClassCouncil $council, string $action, array $fields): void
    {
        app(AuditTrail::class)->record('tenant', 'pedagogical.council.'.$action, ClassCouncil::class, $council->id, null,
            ['status' => $council->status, 'changed_fields' => implode(',', $fields)]);
    }

    private function context(Request $request, string $schoolId, string $classId): array
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($schoolId);
        $class = SchoolClass::query()->where('school_id', $school->id)->where('status', 'active')->findOrFail($classId);
        abort_if($this->grades->isMunicipalManager($request->user(), $municipality), 403);
        $director = $this->access->directs($request->user(), $municipality, $school);
        abort_unless($director || $this->grades->isAssignedTeacher($request->user(), $municipality, $school, $class), 403);

        return [$municipality, $school, $class, $director];
    }
}
