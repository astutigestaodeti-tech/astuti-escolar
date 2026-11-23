<?php

namespace App\Http\Controllers;

use App\Models\Tenant\School;
use App\Students\RegistrationPendencies;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Lista do que falta completar no cadastro dos alunos, com o atalho para corrigir. */
class SchoolPendenciesController extends Controller
{
    private const LIMIT = 300;

    public function index(Request $request, string $municipio, string $escola, RegistrationPendencies $pendencies): View
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($escola);
        Gate::authorize('manageStudents', [$school, $municipality]);

        $filters = $request->validate(
            ['tipo' => ['nullable', Rule::in(array_keys(RegistrationPendencies::TYPES))]],
            ['tipo.in' => 'Escolha uma pendência da lista.'],
        );
        $type = $filters['tipo'] ?? null;

        $students = $pendencies->forSchool($school);
        $summary = $pendencies->summary($students);
        $list = $pendencies->prioritized($students->filter(fn (array $student): bool => $type === null
            ? $student['pendencies'] !== []
            : in_array($type, $student['pendencies'], true)));

        return view('schools.pendencies', [
            'municipality' => $municipality,
            'school' => $school,
            'summary' => $summary,
            'students' => $list->take(self::LIMIT),
            'hidden' => max(0, $list->count() - self::LIMIT),
            'types' => RegistrationPendencies::TYPES,
            'selectedType' => $type,
            'schoolMapped' => $school->latitude !== null,
        ]);
    }
}
