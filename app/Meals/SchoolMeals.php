<?php

namespace App\Meals;

use App\Census\CensusReadiness;
use App\Models\Tenant\AttendanceSession;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolMealServing;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\StudentHealthAlert;
use App\Models\Tenant\StudentSpecialEducation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Números gerenciais. A quantidade servida nunca é gravada automaticamente
 * a partir da chamada; precisa ser confirmada por uma pessoa autorizada.
 */
class SchoolMeals
{
    public const STAGES = [
        'creche' => 'Creche',
        'pre_escola' => 'Pré-escola',
        'fundamental' => 'Ensino Fundamental',
    ];

    public const MEALS = [
        'cafe' => 'Café da manhã',
        'almoco' => 'Almoço',
        'lanche' => 'Lanche',
        'jantar' => 'Jantar',
    ];

    public const DAYS = [
        1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira',
        4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado', 7 => 'Domingo',
    ];

    public static function stage(SchoolClass $class, ?Student $student = null, ?SchoolYear $year = null): ?string
    {
        $age = $student !== null && $year !== null
            ? $student->ageOn(CarbonImmutable::create((int) $year->year, 3, 31))
            : null;
        $census = CensusReadiness::stage($class->stage.' '.$class->name, $age);

        return match ($census) {
            'creche', 'pre_escola' => $census,
            'fundamental_iniciais', 'fundamental_finais' => 'fundamental',
            default => null,
        };
    }

    /** @return array<string, int>|null Null quando nenhuma turma registrou chamada. */
    public function suggested(School $school, SchoolYear $year, string $date): ?array
    {
        $classes = SchoolClass::query()->where('school_id', $school->id)
            ->where('school_year_id', $year->id)->get()->keyBy('id');
        if ($classes->isEmpty()) {
            return null;
        }
        $sessions = AttendanceSession::query()->whereIn('school_class_id', $classes->keys())
            ->whereDate('attendance_date', $date)->with('marks')->get();
        if ($sessions->isEmpty()) {
            return null;
        }
        $enrollments = StudentEnrollment::query()->where('school_id', $school->id)
            ->where('school_year_id', $year->id)
            ->whereIn('status', ['active', 'completed'])
            ->with('student')->get()->filter(function (StudentEnrollment $item) use ($date): bool {
                return $item->student !== null && $item->student->deleted_at === null
                    && ($item->started_at === null || $item->started_at->toDateString() <= $date)
                    && ($item->ended_at === null || $item->ended_at->toDateString() >= $date);
            })->groupBy(fn (StudentEnrollment $item): string => $item->school_class_id.'|'.$item->student_id);

        $result = array_fill_keys(array_keys(self::STAGES), 0);
        $seen = [];
        foreach ($sessions as $session) {
            $class = $classes->get($session->school_class_id);
            if ($class === null) {
                continue;
            }
            foreach ($session->marks as $mark) {
                if ($mark->status !== 'present' || isset($seen[$mark->student_id])) {
                    continue;
                }
                $enrollment = $enrollments->get($class->id.'|'.$mark->student_id)?->first();
                if ($enrollment === null) {
                    continue;
                }
                $stage = self::stage($class, $enrollment->student, $year);
                if ($stage !== null) {
                    $result[$stage]++;
                    $seen[$mark->student_id] = true;
                }
            }
        }

        return $result;
    }

    /**
     * Alertas são texto livre na ficha: jamais os mostramos à cozinha.
     * Classificação fechada impede que nomes ou diagnósticos digitados no
     * campo livre escapem pela coluna "tipo".
     *
     * @return array{counts: array<string, int>, names: Collection}
     */
    public function allergies(School $school, SchoolYear $year): array
    {
        $students = StudentEnrollment::query()->where('school_id', $school->id)
            ->where('school_year_id', $year->id)->where('status', 'active')
            ->with('student')->get()->pluck('student')->filter(fn ($student) => $student !== null && $student->deleted_at === null)
            ->unique('id')->keyBy('id');
        $alerts = $students->isEmpty() ? collect() : StudentHealthAlert::query()
            ->whereIn('student_id', $students->keys())
            ->where('kind', 'allergy')->get(['student_id', 'alert_text']);
        $rows = collect();
        foreach ($alerts as $alert) {
            $type = self::allergen((string) $alert->alert_text);
            $rows->push(['student_id' => $alert->student_id, 'student' => $students->get($alert->student_id)?->full_name, 'type' => $type]);
        }
        $counts = $rows->unique(fn (array $row): string => $row['student_id'].'|'.$row['type'])
            ->countBy('type')->sortKeys()->all();

        return ['counts' => $counts, 'names' => $rows->unique(fn (array $row): string => $row['student_id'].'|'.$row['type'])->sortBy('student')->values()];
    }

    private static function allergen(string $text): string
    {
        $text = Str::lower(Str::ascii($text));
        foreach ([
            'Amendoim' => '/amendoim/',
            'Leite e derivados' => '/leite|lactose|lacticinios/',
            'Ovo' => '/\bovo(s)?\b/',
            'Glúten ou trigo' => '/gluten|trigo/',
            'Soja' => '/soja/',
            'Peixe' => '/\bpeixe(s)?\b/',
            'Frutos do mar' => '/camarao|crustaceo|marisco|frutos do mar/',
            'Castanhas e nozes' => '/castanha|nozes|noz\b/',
        ] as $label => $pattern) {
            if (preg_match($pattern, $text)) {
                return $label;
            }
        }

        return 'Outras alergias ou intolerâncias';
    }

