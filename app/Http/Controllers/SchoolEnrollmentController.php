<?php

namespace App\Http\Controllers;

use App\Models\Tenant\School;
use App\Models\Tenant\StudentEnrollment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SchoolEnrollmentController extends Controller
{
    public function index(Request $request, string $municipio, string $escola): View|RedirectResponse
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

        $validator = Validator::make($request->query(), [
            'busca' => ['nullable', 'string', 'max:120'],
            'ano_letivo' => ['nullable', 'integer', Rule::in($availableYears)],
        ], [
            'busca.string' => 'Informe um nome válido para pesquisar.',
            'busca.max' => 'A busca deve conter no máximo 120 caracteres.',
            'ano_letivo.integer' => 'Escolha um ano letivo disponível para esta escola.',
            'ano_letivo.in' => 'Escolha um ano letivo disponível para esta escola.',
        ]);

        if ($validator->fails()) {
            // GET inválido precisa voltar a uma URL válida, para não prender o navegador
            // num redirecionamento repetido nem afastar a secretaria da sua escola.
            $searchTerm = $request->query('busca');
            $safeSearch = is_string($searchTerm) && mb_strlen($searchTerm) <= 120
                ? trim($searchTerm)
                : '';

            $filters = ['municipio' => $municipality->slug, 'escola' => $school->id];
            if ($safeSearch !== '') {
                $filters['busca'] = $safeSearch;
            }

            return redirect()->route('school.enrollments.index', $filters)
                ->withErrors($validator);
        }

        $validated = $validator->validated();
        $search = trim((string) ($validated['busca'] ?? ''));
        $selectedYear = isset($validated['ano_letivo']) ? (int) $validated['ano_letivo'] : null;

        if ($selectedYear !== null) {
            $query->where('school_years.year', $selectedYear);
        }

        if ($search !== '') {
            // Evita que % e _ enviados pelo usuário sejam interpretados como curingas SQL.
            $literalSearch = addcslashes($search, '\\%_');
            if ($query->getModel()->getConnection()->getDriverName() === 'pgsql') {
                $query->whereRaw('public.f_unaccent(lower(students.full_name)) LIKE public.f_unaccent(lower(?))', ['%'.$literalSearch.'%']);
            } else {
                $query->where('students.full_name', 'like', '%'.$literalSearch.'%');
            }
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
