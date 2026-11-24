<?php

namespace App\Http\Controllers;

use App\Attendance\SchoolAbsenceAlerts;
use App\Educacenso\ConferenciaDeAlunos;
use App\Models\Tenant\School;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Alunos da escola no formato do Educacenso (registros 30 e 60), com o que
 * falta para cada um. Mostra só nome e pendências; os dados sensíveis ficam
 * nas telas próprias, com o acesso delas.
 */
class StudentsCensusController extends Controller
{
    public function index(Request $request, string $municipio, string $escola, ConferenciaDeAlunos $conferencia): Response
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($escola);
        Gate::authorize('manageStudents', [$school, $municipality]);

        $year = app(SchoolAbsenceAlerts::class)->currentYear();
        $rows = $year === null ? [] : ($conferencia->para(collect([$school]), $year)->get($school->id) ?? []);
        $filtro = $request->query('situacao') === 'pendentes' ? 'pendentes' : 'todos';

        return response()->view('census.students', [
            'municipality' => $municipality,
            'school' => $school,
            'year' => $year,
            'rows' => $rows,
            'filter' => $filtro,
            'canManageHealth' => Gate::allows('manageHealth', [$school, $municipality]),
        ])->header('Cache-Control', 'private, no-store');
    }
}
