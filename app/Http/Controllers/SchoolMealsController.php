<?php

namespace App\Http\Controllers;

use App\Audit\AuditTrail;
use App\Meals\SchoolMeals;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolMealMenu;
use App\Models\Tenant\SchoolMealServing;
use App\Models\Tenant\SchoolYear;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SchoolMealsController extends Controller
{
    public function menu(Request $request, string $municipio): Response
    {
        $municipality = $request->attributes->get('astuti.municipality');
        Gate::authorize('viewNetworkPanel', [School::class, $municipality]);

        return $this->menuPage($request);
    }

    public function schoolMenu(Request $request, string $municipio, string $escola): Response
    {
        $this->authorizedSchool($request, $escola);

        return $this->menuPage($request, $escola);
    }

    private function menuPage(Request $request, ?string $schoolId = null): Response
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $filter = $request->validate([
            'ano' => ['nullable', 'integer', 'between:2024,2100'],
            'semana' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $years = SchoolYear::query()->orderByDesc('year')->get(['id', 'year', 'start_date', 'end_date']);
        $year = $years->firstWhere('year', (int) ($filter['ano'] ?? today()->year)) ?? $years->first();
        if (isset($filter['ano']) && ($year === null || $year->year !== (int) $filter['ano'])) {
            throw ValidationException::withMessages(['ano' => 'Ano letivo não encontrado.']);
        }
        $week = null;
        $entries = collect();
        if ($year !== null) {
            $reference = isset($filter['semana'])
                ? CarbonImmutable::parse($filter['semana'])
                : (today()->year === $year->year && CarbonImmutable::today()->betweenIncluded(CarbonImmutable::parse($year->start_date), CarbonImmutable::parse($year->end_date))
                    ? CarbonImmutable::today() : CarbonImmutable::parse($year->start_date));
            $week = $reference->startOfWeek(CarbonInterface::MONDAY);
            $this->assertWeek($year, $week);
            $entries = SchoolMealMenu::query()->where('school_year_id', $year->id)
                ->whereDate('week_start', $week->toDateString())->get()
                ->keyBy(fn (SchoolMealMenu $item): string => $item->stage.'|'.$item->weekday.'|'.$item->meal_type);
        }

        return response()->view('meals.menu', [
            'municipality' => $municipality,
            'school' => $schoolId === null ? null : School::query()->findOrFail($schoolId),
            'years' => $years,
            'year' => $year,
            'week' => $week,
            'entries' => $entries,
            'canEdit' => $schoolId === null,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function saveMenu(Request $request, string $municipio): RedirectResponse
    {
        $municipality = $request->attributes->get('astuti.municipality');
        Gate::authorize('viewNetworkPanel', [School::class, $municipality]);
        $data = $request->validate([
            'ano_id' => ['required', 'uuid'],
            'semana' => ['required', 'date_format:Y-m-d'],
            'dia' => ['required', 'integer', 'between:1,7'],
            'etapa' => ['required', Rule::in(array_keys(SchoolMeals::STAGES))],
            'refeicao' => ['required', Rule::in(array_keys(SchoolMeals::MEALS))],
            'cardapio' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        $year = SchoolYear::query()->findOrFail($data['ano_id']);
        $week = CarbonImmutable::parse($data['semana']);
        $this->assertWeek($year, $week);
        $this->assertWithinYear($year, $week->addDays((int) $data['dia'] - 1));
        DB::connection('tenant')->transaction(function () use ($year, $week, $data, $request): void {
            SchoolYear::query()->whereKey($year->id)->lockForUpdate()->firstOrFail();
            $item = SchoolMealMenu::query()->where('school_year_id', $year->id)
                ->whereDate('week_start', $week->toDateString())
                ->where('weekday', $data['dia'])->where('stage', $data['etapa'])
                ->where('meal_type', $data['refeicao'])->lockForUpdate()->first();
            $before = $item?->only(['id', 'school_year_id', 'week_start', 'weekday', 'stage', 'meal_type', 'description', 'updated_by']);
            if ($item === null) {
                $item = new SchoolMealMenu(['id' => (string) Str::uuid(), 'school_year_id' => $year->id,
                    'week_start' => $week->toDateString(), 'weekday' => $data['dia'],
                    'stage' => $data['etapa'], 'meal_type' => $data['refeicao']]);
            }
            $item->fill(['description' => trim($data['cardapio']), 'updated_by' => $request->user()->id])->save();
            app(AuditTrail::class)->record('tenant', 'meals.menu.saved', SchoolMealMenu::class, $item->id,
                $before, $item->only(['id', 'school_year_id', 'week_start', 'weekday', 'stage', 'meal_type', 'description', 'updated_by']));
        });

        return redirect()->route('meals.menu', ['municipio' => $municipality->slug, 'ano' => $year->year,
            'semana' => $week->toDateString()])->with('status', 'Cardápio atualizado e auditado.');
    }

    public function copyMenu(Request $request, string $municipio): RedirectResponse
    {
        $municipality = $request->attributes->get('astuti.municipality');
        Gate::authorize('viewNetworkPanel', [School::class, $municipality]);
        $data = $request->validate([
            'ano_id' => ['required', 'uuid'],
            'origem' => ['required', 'date_format:Y-m-d'],
            'destino' => ['required', 'date_format:Y-m-d', 'different:origem'],
        ]);
        $year = SchoolYear::query()->findOrFail($data['ano_id']);
        $from = CarbonImmutable::parse($data['origem']);
        $to = CarbonImmutable::parse($data['destino']);
        $this->assertWeek($year, $from);
        $this->assertWeek($year, $to);

        $copied = DB::connection('tenant')->transaction(function () use ($year, $from, $to, $request): int {
            SchoolYear::query()->whereKey($year->id)->lockForUpdate()->firstOrFail();
            $source = SchoolMealMenu::query()->where('school_year_id', $year->id)
                ->whereDate('week_start', $from->toDateString())->get();
            $count = 0;
            foreach ($source as $row) {
                if (! $this->withinYear($year, $to->addDays($row->weekday - 1))) {
                    continue;
                }
                $target = SchoolMealMenu::query()->where('school_year_id', $year->id)
                    ->whereDate('week_start', $to->toDateString())
                    ->where('weekday', $row->weekday)->where('stage', $row->stage)
                    ->where('meal_type', $row->meal_type)->lockForUpdate()->first();
                $before = $target?->only(['id', 'school_year_id', 'week_start', 'weekday', 'stage', 'meal_type', 'description', 'updated_by']);
                if ($target === null) {
                    $target = new SchoolMealMenu(['id' => (string) Str::uuid(),
                        'school_year_id' => $year->id, 'week_start' => $to->toDateString(),
                        'weekday' => $row->weekday, 'stage' => $row->stage, 'meal_type' => $row->meal_type]);
                }
                $target->fill(['description' => $row->description, 'updated_by' => $request->user()->id])->save();
                app(AuditTrail::class)->record('tenant', 'meals.menu.copied', SchoolMealMenu::class, $target->id,
                    $before, $target->only(['id', 'school_year_id', 'week_start', 'weekday', 'stage', 'meal_type', 'description', 'updated_by']));
                $count++;
            }

            return $count;
        });

        return redirect()->route('meals.menu', ['municipio' => $municipality->slug, 'ano' => $year->year,
            'semana' => $to->toDateString()])->with('status', $copied.' item(ns) copiado(s) e auditado(s).');
    }

    public function school(Request $request, string $municipio, string $escola, SchoolMeals $meals): Response
    {
        [$municipality, $school] = $this->authorizedSchool($request, $escola);
        $filter = $request->validate(['data' => ['nullable', 'date_format:Y-m-d']]);
        $date = CarbonImmutable::parse($filter['data'] ?? today()->toDateString());
        abort_if($date->isAfter(CarbonImmutable::today()), 422, 'A data não pode estar no futuro.');
        $year = SchoolYear::query()->where('year', $date->year)
            ->first(['id', 'year', 'start_date', 'end_date']);
        $validDate = $year !== null && $this->withinYear($year, $date);
        $suggested = $validDate ? $meals->suggested($school, $year, $date->toDateString()) : null;
        $entries = $validDate ? SchoolMealServing::query()->where('school_id', $school->id)
            ->where('school_year_id', $year->id)->whereDate('meal_date', $date->toDateString())
            ->orderBy('stage')->orderBy('meal_type')->get() : collect();
        $allergies = $validDate ? $meals->allergies($school, $year)['counts'] : [];
        $registrants = User::query()->whereIn('id', $entries->pluck('confirmed_by')->unique())->pluck('name', 'id');

        return response()->view('meals.school', compact(
            'municipality', 'school', 'date', 'year', 'validDate', 'suggested', 'entries', 'allergies', 'registrants',
        ))->header('Cache-Control', 'no-store, private');
    }

    public function saveServing(Request $request, string $municipio, string $escola, SchoolMeals $meals): RedirectResponse
    {
        [$municipality, $school] = $this->authorizedSchool($request, $escola);
        $data = $request->validate([
            'data' => ['required', 'date_format:Y-m-d'],
            'etapa' => ['required', Rule::in(array_keys(SchoolMeals::STAGES))],
            'refeicao' => ['required', Rule::in(array_keys(SchoolMeals::MEALS))],
            'quantidade' => ['required', 'integer', 'between:0,1000000'],
        ]);
        $date = CarbonImmutable::parse($data['data']);
        if ($date->isAfter(CarbonImmutable::today())) {
            throw ValidationException::withMessages(['data' => 'Não é permitido registrar refeições futuras.']);
        }
        $year = SchoolYear::query()->where('year', $date->year)
            ->first(['id', 'year', 'start_date', 'end_date']);
        if ($year === null) {
            throw ValidationException::withMessages(['data' => 'Não existe ano letivo cadastrado para a data.']);
        }
        $this->assertWithinYear($year, $date);
        // Fotografia da sugestão naquele momento, não um número presumido.
        $suggestion = $meals->suggested($school, $year, $date->toDateString());
        DB::connection('tenant')->transaction(function () use ($school, $year, $date, $data, $suggestion, $request): void {
            School::query()->whereKey($school->id)->where('status', 'active')->lockForUpdate()->firstOrFail();
            $item = SchoolMealServing::query()->where('school_id', $school->id)
                ->where('school_year_id', $year->id)->whereDate('meal_date', $date->toDateString())
                ->where('stage', $data['etapa'])->where('meal_type', $data['refeicao'])
                ->lockForUpdate()->first();
            $before = $item?->only(['id', 'school_id', 'school_year_id', 'meal_date', 'stage', 'meal_type',
                'quantity', 'suggested_present', 'confirmed_by', 'confirmed_at']);
            if ($item === null) {
                $item = new SchoolMealServing(['id' => (string) Str::uuid(),
                    'school_id' => $school->id, 'school_year_id' => $year->id,
                    'meal_date' => $date->toDateString(), 'stage' => $data['etapa'], 'meal_type' => $data['refeicao']]);
            }
            $item->fill([
                'quantity' => (int) $data['quantidade'],
                'suggested_present' => $suggestion[$data['etapa']] ?? null,
                'confirmed_by' => $request->user()->id, 'confirmed_at' => now(),
            ])->save();
            app(AuditTrail::class)->record('tenant', 'meals.serving.confirmed', SchoolMealServing::class,
                $item->id, $before,
                $item->only(['id', 'school_id', 'school_year_id', 'meal_date', 'stage', 'meal_type',
                    'quantity', 'suggested_present', 'confirmed_by', 'confirmed_at']));
        });

        return redirect()->route('school.meals.index', ['municipio' => $municipality->slug,
            'escola' => $school->id, 'data' => $date->toDateString()])
            ->with('status', 'Quantidade confirmada e auditada. A sugestão de presença não substitui a contagem real.');
    }

    public function kitchen(Request $request, string $municipio, string $escola, SchoolMeals $meals): Response
    {
        [$municipality, $school] = $this->authorizedSchool($request, $escola);
        $year = SchoolYear::query()->where('year', today()->year)
            ->first(['id', 'year', 'start_date', 'end_date']);
        $counts = $year === null ? [] : $meals->allergies($school, $year)['counts'];
        // Nenhum nome, UUID de aluno ou campo livre de saúde chega à view.
        app(AuditTrail::class)->record('tenant', 'meals.kitchen.counts.viewed');

        return response()->view('meals.kitchen', compact('municipality', 'school', 'year', 'counts'))
            ->header('Cache-Control', 'no-store, private');
    }

    public function allergies(Request $request, string $municipio, string $escola, SchoolMeals $meals): Response
    {
        [$municipality, $school] = $this->authorizedSchool($request, $escola);
        Gate::authorize('viewMealAllergyNames', [$school, $municipality]);
        $year = SchoolYear::query()->where('year', today()->year)
            ->first(['id', 'year', 'start_date', 'end_date']);
        $names = $year === null ? collect() : $meals->allergies($school, $year)['names'];
        app(AuditTrail::class)->record('tenant', 'meals.allergies.names.viewed');

        return response()->view('meals.allergies', compact('municipality', 'school', 'year', 'names'))
            ->header('Cache-Control', 'no-store, private');
    }

    public function report(Request $request, string $municipio, SchoolMeals $meals): Response
    {
        $municipality = $request->attributes->get('astuti.municipality');
        Gate::authorize('viewNetworkPanel', [School::class, $municipality]);

        return $this->reportPage($request, $meals);
    }

    public function schoolReport(Request $request, string $municipio, string $escola, SchoolMeals $meals): Response
    {
        $this->authorizedSchool($request, $escola);

        return $this->reportPage($request, $meals, $escola);
    }

    private function reportPage(Request $request, SchoolMeals $meals, ?string $schoolId = null): Response
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $filter = $request->validate(['mes' => ['nullable', 'date_format:Y-m']]);
        $month = $filter['mes'] ?? today()->format('Y-m');
        $year = SchoolYear::query()->where('year', (int) substr($month, 0, 4))
            ->first(['id', 'year', 'start_date', 'end_date']);
        $data = $year === null ? null : $meals->monthly($year, $month, $schoolId);
        $school = $schoolId === null ? null : School::query()->findOrFail($schoolId);

        return response()->view('meals.report', compact('municipality', 'month', 'year', 'data', 'school'))
            ->header('Cache-Control', 'no-store, private');
    }

    /** @return array{0: mixed, 1: School} */
    private function authorizedSchool(Request $request, string $schoolId): array
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($schoolId);
        Gate::authorize('manageStudents', [$school, $municipality]);

        return [$municipality, $school];
    }

    private function assertWeek(SchoolYear $year, CarbonImmutable $week): void
    {
        $first = CarbonImmutable::parse($year->start_date)->startOfWeek(CarbonInterface::MONDAY);
        $last = CarbonImmutable::parse($year->end_date)->startOfWeek(CarbonInterface::MONDAY);
        if ($week->dayOfWeekIso !== 1 || $week->lt($first) || $week->gt($last)) {
            throw ValidationException::withMessages(['semana' => 'Escolha uma segunda-feira do período letivo.']);
        }
    }

    private function withinYear(SchoolYear $year, CarbonImmutable $date): bool
    {
        return $date->toDateString() >= $year->start_date->toDateString()
            && $date->toDateString() <= $year->end_date->toDateString();
    }

    private function assertWithinYear(SchoolYear $year, CarbonImmutable $date): void
    {
        if (! $this->withinYear($year, $date)) {
            throw ValidationException::withMessages(['data' => 'A data está fora do período do ano letivo.']);
        }
    }
}
