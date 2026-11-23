<?php

namespace App\Http\Controllers;

use App\Attendance\SchoolAbsenceAlerts;
use App\Audit\AuditTrail;
use App\Calendar\SchoolCalendar;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\StudentEnrollment;
use App\Students\RegistrationPendencies;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Painel agregado da rede, exclusivo para o gestor municipal.
 *
 * Custo previsível por escola: 1 consulta de matrículas, 1 de turmas, 1 de
 * transporte, 1 de pendências, 1 de calendário e até 3 de alertas de faltas.
 * O número de consultas não cresce com a quantidade de alunos.
 */
class NetworkPanelController extends Controller
{
    public function index(
        Request $request,
        string $municipio,
        SchoolAbsenceAlerts $alerts,
        RegistrationPendencies $pendencies,
        SchoolCalendar $calendar,
    ): View {
        $municipality = $request->attributes->get('astuti.municipality');
        Gate::authorize('viewNetworkPanel', [School::class, $municipality]);

        $validated = $request->validate([
            'ordenar' => ['nullable', Rule::in(['alertas', 'nome', 'pendencias'])],
        ]);
        $order = $validated['ordenar'] ?? 'alertas';
        $year = $alerts->currentYear();

        $schools = School::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'latitude', 'longitude']);
        $rows = $schools->map(function (School $school) use ($year, $alerts, $pendencies, $calendar): array {
            $activeStudents = StudentEnrollment::query()
                ->where('school_id', $school->id)->where('status', 'active')
                ->whereHas('student', fn (Builder $query) => $query->whereNull('deleted_at'))
                ->distinct()->count('student_id');

            $classes = SchoolClass::query()->where('school_id', $school->id)
                ->where('status', '!=', 'completed')
                ->when($year !== null, fn (Builder $query) => $query->where('school_year_id', $year->id))
                ->count();

            $transport = StudentEnrollment::query()
                ->where('school_id', $school->id)->where('status', 'active')
                ->where('transport_status', 'active')
                ->whereHas('student', fn (Builder $query) => $query->whereNull('deleted_at'))
                ->selectRaw("count(distinct student_id) as usuarios, count(distinct case when transport_route is null or btrim(transport_route) = '' then student_id end) as sem_rota")
                ->first();

            $pending = $pendencies->summary($pendencies->forSchool($school));
            $plannedDays = $year === null ? null : $calendar->plannedDays($year, $calendar->entries($year->id, $school->id));

            return [
                'school' => $school,
                'students' => $activeStudents,
                'classes' => $classes,
                'alerts' => $alerts->alertCount($school, $year, today()),
                'pendencies' => $pending['withPendencies'],
                'transport' => (int) ($transport?->usuarios ?? 0),
                'without_route' => (int) ($transport?->sem_rota ?? 0),
                'planned_days' => $plannedDays,
                'mapped' => $school->latitude !== null && $school->longitude !== null,
            ];
        });

        $rows = $rows->sort(function (array $a, array $b) use ($order): int {
            $primary = match ($order) {
                'nome' => 0,
                'pendencias' => $b['pendencies'] <=> $a['pendencies'],
                default => $b['alerts'] <=> $a['alerts'],
            };

            return $primary ?: strcmp(
                Str::lower(Str::ascii($a['school']->name)),
                Str::lower(Str::ascii($b['school']->name)),
            );
        })->values();

        $totals = [
            'schools' => $rows->count(),
            'students' => $rows->sum('students'),
            'classes' => $rows->sum('classes'),
            'alerts' => $rows->sum('alerts'),
            'pendencies' => $rows->sum('pendencies'),
            'transport' => $rows->sum('transport'),
            'without_route' => $rows->sum('without_route'),
            'calendar_below_minimum' => $rows->filter(fn (array $row): bool => $row['planned_days'] !== null && $row['planned_days'] < 200)->count(),
            'unmapped' => $rows->filter(fn (array $row): bool => ! $row['mapped'])->count(),
        ];

        // A auditoria guarda apenas que houve consulta ao painel, sem dados pessoais.
        app(AuditTrail::class)->record('tenant', 'network.panel.viewed');

        return view('network.panel', [
            'municipality' => $municipality,
            'rows' => $rows,
            'totals' => $totals,
            'year' => $year,
            'order' => $order,
        ]);
    }
}
