<?php

namespace App\Http\Controllers;

use App\Academics\GradeAccess;
use App\Academics\Literacy;
use App\Audit\AuditTrail;
use App\Models\Platform\Municipality;
use App\Models\Tenant\AcademicPeriod;
use App\Models\Tenant\ClassYearClosure;
use App\Models\Tenant\LiteracyAssessment;
use App\Models\Tenant\LiteracyCycleSetting;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\StudentEnrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LiteracyController extends Controller
{
    public function __construct(private readonly Literacy $literacy, private readonly GradeAccess $access) {}

    public function network(Request $request, string $municipio): Response
    {
        $municipality = $request->attributes->get('astuti.municipality');
        Gate::authorize('viewNetworkPanel', [School::class, $municipality]);

        return $this->panel($request, $municipality, null);
    }

    public function school(Request $request, string $municipio, string $escola): Response
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($escola);
        Gate::authorize('manageStudents', [$school, $municipality]);

        return $this->panel($request, $municipality, $school);
    }

    private function panel(Request $request, Municipality $municipality, ?School $school): Response
    {
        $input = $request->validate([
            'ano' => ['nullable', 'uuid'],
            'bimestre' => ['nullable', 'integer', 'between:1,4'],
        ]);
        $years = SchoolYear::query()->orderByDesc('year')->get();
        $year = isset($input['ano']) ? $years->firstWhere('id', $input['ano']) : $years->firstWhere('status', 'active') ?? $years->first();
        abort_if(isset($input['ano']) && $year === null, 404);
        $term = (int) ($input['bimestre'] ?? 4);
        $classes = $year ? $this->literacy->classes($year, $school?->id) : collect();
        $nominal = $school !== null && Gate::allows('viewLiteracySupportNames', [$school, $municipality]);
        $enrollments = $this->literacy->enrollments($classes, $nominal);
        $assessments = $this->literacy->assessments($enrollments);
        $byClass = $enrollments->groupBy('school_class_id');
        $classRows = $classes->map(function (SchoolClass $class) use ($byClass, $assessments): array {
            $entries = $byClass->get($class->id, collect());

            return ['class' => $class, 'distribution' => $this->literacy->distribution($entries, $assessments)];
        });
        $secondIds = $classes->filter(fn (SchoolClass $class): bool => Literacy::grade($class) === 2)->pluck('id');
        $second = $this->literacy->distribution($enrollments->whereIn('school_class_id', $secondIds), $assessments)[4];
        $distribution = $this->literacy->distribution($enrollments, $assessments);
        $schools = School::query()->whereIn('id', $classes->pluck('school_id'))->orderBy('name')->get(['id', 'name']);
        $schoolRows = $schools->map(fn (School $item): array => [
            'school' => $item,
            'distribution' => $this->literacy->distribution($enrollments->where('school_id', $item->id), $assessments),
        ]);
        $support = $nominal ? $this->supportRows($enrollments, $assessments, $term) : collect();
        $settings = $year && $school === null ? $this->literacy->settings($year->id) : null;

        return response()->view('literacy.panel', compact(
            'municipality', 'school', 'year', 'years', 'term', 'distribution', 'second',
            'schoolRows', 'classRows', 'support', 'settings', 'nominal',
        ))->header('Cache-Control', 'no-store, private');
    }

    private function supportRows(Collection $enrollments, Collection $assessments, int $term): Collection
    {
        $items = $assessments->where('term', $term)->keyBy('student_enrollment_id');

        return $enrollments->map(function (StudentEnrollment $entry) use ($items): ?array {
            $assessment = $items->get($entry->id);
            if ($assessment === null || ! $this->literacy->needsSupport($assessment)) {
                return null;
            }

            return ['enrollment' => $entry, 'assessment' => $assessment];
        })->filter()->sortBy(fn (array $row): string => Str::lower(Str::ascii($row['enrollment']->student->full_name)))->values();
    }

    public function classroom(Request $request, string $municipio, string $escola, string $turma): Response
    {
        [$municipality, $school, $class] = $this->authorizedClass($request, $escola, $turma);
        $input = $request->validate(['bimestre' => ['nullable', 'integer', 'between:1,4']]);
        $term = (int) ($input['bimestre'] ?? 1);
        $enrollments = $this->literacy->enrollments(collect([$class]), true)
            ->sortBy(fn (StudentEnrollment $entry): string => Str::lower(Str::ascii($entry->student->full_name)))->values();
        $assessments = $this->literacy->assessments($enrollments);
        $distribution = $this->literacy->distribution($enrollments, $assessments);
        $records = $assessments->where('term', $term)->keyBy('student_enrollment_id');
        $closed = ClassYearClosure::query()->whereKey($class->id)->whereNotNull('closed_at')->exists();
        $periodClosed = $this->period($class, $term)?->closed_at !== null;
        $management = $this->access->isManagement($request->user(), $municipality, $school);
        $canWrite = ! $closed && (! $periodClosed || $management)
            && $this->access->canWrite($request->user(), $municipality, $school, $class);

        return response()->view('literacy.classroom', compact(
            'municipality', 'school', 'class', 'term', 'enrollments', 'distribution', 'records', 'closed', 'periodClosed', 'canWrite',
        ))->header('Cache-Control', 'no-store, private');
    }

    public function save(Request $request, string $municipio, string $escola, string $turma): RedirectResponse
    {
        [$municipality, $school, $class] = $this->authorizedClass($request, $escola, $turma);
        abort_unless($this->access->canWrite($request->user(), $municipality, $school, $class), 403);
        $input = $request->validate([
            'bimestre' => ['required', 'integer', 'between:1,4'],
            'matricula' => ['required', 'uuid'],
            'escrita' => ['required', Rule::in(array_keys(LiteracyAssessment::WRITING))],
            'leitura' => ['required', Rule::in(array_keys(LiteracyAssessment::READING))],
            'motivo' => ['nullable', 'string', 'min:10', 'max:500'],
        ]);
        $term = (int) $input['bimestre'];
        $management = $this->access->isManagement($request->user(), $municipality, $school);
        DB::connection('tenant')->transaction(function () use ($request, $school, $class, $input, $term, $management): void {
            // Mesma ordem de bloqueios da gravação de notas: período e turma.
            $period = $this->period($class, $term, true);
            $closure = ClassYearClosure::query()->lockForUpdate()->findOrFail($class->id);
            if ($closure->closed_at !== null) {
                throw ValidationException::withMessages(['bimestre' => 'Ano letivo encerrado. Solicite reabertura à gestão municipal.']);
            }
            if ($period?->closed_at !== null && (! $management || mb_strlen(trim($input['motivo'] ?? '')) < 10)) {
                throw ValidationException::withMessages(['motivo' => 'Bimestre fechado: somente a gestão pode corrigir com justificativa.']);
            }

            $entry = StudentEnrollment::query()->where('school_id', $school->id)
                ->where('school_year_id', $class->school_year_id)->where('school_class_id', $class->id)
                ->where('status', 'active')->whereHas('student', fn ($query) => $query->whereNull('deleted_at'))
                ->lockForUpdate()->findOrFail($input['matricula']);
            $record = LiteracyAssessment::query()->where('school_class_id', $class->id)
                ->where('student_enrollment_id', $entry->id)->where('term', $term)->lockForUpdate()->first();
            $before = $record?->only(['writing', 'reading']);
            $values = ['writing' => $input['escrita'], 'reading' => $input['leitura']];
            if ($before === $values) {
                return;
            }
            $record ??= new LiteracyAssessment([
                'id' => (string) Str::uuid(), 'school_class_id' => $class->id,
                'student_enrollment_id' => $entry->id, 'term' => $term, 'recorded_by' => $request->user()->id,
            ]);
            $record->fill([...$values, 'updated_by' => $request->user()->id])->save();
            app(AuditTrail::class)->record('tenant', $before === null ? 'literacy.assessment.created' : 'literacy.assessment.updated',
                LiteracyAssessment::class, $record->id, $before, [
                    ...$record->only(['school_class_id', 'student_enrollment_id', 'term', 'writing', 'reading', 'updated_by']),
                    'change_reason' => $period?->closed_at !== null ? trim($input['motivo']) : null,
                ]);
        });

        return redirect()->route('school.literacy.classroom', [
            'municipio' => $municipality->slug, 'escola' => $school->id, 'turma' => $class->id, 'bimestre' => $term,
        ])->with('status', 'Sondagem registrada.');
    }

    public function settings(Request $request, string $municipio): RedirectResponse
    {
        $municipality = $request->attributes->get('astuti.municipality');
        Gate::authorize('viewNetworkPanel', [School::class, $municipality]);
        $input = $request->validate([
            'ano' => ['required', 'uuid'],
            'retain_first' => ['required', 'boolean'],
            'retain_second' => ['required', 'boolean'],
        ]);
        DB::connection('tenant')->transaction(function () use ($input, $request): void {
            $year = SchoolYear::query()->lockForUpdate()->findOrFail($input['ano']);
            $record = LiteracyCycleSetting::query()->where('school_year_id', $year->id)->lockForUpdate()->first();
            $before = $record?->only(['retain_first', 'retain_second']);
            $record ??= new LiteracyCycleSetting(['id' => (string) Str::uuid(), 'school_year_id' => $year->id]);
            $record->fill([
                'retain_first' => (bool) $input['retain_first'], 'retain_second' => (bool) $input['retain_second'],
                'updated_by' => $request->user()->id,
            ])->save();
            app(AuditTrail::class)->record('tenant', 'literacy.cycle.updated', LiteracyCycleSetting::class, $record->id,
                $before, $record->only(['school_year_id', 'retain_first', 'retain_second', 'updated_by']));
        });

        return redirect()->route('literacy.network', ['municipio' => $municipality->slug, 'ano' => $input['ano']])
            ->with('status', 'Política do ciclo atualizada. Resultados confirmados foram preservados.');
    }

    private function period(SchoolClass $class, int $term, bool $lock = false): ?AcademicPeriod
    {
        return AcademicPeriod::query()->where('school_year_id', $class->school_year_id)
            ->where('kind', 'bimestre')->where('number', $term)
            ->when($lock, fn ($query) => $query->lockForUpdate())->first();
    }

    private function authorizedClass(Request $request, string $schoolId, string $classId): array
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($schoolId);
        Gate::authorize('view', [$school, $municipality]);
        $class = SchoolClass::query()->where('school_id', $school->id)->where('status', 'active')
            ->with('schoolYear')->findOrFail($classId);
        abort_unless(Literacy::grade($class) !== null && $this->access->isElementary($class), 404);
        abort_unless($this->access->canWrite($request->user(), $municipality, $school, $class), 403);

        return [$municipality, $school, $class];
    }
}
