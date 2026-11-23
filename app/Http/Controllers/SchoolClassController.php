<?php

namespace App\Http\Controllers;

use App\Attendance\AttendanceSummary;
use App\Models\Tenant\AttendanceSession;
use App\Models\Tenant\ClassTeacherAssignment;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\StaffMember;
use App\Models\Tenant\StudentEnrollment;
use App\Support\ClassAgeRule;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SchoolClassController extends Controller
{
    private const RULES = [
        'name' => ['required', 'string', 'max:120'],
        'stage' => ['required', 'string', 'max:120'],
        'modality' => ['required', 'string', 'max:120'],
        'shift' => ['required', 'string', 'max:60'],
        'capacity' => ['nullable', 'integer', 'between:1,32767'],
        'school_year_id' => ['required', 'uuid'],
    ];

    private const MESSAGES = [
        'name.required' => 'Informe o nome ou código da turma.',
        'stage.required' => 'Informe a etapa de ensino.',
        'modality.required' => 'Informe a modalidade.',
        'shift.required' => 'Informe o turno.',
        'school_year_id.required' => 'Escolha o ano letivo.',
        'school_year_id.uuid' => 'Escolha um ano letivo válido.',
        'capacity.integer' => 'Informe a quantidade de vagas em números inteiros.',
        'capacity.between' => 'Informe um número de vagas entre 1 e 32767.',
    ];

    private const ATTRIBUTES = [
        'name' => 'Nome ou código',
        'stage' => 'Etapa',
        'modality' => 'Modalidade',
        'shift' => 'Turno',
        'capacity' => 'Vagas',
        'school_year_id' => 'Ano letivo',
        'versao' => 'Versão do cadastro',
    ];

    public function index(Request $request, string $municipio, string $escola): View
    {
        [$municipality, $school] = $this->school($request, $escola);
        Gate::authorize('view', [$school, $municipality]);

        $classes = SchoolClass::query()
            ->where('school_id', $school->id)
            ->where('status', 'active')
            ->with('schoolYear')
            ->orderBy('name')
            ->get();
        $canManage = Gate::allows('manageStudents', [$school, $municipality]);

        return view('classes.index', compact('municipality', 'school', 'classes', 'canManage'));
    }

    public function show(Request $request, string $municipio, string $escola, string $turma): View
    {
        [$municipality, $school] = $this->school($request, $escola);
        Gate::authorize('view', [$school, $municipality]);

        $class = SchoolClass::query()
            ->where('school_id', $school->id)
            ->where('status', 'active')
            ->with('schoolYear')
            ->findOrFail($turma);

        $cutoff = Carbon::create((int) $class->schoolYear->year, 3, 31);
        $allowedAges = ClassAgeRule::allowedAges($class->stage, $class->name);

        $enrollments = StudentEnrollment::query()
            ->where('school_id', $school->id)
            ->where('school_year_id', $class->school_year_id)
            ->where('school_class_id', $class->id)
            ->where('status', 'active')
            ->with(['student.primaryGuardianLink.guardian'])
            ->get()
            ->sortBy(fn (StudentEnrollment $enrollment): string => Str::lower(Str::ascii((string) $enrollment->student?->full_name)).'|'.$enrollment->id)
            ->values();

        $frequency = app(AttendanceSummary::class)->forStudents($class, $enrollments->pluck('student_id'), today());
        $canManage = Gate::allows('manageStudents', [$school, $municipality]);
        $assignments = ClassTeacherAssignment::query()
            ->where('school_class_id', $class->id)
            ->with('staffMember')
            ->orderByDesc('starts_on')
            ->get();
        $availableTeachers = $canManage
            ? StaffMember::query()->where('school_id', $school->id)
                ->where('status', 'active')->where('role', 'teacher')
                ->orderBy('full_name')->get()
            : collect();
        $recentDates = AttendanceSession::query()
            ->where('school_class_id', $class->id)
            ->whereDate('attendance_date', '<=', today())
            ->orderByDesc('attendance_date')
            ->limit(11)
            ->get(['attendance_date']);
        $todaySessionExists = $recentDates->contains(
            fn (AttendanceSession $session): bool => $session->attendance_date->isToday()
        );
        $previousDates = $recentDates->filter(
            fn (AttendanceSession $session): bool => $session->attendance_date->lt(today())
        )->take(10);

        $roster = $enrollments->map(function (StudentEnrollment $enrollment) use ($cutoff, $allowedAges, $frequency): array {
            $student = $enrollment->student;
            $age = $student?->ageOn($cutoff);

            return [
                'student' => $student,
                'age' => $age,
                'needsAgeReview' => $age !== null && $allowedAges !== null && in_array($age, $allowedAges, true) === false,
                'primaryContact' => $student?->primaryGuardianLink?->guardian,
                'frequency' => $frequency[$student->id] ?? ['percentage' => null, 'sessions' => 0, 'present' => 0, 'alert' => null],
            ];
        });

        $occupied = $roster->count();
        $capacity = $class->capacity === null ? null : (int) $class->capacity;
        $occupancyPercentage = $capacity !== null && $capacity > 0
            ? (int) round($occupied * 100 / $capacity)
            : null;
        $visualPercentage = min(100, $occupancyPercentage ?? 0);
        $aboveCapacity = $capacity !== null && $occupied > $capacity;

        return view('classes.show', compact(
            'municipality', 'school', 'class', 'roster', 'cutoff',
            'occupied', 'capacity', 'occupancyPercentage', 'visualPercentage', 'aboveCapacity',
            'canManage', 'todaySessionExists', 'previousDates', 'assignments', 'availableTeachers',
        ));
    }

    public function create(Request $request, string $municipio, string $escola): View
    {
        [$municipality, $school] = $this->managedSchool($request, $escola);

        return view('classes.form', [
            'municipality' => $municipality,
            'school' => $school,
            'class' => null,
            'years' => $this->availableYears(),
        ]);
    }

    public function store(Request $request, string $municipio, string $escola): RedirectResponse
    {
        [$municipality, $school] = $this->managedSchool($request, $escola);
        $data = $this->validatedData($request);

        try {
            DB::connection('tenant')->transaction(function () use ($data, $school): void {
                $this->assertAllowedYear($data['school_year_id']);
                $this->assertUniqueName($school, $data);

                SchoolClass::query()->create([
                    'id' => (string) Str::uuid(),
                    'school_id' => $school->id,
                    'school_year_id' => $data['school_year_id'],
                    'name' => $data['name'],
                    'stage' => $data['stage'],
                    'modality' => $data['modality'],
                    'shift' => $data['shift'],
                    'capacity' => $data['capacity'] ?? null,
                    'status' => 'active',
                ]);
            });
        } catch (QueryException $exception) {
            $this->rethrowDuplicateName($exception);
        }

        return redirect()
            ->route('school.classes.index', ['municipio' => $municipality->slug, 'escola' => $school->id])
            ->with('status', 'Turma cadastrada com sucesso.');
    }

    public function edit(Request $request, string $municipio, string $escola, string $turma): View
    {
        [$municipality, $school] = $this->managedSchool($request, $escola);
        $class = $this->activeClassOf($school)->findOrFail($turma);

        return view('classes.form', [
            'municipality' => $municipality,
            'school' => $school,
            'class' => $class,
            'years' => $this->availableYears($class),
        ]);
    }

    public function update(Request $request, string $municipio, string $escola, string $turma): RedirectResponse
    {
        [$municipality, $school] = $this->managedSchool($request, $escola);
        $this->activeClassOf($school)->findOrFail($turma);
        $data = $this->validatedData($request, editing: true);

        try {
            $changed = DB::connection('tenant')->transaction(function () use ($data, $school, $turma): bool {
                $class = $this->activeClassOf($school)->lockForUpdate()->findOrFail($turma);

                if ((string) $class->row_token !== $data['versao']) {
                    throw ValidationException::withMessages([
                        'versao' => 'Esta turma foi alterada por outra pessoa. Abra o formulário novamente.',
                    ]);
                }

                $this->assertAllowedYear($data['school_year_id'], $class->school_year_id);
                if ($data['school_year_id'] !== $class->school_year_id
                    && StudentEnrollment::withTrashed()->where('school_class_id', $class->id)->exists()) {
                    throw ValidationException::withMessages([
                        'school_year_id' => 'Esta turma já tem matrículas vinculadas. O ano letivo não pode ser alterado.',
                    ]);
                }

                $this->assertUniqueName($school, $data, $class->id);
                $class->fill([
                    'school_year_id' => $data['school_year_id'],
                    'name' => $data['name'],
                    'stage' => $data['stage'],
                    'modality' => $data['modality'],
                    'shift' => $data['shift'],
                    'capacity' => $data['capacity'] ?? null,
                ]);

                if ($class->isDirty() === false) {
                    return false;
                }

                $class->save();

                return true;
            });
        } catch (QueryException $exception) {
            $this->rethrowDuplicateName($exception);
        }

        return redirect()
            ->route('school.classes.index', ['municipio' => $municipality->slug, 'escola' => $school->id])
            ->with('status', $changed ? 'Turma atualizada com sucesso.' : 'Nenhuma alteração foi feita.');
    }

    private function school(Request $request, string $escola): array
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($escola);

        return [$municipality, $school];
    }

    private function managedSchool(Request $request, string $escola): array
    {
        [$municipality, $school] = $this->school($request, $escola);
        Gate::authorize('manageStudents', [$school, $municipality]);

        return [$municipality, $school];
    }

    private function activeClassOf(School $school): Builder
    {
        return SchoolClass::query()
            ->selectRaw('school_classes.*, xmin::text AS row_token')
            ->where('school_id', $school->id)
            ->where('status', 'active');
    }

    private function availableYears(?SchoolClass $class = null)
    {
        return SchoolYear::query()
            ->where(function (Builder $query) use ($class): void {
                $query->whereIn('status', ['active', 'planned']);
                if ($class !== null) {
                    $query->orWhereKey($class->school_year_id);
                }
            })
            ->orderByDesc('year')
            ->get();
    }

    private function validatedData(Request $request, bool $editing = false): array
    {
        $normalized = [];
        foreach (['name', 'stage', 'modality', 'shift'] as $field) {
            $value = $request->input($field);
            $normalized[$field] = is_string($value)
                ? preg_replace('/\s+/u', ' ', trim($value))
                : $value;
        }
        $request->merge($normalized);

        return $request->validate([
            ...self::RULES,
            ...($editing ? ['versao' => ['required', 'string', 'max:40']] : []),
        ], self::MESSAGES, self::ATTRIBUTES);
    }

    private function assertAllowedYear(string $yearId, ?string $currentYearId = null): void
    {
        $year = SchoolYear::query()->lockForUpdate()->find($yearId);

        if ($year === null || (in_array($year->status, ['active', 'planned'], true) === false && $yearId !== $currentYearId)) {
            throw ValidationException::withMessages([
                'school_year_id' => 'Escolha um ano letivo em andamento ou planejado.',
            ]);
        }
    }

    private function assertUniqueName(School $school, array $data, ?string $exceptId = null): void
    {
        $query = SchoolClass::query()
            ->where('school_id', $school->id)
            ->where('school_year_id', $data['school_year_id'])
            ->where('status', 'active')
            ->whereRaw('lower(btrim(name)) = lower(btrim(?))', [$data['name']]);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'name' => 'Já existe uma turma ativa com este nome nesta escola e ano letivo.',
            ]);
        }
    }

    private function rethrowDuplicateName(QueryException $exception): never
    {
        if (($exception->errorInfo[0] ?? (string) $exception->getCode()) === '23505'
            && str_contains($exception->getMessage(), 'school_classes_active_name_unique')) {
            throw ValidationException::withMessages([
                'name' => 'Já existe uma turma ativa com este nome nesta escola e ano letivo.',
            ]);
        }

        throw $exception;
    }
}
