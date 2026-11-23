<?php

namespace App\Http\Controllers;

use App\Attendance\MonthlyAttendanceReport;
use App\Models\Tenant\ClassTeacherAssignment;
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

        // Consulta independente da grade: as três consultas de frequência permanecem inalteradas.
        $principalName = ClassTeacherAssignment::query()
            ->join('staff_members', 'staff_members.id', '=', 'class_teacher_assignments.staff_member_id')
            ->where('class_teacher_assignments.school_class_id', $class->id)
            ->where('class_teacher_assignments.is_main', true)
            ->whereDate('class_teacher_assignments.starts_on', '<=', $month->copy()->endOfMonth()->toDateString())
            ->where(function ($query) use ($month): void {
                $query->whereNull('class_teacher_assignments.ends_on')
                    ->orWhereDate('class_teacher_assignments.ends_on', '>=', $month->copy()->startOfMonth()->toDateString());
            })
            ->orderByDesc('class_teacher_assignments.starts_on')
            ->value('staff_members.full_name');

        $previous = $month->copy()->subMonthNoOverflow();
        $next = $month->copy()->addMonthNoOverflow();

        return view('classes.monthly-attendance-report', [
            'municipality' => $municipality,
            'school' => $school,
            'class' => $class,
            'month' => $month,
            'principalName' => $principalName,
            'previousMonth' => $previous->lessThan($firstMonth) ? null : $previous->format('Y-m'),
            'nextMonth' => $next->greaterThan($lastMonth) ? null : $next->format('Y-m'),
            ...$report->forClass($class, $month),
        ]);
    }
}
