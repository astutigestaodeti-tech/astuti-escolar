<?php

namespace App\Reports;

use App\Academics\Literacy;
use App\Attendance\ActiveSearchAlerts;
use App\Census\CensusReadiness;
use App\Equity\EquityReadiness;
use App\Integral\FullTimeReadiness;
use App\Meals\SchoolMeals;
use App\Models\Tenant\ActiveSearchCase;
use App\Models\Tenant\AttendanceMark;
use App\Models\Tenant\IntegralFundebExpense;
use App\Models\Tenant\IntegralFundebFinance;
use App\Models\Tenant\IntegralPactTarget;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\VaarCondition;
use App\Models\Tenant\WaitlistRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Dados da via institucional: somente agregados e rótulos institucionais.
 * Nenhuma lista nominal dos serviços é devolvida ao controlador.
 */
class MonthlySecretariatReport
{
    public function __construct(
        private readonly CensusReadiness $census,
        private readonly EquityReadiness $equity,
        private readonly FullTimeReadiness $integral,
        private readonly ActiveSearchAlerts $alerts,
        private readonly Literacy $literacy,
        private readonly SchoolMeals $meals,
    ) {}

    public function forMonth(SchoolYear $year, Carbon $month): array
    {
        $from = $month->copy()->startOfMonth();
        $until = $month->copy()->endOfMonth()->min(now());
        $schools = School::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']);
        $classes = SchoolClass::query()->where('school_year_id', $year->id)
            ->whereIn('school_id', $schools->pluck('id'))->get(['id', 'school_id']);
        $census = $this->census->forNetwork(CarbonImmutable::create($year->year, 3, 31), $year->id);
        $censusTotals = $census['totals'];
        $categories = collect($census['categories'])->map(fn (array $row): array => [
            'label' => $row['label'], 'count' => $row['count'],
        ])->all();
        $enrollments = $this->movements($year, $schools, $from, $until);
        $attendance = $this->attendance($year, $schools, $classes, $from, $until);
        $search = $this->search($year, $schools, $from, $until);
        $equity = $this->equity->forYear($year);
        $conditions = VaarCondition::query()->where('reference_year', $year->year)
            ->pluck('status', 'condition_number');
        $vaar = collect(EquityReadiness::CONDITIONS)->map(fn (string $label, int $number): array => [
            'label' => $label,
            'status' => EquityReadiness::STATUSES[$conditions->get($number, 'pendente')],
        ])->all();

        return [
            'matriculas' => [
                'active' => $censusTotals['enrollments'],
                'categories' => $categories,
                'unclassified' => $censusTotals['enrollments'] - array_sum(array_column($categories, 'count')),
                ...$enrollments,
            ],
            'attendance' => $attendance,
            'search' => $search,
            'census' => [
                'warnings' => $censusTotals['with_warnings'],
                'at_risk' => $censusTotals['at_risk_enrollments'],
                'without_inep' => $census['schoolsWithoutInep']->count(),
                'fundeb' => $censusTotals['fundeb'],
                'pnae' => $censusTotals['pnae'],
                'reference_year' => (int) config('censo.year_reference'),
            ],
            'integral' => $this->fullTime($year),
            'equity' => ['summary' => $equity['summary'], 'groups' => $equity['groups'], 'conditions' => $vaar],
            'meals' => $this->mealTotals($year, $month),
            'literacy' => $this->literacyTotals($year),
            'waitlist' => $this->waitlist($year, $from, $until),
        ];
    }

    private function movements(SchoolYear $year, Collection $schools, Carbon $from, Carbon $until): array
    {
        $base = StudentEnrollment::query()->where('school_year_id', $year->id)
            ->whereIn('school_id', $schools->pluck('id'))
            ->whereIn('status', ['active', 'transferred', 'left_network', 'abandoned', 'completed', 'deceased'])
            ->whereHas('student', fn ($query) => $query->whereNull('deleted_at'));

        return [
            'entries' => (clone $base)->whereBetween('started_at', [$from->toDateString(), $until->toDateString()])->count(),
            'exits' => (clone $base)->whereBetween('ended_at', [$from->toDateString(), $until->toDateString()])->count(),
        ];
    }

    private function attendance(SchoolYear $year, Collection $schools, Collection $classes, Carbon $from, Carbon $until): array
    {
        $start = max($from->toDateString(), $year->start_date->toDateString());
        $end = min($until->toDateString(), $year->end_date->toDateString());
        $marks = AttendanceMark::query()
            ->join('attendance_sessions as session', 'session.id', '=', 'attendance_marks.attendance_session_id')
            ->join('school_classes as room', 'room.id', '=', 'session.school_class_id')
            ->whereIn('room.id', $classes->pluck('id'))
            ->whereBetween('session.attendance_date', [$start, $end])
            ->whereHas('student')
            ->whereExists(StudentEnrollment::query()->selectRaw('1')
                ->whereColumn('student_enrollments.student_id', 'attendance_marks.student_id')
                ->whereColumn('student_enrollments.school_class_id', 'room.id')
                ->whereColumn('student_enrollments.school_id', 'room.school_id')
                ->where('student_enrollments.school_year_id', $year->id)
                ->whereIn('student_enrollments.status', ['active', 'transferred', 'left_network', 'abandoned', 'completed', 'deceased'])
                ->where(fn ($query) => $query->whereNull('student_enrollments.started_at')
                    ->orWhereColumn('student_enrollments.started_at', '<=', 'session.attendance_date'))
                ->where(fn ($query) => $query->whereNull('student_enrollments.ended_at')
                    ->orWhereColumn('student_enrollments.ended_at', '>=', 'session.attendance_date')))
            ->select('room.school_id')
            ->selectRaw('count(*) as recorded')
            ->selectRaw("sum(case when attendance_marks.status = 'present' then 1 else 0 end) as present")
            ->groupBy('room.school_id')->get()->keyBy('school_id');
        $rows = $schools->map(function (School $school) use ($marks): array {
            $row = $marks->get($school->id);
            $recorded = (int) ($row?->recorded ?? 0);
            $present = (int) ($row?->present ?? 0);

            return ['school' => $school->name, 'recorded' => $recorded, 'present' => $present, 'percentage' => self::percentage($present, $recorded)];
        })->all();
        $recorded = array_sum(array_column($rows, 'recorded'));
        $present = array_sum(array_column($rows, 'present'));

        return ['schools' => $rows, 'recorded' => $recorded, 'present' => $present, 'percentage' => self::percentage($present, $recorded)];
    }

