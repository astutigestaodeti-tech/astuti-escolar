<?php

namespace App\Http\Controllers;

use App\Models\Tenant\School;
use App\Models\Tenant\StudentEnrollment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SchoolEnrollmentController extends Controller
{
    public function index(Request $request, string $municipio, string $escola): View
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($escola);
        Gate::authorize('view', [$school, $municipality]);

        $query = StudentEnrollment::query()
            ->join('students', 'students.id', '=', 'student_enrollments.student_id')
            ->join('school_years', 'school_years.id', '=', 'student_enrollments.school_year_id')
            ->select('student_enrollments.*')
            ->where('student_enrollments.school_id', $school->id)
            ->where('student_enrollments.status', 'active')
            ->whereNull('students.deleted_at')
            ->whereNull('school_years.deleted_at')
            ->with(['student', 'schoolYear']);

        // As opções têm o mesmo escopo já autorizado, sem revelar anos de outra unidade.
        $availableYears = (clone $query)
            ->reorder()
            ->select('school_years.year')
            ->distinct()
            ->orderByDesc('school_years.year')
            ->pluck('school_years.year')
            ->map(fn ($year): int => (int) $year)
            ->all();

        $validated = $request->validate([
            'busca' => ['nullable', 'string', 'max:120'],
            'ano_letivo' => ['nullable', 'integer', Rule::in($availableYears)],
        ]);
        $search = trim((string) ($validated['busca'] ?? ''));
        $selectedYear = isset($validated['ano_letivo']) ? (int) $validated['ano_letivo'] : null;

        if ($selectedYear !== null) {
            $query->where('school_years.year', $selectedYear);
        }

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

        return view('enrollments.index', compact('municipality', 'school', 'enrollments', 'search', 'availableYears', 'selectedYear'));
    }
}
