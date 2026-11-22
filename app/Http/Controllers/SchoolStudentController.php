<?php

namespace App\Http\Controllers;

use App\Audit\AuditTrail;
use App\Models\Tenant\School;
use App\Models\Tenant\Student;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SchoolStudentController extends Controller
{
    public function index(Request $request, string $municipio, string $escola): View
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($escola);
        Gate::authorize('view', [$school, $municipality]);

        $validated = $request->validate(['busca' => ['nullable', 'string', 'max:120']]);
        $search = trim((string) ($validated['busca'] ?? ''));

        $query = Student::query()->whereHas('enrollments', function (Builder $enrollments) use ($school): void {
            $enrollments->where('school_id', $school->id)->where('status', 'active');
        });

        if ($search !== '') {
            // Evita que % e _ enviados pelo usuário funcionem como curingas SQL.
            $literalSearch = addcslashes($search, '\\%_');
            if ($query->getModel()->getConnection()->getDriverName() === 'pgsql') {
                $query->whereRaw('public.f_unaccent(lower(full_name)) LIKE public.f_unaccent(lower(?))', ['%'.$literalSearch.'%']);
            } else {
                $query->where('full_name', 'like', '%'.$literalSearch.'%');
            }
        }

        $students = $query->orderBy('full_name')->orderBy('id')->paginate(25)->withQueryString();

        $canManage = Gate::allows('manageStudents', [$school, $municipality]);

        return view('students.index', compact('municipality', 'school', 'students', 'search', 'canManage'));
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

        app(AuditTrail::class)->record('tenant', 'student.viewed', Student::class, $student->id);

        $canManage = Gate::allows('manageStudents', [$school, $municipality]);
        $canDeleteEnrollment = Gate::allows('deleteEnrollment', [$school, $municipality]);

        return view('students.show', compact('municipality', 'school', 'student', 'enrollments', 'canManage', 'canDeleteEnrollment'));
    }
}
