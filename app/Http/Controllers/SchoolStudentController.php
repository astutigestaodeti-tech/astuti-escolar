<?php

namespace App\Http\Controllers;

use App\Audit\AuditTrail;
use App\Models\Tenant\School;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentGuardian;
use App\Models\Tenant\StudentPickupAuthorization;
use App\Students\StudentSearch;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SchoolStudentController extends Controller
{
    /** Filtro da lista => situação gravada pela importação do transporte (null = sem informação). */
    public const TRANSPORT_FILTERS = [
        'usa' => ['status' => 'active', 'label' => 'Usa transporte'],
        'nao_solicitado' => ['status' => 'not_requested', 'label' => 'Não solicitado'],
        'cancelado' => ['status' => 'cancelled', 'label' => 'Cancelado'],
        'sem_informacao' => ['status' => null, 'label' => 'Sem informação'],
    ];

    public function index(Request $request, string $municipio, string $escola): View
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($escola);
        Gate::authorize('view', [$school, $municipality]);

        $validated = $request->validate([
            'busca' => ['nullable', 'string', 'max:120'],
            'transporte' => ['nullable', 'in:'.implode(',', array_keys(self::TRANSPORT_FILTERS))],
        ]);
        $search = trim((string) ($validated['busca'] ?? ''));
        $transport = (string) ($validated['transporte'] ?? '');

        $activeHere = function ($enrollments) use ($school): void {
            $enrollments->where('school_id', $school->id)->where('status', 'active');
        };
        $query = Student::query()->whereHas('enrollments', $activeHere);

        if ($search !== '') {
            StudentSearch::apply($query, $search);
        }

        // Contagem por situação do transporte, sempre sobre a busca atual.
        $transportCounts = (clone $query)->join('student_enrollments as transporte_matricula', function ($join) use ($school): void {
            $join->on('transporte_matricula.student_id', '=', 'students.id')
                ->where('transporte_matricula.school_id', $school->id)
                ->where('transporte_matricula.status', 'active');
        })->selectRaw("coalesce(transporte_matricula.transport_status, 'sem_informacao') as situacao, count(distinct students.id) as total")
            ->groupBy('situacao')->pluck('total', 'situacao')->map(fn ($total): int => (int) $total)->all();

        if ($transport !== '') {
            $status = self::TRANSPORT_FILTERS[$transport]['status'];
            $query->whereHas('enrollments', function (Builder $enrollments) use ($activeHere, $status): void {
                $activeHere($enrollments);
                $status === null ? $enrollments->whereNull('transport_status') : $enrollments->where('transport_status', $status);
            });
        }

        $students = $query->with(['enrollments' => $activeHere])
            ->orderBy('full_name')->orderBy('students.id')->paginate(25)->withQueryString();

        $canManage = Gate::allows('manageStudents', [$school, $municipality]);
        $transportFilters = self::TRANSPORT_FILTERS;

        return view('students.index', compact('municipality', 'school', 'students', 'search', 'canManage', 'transport', 'transportCounts', 'transportFilters'));
    }

    public function show(Request $request, string $municipio, string $escola, string $aluno): View
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($escola);
        Gate::authorize('view', [$school, $municipality]);

        $student = Student::query()->whereHas('enrollments', function (Builder $enrollments) use ($school): void {
            $enrollments->where('school_id', $school->id)->where('status', 'active');
        })->findOrFail($aluno);

        $enrollments = $student->enrollments()
            ->where('school_id', $school->id)
            ->with(['schoolYear', 'schoolClass'])
            ->get()
            ->sortByDesc(fn ($enrollment): int => (int) ($enrollment->schoolYear?->year ?? 0));

        $guardianLinks = StudentGuardian::query()
            ->where('student_id', $student->id)
            ->with('guardian')
            ->orderByDesc('is_primary_contact')
            ->orderBy('created_at')
            ->get();

        $pickupAuthorizations = StudentPickupAuthorization::query()
            ->where('student_id', $student->id)
            ->orderBy('full_name')
            ->get()
            ->sortBy(fn (StudentPickupAuthorization $person): array => [$person->isExpired() ? 1 : 0, $person->full_name])
            ->values();

        app(AuditTrail::class)->record('tenant', 'student.viewed', Student::class, $student->id);

        $canManage = Gate::allows('manageStudents', [$school, $municipality]);
        $canDeleteEnrollment = Gate::allows('deleteEnrollment', [$school, $municipality]);

        return view('students.show', compact('municipality', 'school', 'student', 'enrollments', 'canManage', 'canDeleteEnrollment', 'guardianLinks', 'pickupAuthorizations'));
    }
}
