<?php

namespace App\Http\Controllers;

use App\Attendance\SchoolAbsenceAlerts;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SchoolAbsenceAlertsController extends Controller
{
    private const REASONS = [
        'sequencia' => '3 ou mais faltas seguidas',
        'mes' => 'Abaixo de 75% no mês',
        'ano' => 'Abaixo de 75% no ano',
    ];

    public function index(Request $request, string $municipio, string $escola, SchoolAbsenceAlerts $alerts): View
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($escola);

        // Autorizar antes da consulta, dos filtros e dos contatos familiares.
        Gate::authorize('manageStudents', [$school, $municipality]);

        $year = $alerts->currentYear();
        $classes = $year === null ? collect() : SchoolClass::query()
            ->where('school_id', $school->id)
            ->where('school_year_id', $year->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        $filters = $request->validate([
            'turma' => ['nullable', 'uuid', Rule::in($classes->pluck('id')->all())],
            'motivo' => ['nullable', Rule::in(array_keys(self::REASONS))],
        ], [
            'turma.in' => 'Escolha uma turma desta escola.',
            'turma.uuid' => 'Escolha uma turma válida.',
            'motivo.in' => 'Escolha um motivo de alerta válido.',
        ]);

        $students = $alerts->alertedStudents($school, $year, today())
            ->when(isset($filters['turma']), fn ($items) => $items->filter(
                fn (array $item): bool => $item['schoolClass']?->id === $filters['turma']
            ))
            ->when(isset($filters['motivo']), fn ($items) => $items->filter(
                fn (array $item): bool => in_array($filters['motivo'], $item['reasons'], true)
            ))
            ->values();

        return view('schools.absence-alerts', [
            'municipality' => $municipality,
            'school' => $school,
            'year' => $year,
            'classes' => $classes,
            'students' => $students,
            'reasons' => self::REASONS,
            'selectedClass' => $filters['turma'] ?? '',
            'selectedReason' => $filters['motivo'] ?? '',
        ]);
    }
}