    private static function percentage(int $part, int $total): ?float
    {
        return $total > 0 ? round(100 * $part / $total, 1) : null;
    }

    private function search(SchoolYear $year, Collection $schools, Carbon $from, Carbon $until): array
    {
        $alerts = 0;
        $settings = $this->alerts->parameters($year);
        // O serviço calcula todas as matrículas da escola em lote.
        if ($from->toDateString() <= $year->end_date->toDateString() && $until->toDateString() >= $year->start_date->toDateString()) {
            foreach ($schools as $school) {
                $entries = $this->alerts->enrollments($school, $year)
                    ->filter(fn (StudentEnrollment $entry): bool => $entry->started_at === null || $entry->started_at->lte($until));
                $summaries = $this->alerts->summaries($school, $year, $entries, $until);
                $alerts += collect($summaries)->where('alert', true)->count();
            }
        }
        $base = ActiveSearchCase::query()->where('school_year_id', $year->id)->whereIn('school_id', $schools->pluck('id'));

        return [
            'alerts' => $alerts,
            'settings' => $settings,
            'open' => (clone $base)->whereIn('status', ['open', 'referred'])->count(),
            'opened' => (clone $base)->whereBetween('opened_at', [$from, $until])->count(),
            'returns' => (clone $base)->whereBetween('returned_at', [$from, $until])->count(),
        ];
    }

    private function fullTime(SchoolYear $year): array
    {
        $data = $this->integral->forYear($year);
        $targets = IntegralPactTarget::query()->where('school_year_id', $year->id)->pluck('target_enrollments', 'stage');
        $revenue = IntegralFundebFinance::query()->where('school_year_id', $year->id)->value('revenue_cents');
        $applied = (int) IntegralFundebExpense::query()->where('school_year_id', $year->id)->sum('amount_cents');
        $minimum = $revenue === null ? null : intdiv((int) $revenue * 4 + 99, 100);

        return [
            'stages' => collect($data['byStage'])->map(fn (array $row, string $key): array => [
                ...$row, 'target' => $targets->has($key) ? (int) $targets->get($key) : null,
            ])->all(),
            'total' => $data['totals']['current'],
            'inferred' => $data['totals']['inferred'],
            'unclassified' => $data['totals']['without_stage'],
            'revenue' => $revenue === null ? null : (int) $revenue,
            'minimum' => $minimum,
            'applied' => $applied,
            'remaining' => $minimum === null ? null : max(0, $minimum - $applied),
        ];
    }

    private function mealTotals(SchoolYear $year, Carbon $month): array
    {
        $data = $this->meals->monthly($year, $month->format('Y-m'));

        return [
            'total' => $data['totals']['total'],
            'days' => $data['totals']['days'],
            'stages' => $data['totals']['byStage'],
        ];
    }

    private function literacyTotals(SchoolYear $year): array
    {
        $classes = $this->literacy->classes($year);
        $entries = $this->literacy->enrollments($classes);
        $assessments = $this->literacy->assessments($entries);
        $second = $classes->filter(fn (SchoolClass $class): bool => Literacy::grade($class) === 2)->pluck('id');

        return [
            'terms' => $this->literacy->distribution($entries, $assessments),
            'second' => $this->literacy->distribution($entries->whereIn('school_class_id', $second), $assessments)[4],
        ];
    }

    private function waitlist(SchoolYear $year, Carbon $from, Carbon $until): array
    {
        $requests = WaitlistRequest::query()->where('intake_year', $year->year)
            ->where('requested_at', '<=', $until)->get(['id', 'status']);
        $changes = DB::connection('tenant')->table('waitlist_status_changes')
            ->whereIn('waitlist_request_id', $requests->pluck('id'))
            ->orderBy('changed_at')->orderBy('id')
            ->get(['waitlist_request_id', 'previous_status', 'new_status', 'changed_at'])
            ->groupBy('waitlist_request_id');
        $waiting = 0;
        $served = 0;
        foreach ($requests as $request) {
            $history = $changes->get($request->id, collect());
            $atEnd = $history->filter(fn ($row): bool => Carbon::parse($row->changed_at)->lte($until))->last();
            $status = $atEnd?->new_status ?? $history->first()?->previous_status ?? $request->status;
            $waiting += in_array($status, ['aguardando', 'convocado'], true) ? 1 : 0;
            $served += $history->contains(fn ($row): bool => $row->new_status === 'atendido'
                && Carbon::parse($row->changed_at)->betweenIncluded($from, $until)) ? 1 : 0;
        }

        return ['waiting' => $waiting, 'served' => $served];
    }
}
