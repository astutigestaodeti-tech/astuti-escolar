<?php

namespace App\Http\Controllers;

use App\Audit\AuditTrail;
use App\Models\Tenant\School;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentGuardian;
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
            $this->applySearch($query, $search);
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

        app(AuditTrail::class)->record('tenant', 'student.viewed', Student::class, $student->id);

        $canManage = Gate::allows('manageStudents', [$school, $municipality]);
        $canDeleteEnrollment = Gate::allows('deleteEnrollment', [$school, $municipality]);

        return view('students.show', compact('municipality', 'school', 'student', 'enrollments', 'canManage', 'canDeleteEnrollment', 'guardianLinks'));
    }

    /**
     * Uma caixa só: nome do aluno ou do responsável (sem acento), RA ou CPF
     * (só pelos números, com ou sem pontuação).
     */
    private function applySearch(Builder $query, string $search): void
    {
        // Evita que % e _ enviados pelo usuário funcionem como curingas SQL.
        $literal = '%'.addcslashes($search, '\\%_').'%';
        $digits = preg_replace('/\D+/', '', $search) ?? '';
        $pgsql = $query->getModel()->getConnection()->getDriverName() === 'pgsql';
        $nameMatch = $pgsql ? 'public.f_unaccent(lower(%s)) LIKE public.f_unaccent(lower(?))' : '%s LIKE ?';

        $query->where(function (Builder $where) use ($literal, $digits, $pgsql, $nameMatch): void {
            $where->whereRaw(sprintf($nameMatch, 'students.full_name'), [$literal])
                ->orWhereRaw(sprintf($nameMatch, 'students.social_name'), [$literal])
                ->orWhereExists(function ($guardians) use ($literal, $nameMatch): void {
                    $guardians->selectRaw('1')->from('student_guardians')
                        ->join('guardians', 'guardians.id', '=', 'student_guardians.guardian_id')
                        ->whereColumn('student_guardians.student_id', 'students.id')
                        ->whereRaw(sprintf($nameMatch, 'guardians.full_name'), [$literal]);
                });

            if ($pgsql && strlen($digits) >= 4) {
                $where->orWhereRaw("regexp_replace(coalesce(students.ra, ''), '[^0-9]', '', 'g') LIKE ?", ['%'.$digits.'%']);
            }
            if ($pgsql && strlen($digits) === 11) {
                $where->orWhereRaw("regexp_replace(coalesce(students.cpf, ''), '[^0-9]', '', 'g') = ?", [$digits]);
            }
        });
    }
}
