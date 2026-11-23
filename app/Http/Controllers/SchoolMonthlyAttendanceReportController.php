<?php

namespace App\Http\Controllers;

use App\Attendance\MonthlyAttendanceReport;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SchoolMonthlyAttendanceReportController extends Controller
{
    public function index(Request $request, string $municipio, string $escola, string $turma, MonthlyAttendanceReport $report): View
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($escola);
        Gate::authorize('view', [$school, $municipality]);

        $class = SchoolClass::query()
            ->where('school_id', $school->id)
            ->where('status', 'active')
            ->with('schoolYear')
            ->findOrFail($turma);

        $validated = $request->validate([
            'mes' => ['nullable', 'date_format:Y-m'],
        ], [
            'mes.date_format' => 'Informe um mês válido no formato AAAA-MM.',
        ]);

        $firstMonth = $class->schoolYear->start_date->copy()->startOfMonth();
        $lastMonth = $class->schoolYear->end_date->copy()->startOfMonth();
        $today = today()->startOfMonth();
        $defaultMonth = $today->lessThan($firstMonth) ? $firstMonth : ($today->greaterThan($lastMonth) ? $lastMonth : $today);
        $month = isset($validated['mes'])
            ? Carbon::parse($validated['mes'].'-01')->startOfMonth()
            : $defaultMonth->copy();

        if ($month->lessThan($firstMonth) || $month->greaterThan($lastMonth)) {
            throw ValidationException::withMessages(['mes' => 'Escolha um mês dentro do ano letivo.']);
        }

        $previous = $month->copy()->subMonthNoOverflow();
        $next = $month->copy()->addMonthNoOverflow();

        return view('classes.monthly-attendance-report', [
            'municipality' => $municipality,
            'school' => $school,
            'class' => $class,
            'month' => $month,
            'previousMonth' => $previous->lessThan($firstMonth) ? null : $previous->format('Y-m'),
            'nextMonth' => $next->greaterThan($lastMonth) ? null : $next->format('Y-m'),
            ...$report->forClass($class, $month),
        ]);
    }
}