    /**
     * Estimativa de repasse proporcional a dias com refeição. Não é custo
     * de preparo, nem valor oficial do FNDE, nem quantidade de refeições.
     *
     * @return array{daily: float, annual: float, unclassified: int}
     */
    public function pnaeEstimate(SchoolYear $year, ?string $schoolId = null): array
    {
        $classes = SchoolClass::query()->where('school_year_id', $year->id)
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->get()->keyBy('id');
        $enrollments = StudentEnrollment::query()->where('school_year_id', $year->id)
            ->where('status', 'active')
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->with('student')->get();
        $schools = School::query()->where('status', 'active')->get(['id'])->pluck('id')->flip();
        $aee = StudentSpecialEducation::query()
            ->whereIn('student_id', $enrollments->pluck('student_id')->unique())
            ->where('aee_status', 'participa')->pluck('student_id')->flip();
        $daily = 0.0;
        $unknown = 0;
        $seen = [];
        foreach ($enrollments as $entry) {
            $class = $classes->get($entry->school_class_id);
            if ($class === null || $class->school_id !== $entry->school_id
                || ! $schools->has($entry->school_id) || $entry->student === null
                || $entry->student->deleted_at !== null || isset($seen[$entry->student_id])) {
                continue;
            }
            $seen[$entry->student_id] = true;
            $stage = CensusReadiness::stage($class->stage.' '.$class->name,
                $entry->student->ageOn(CarbonImmutable::create((int) $year->year, 3, 31)));
            $integral = $class->daily_hours === null
                ? CensusReadiness::shift((string) $class->shift) === 'integral'
                : (float) $class->daily_hours >= 7;
            $category = match ($stage) {
                'creche' => $integral ? 'creche_integral' : 'creche_parcial',
                'pre_escola' => $integral ? 'pre_integral' : 'pre_parcial',
                'fundamental_iniciais', 'fundamental_finais' => $integral ? 'fundamental_integral' : $stage,
                'eja' => 'eja',
                default => null,
            };
            if ($category === null) {
                $unknown++;
                continue;
            }
            $daily += (float) config('censo.categories.'.$category.'.pnae', 0);
            if ($aee->has($entry->student_id)) {
                $daily += (float) config('censo.pnae_aee', 0.78);
            }
        }

        return ['daily' => round($daily, 2),
            'annual' => round($daily * (int) config('censo.pnae_days', 200), 2),
            'unclassified' => $unknown];
    }

    /**
     * @return array{rows: Collection, totals: array, schoolDays: int, dates: int}
     */
    public function monthly(SchoolYear $year, string $month, ?string $schoolId = null): array
    {
        $schools = School::query()->where('status', 'active')
            ->when($schoolId, fn ($q) => $q->where('id', $schoolId))
            ->orderBy('name')->get(['id', 'name']);
        $servings = SchoolMealServing::query()->where('school_year_id', $year->id)
            ->where('meal_date', '>=', $month.'-01')
            ->where('meal_date', '<', CarbonImmutable::parse($month.'-01')->addMonth()->toDateString())
            ->whereIn('school_id', $schools->pluck('id'))->get();
        $allDates = [];
        $schoolDays = 0;
        $rows = $schools->map(function (School $school) use ($servings, &$allDates, &$schoolDays, $year): array {
            $items = $servings->where('school_id', $school->id);
            $positive = $items->filter(fn (SchoolMealServing $item): bool => $item->quantity > 0);
            $dates = $positive->pluck('meal_date')->map(fn ($date): string => $date->toDateString())->unique();
            foreach ($dates as $date) {
                $allDates[$date] = true;
            }
            $days = $dates->count();
            $schoolDays += $days;
            $total = (int) $positive->sum('quantity');
            $pnae = $this->pnaeEstimate($year, $school->id);

            return [
                'school' => $school, 'days' => $days, 'total' => $total,
                'byStage' => collect(self::STAGES)->keys()->mapWithKeys(fn (string $stage): array => [$stage => (int) $positive->where('stage', $stage)->sum('quantity')])->all(),
                'average' => $days > 0 ? round($total / $days, 1) : 0,
                'reference' => round($pnae['daily'] * $days, 2),
                'pnae_annual' => $pnae['annual'],
                'unclassified' => $pnae['unclassified'],
            ];
        });
        $totals = [
            'days' => count($allDates), 'total' => $rows->sum('total'),
            'byStage' => collect(self::STAGES)->keys()->mapWithKeys(fn (string $stage): array => [$stage => $rows->sum(fn (array $row): int => $row['byStage'][$stage])])->all(),
            'average' => $schoolDays > 0 ? round($rows->sum('total') / $schoolDays, 1) : 0,
            'reference' => round($rows->sum('reference'), 2),
            'pnae_annual' => round($rows->sum('pnae_annual'), 2),
            'unclassified' => $rows->sum('unclassified'),
        ];

        return compact('rows', 'totals', 'schoolDays') + ['dates' => count($allDates)];
    }
}
