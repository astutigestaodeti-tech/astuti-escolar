<?php

namespace App\Http\Controllers;

use App\Models\Tenant\School;
use App\Models\Tenant\StudentEnrollment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SchoolEnrollmentController extends Controller
{
    public function index(Request $request, string $municipio, string $escola): View
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($escola);
        Gate::authorize('view', [$school, $municipality]);

        $validated = $request->validate(['busca' => ['nullable', 'string', 'max:120']]);
        $search = trim((string) ($validated['busca'] ?? ''));

        $query = StudentEnrollment::query()
            ->join('students', 'students.id', '=', 'student_enrollments.student_id')
            ->join('school_years', 'school_years.id', '=', 'student_enrollments.school_year_id')
            ->select('student_enrollments.*')
            ->where('student_enrollments.school_id', $school->id)
            ->where('student_enrollments.status', 'active')
            ->whereNull('students.deleted_at')
            ->with(['student', 'schoolYear']);

        if ($search !== '') {
            // Evita que % e _ enviados pelo usuário sejam interpretados como curingas SQL.
            $literalSearch = addcslashes($search, '\\%_');
            $operator = $query->getModel()->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where('students.full_name', $operator, '%'.$literalSearch.'%');
        }

        $enrollments = $query
            ->orderByDesc('school_years.year')
            ->orderBy('students.full_name')
            ->orderBy('student_enrollments.id')
            ->paginate(25)
            ->withQueryString();

        return view('enrollments.index', compact('municipality', 'school', 'enrollments', 'search'));
    }
}
