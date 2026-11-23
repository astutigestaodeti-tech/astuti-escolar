<?php

namespace App\Http\Controllers;

use App\Audit\AuditTrail;
use App\Calendar\BrazilianHolidays;
use App\Calendar\SchoolCalendar;
use App\Models\Platform\Membership;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolCalendarDay;
use App\Models\Tenant\SchoolYear;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SchoolCalendarController extends Controller
{
    public function index(Request $request, string $municipio, string $escola, SchoolCalendar $calendar): View
    {
        [$municipality, $school] = $this->authorizedSchool($request, $escola);
        $years = SchoolYear::query()->orderByDesc('year')->get();
        $validated = $request->validate([
            'ano' => ['nullable', 'uuid'],
            'mes' => ['nullable', 'date_format:Y-m'],
        ], [
            'ano.uuid' => 'Escolha um ano letivo válido.',
            'mes.date_format' => 'Escolha um mês válido.',
        ]);
        $year = $years->firstWhere('id', $validated['ano'] ?? null) ?? $years->firstWhere('status', 'active') ?? $years->first();

        $entries = $year === null ? collect() : $calendar->entries($year->id, $school->id);
        $plannedDays = $year === null ? 0 : $calendar->plannedDays($year, $entries);
        $month = Carbon::parse(($validated['mes'] ?? ($year?->year ?? today()->year).'-02').'-01');
        $cursor = $month->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $end = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $days = [];
        while ($cursor->lte($end)) {
            $days[] = $cursor->copy();
            $cursor->addDay();
        }
        $byDate = $entries->groupBy(fn (SchoolCalendarDay $day): string => $day->date->toDateString());
        $canManage = Gate::allows('manageStudents', [$school, $municipality]);
        $isMunicipalManager = $this->municipalManager($request);

        return view('calendar.index', compact(
            'municipality', 'school', 'years', 'year', 'entries', 'plannedDays',
            'month', 'days', 'byDate', 'canManage', 'isMunicipalManager',
        ));
    }

    public function store(Request $request, string $municipio, string $escola): RedirectResponse
    {
        [$municipality, $school] = $this->managedSchool($request, $escola);
        $validated = $request->validate([
            'school_year_id' => ['required', 'uuid'],
            'date' => ['required', 'date_format:Y-m-d'],
            'kind' => ['required', Rule::in(array_keys(SchoolCalendarDay::KINDS))],
            'description' => ['required', 'string', 'max:120'],
            'scope' => ['required', Rule::in(['school', 'municipality'])],
        ], [
            'school_year_id.required' => 'Escolha o ano letivo.',
            'school_year_id.uuid' => 'Escolha um ano letivo válido.',
            'date.required' => 'Informe a data do calendário.',
            'date.date_format' => 'Informe uma data válida.',
            'kind.required' => 'Escolha o tipo do dia.',
            'kind.in' => 'Escolha um tipo válido.',
            'description.required' => 'Informe a descrição do dia.',
            'description.max' => 'A descrição pode ter até 120 caracteres.',
            'scope.required' => 'Escolha a abrangência.',
            'scope.in' => 'Escolha uma abrangência válida.',
        ], [
            'date' => 'Data', 'kind' => 'Tipo', 'description' => 'Descrição',
            'scope' => 'Abrangência', 'school_year_id' => 'Ano letivo',
        ]);

        if ($validated['scope'] === 'municipality' && $this->municipalManager($request) === false) {
            abort(403);
        }

        $year = SchoolYear::query()->findOrFail($validated['school_year_id']);
        $date = Carbon::parse($validated['date']);
        if ($year->start_date === null || $year->end_date === null
            || $date->lt($year->start_date) || $date->gt($year->end_date)) {
            throw ValidationException::withMessages([
                'date' => 'A data deve estar entre o início e o fim do ano letivo escolhido.',
            ]);
        }

        $description = preg_replace('/\s+/u', ' ', trim($validated['description']));
        $scopeId = $validated['scope'] === 'municipality' ? null : $school->id;
        try {
            DB::connection('tenant')->transaction(function () use ($year, $scopeId, $validated, $description): void {
                $entry = SchoolCalendarDay::query()->create([
                    'id' => (string) Str::uuid(),
                    'school_year_id' => $year->id,
                    'school_id' => $scopeId,
                    'date' => $validated['date'],
                    'kind' => $validated['kind'],
                    'description' => $description,
                ]);
                app(AuditTrail::class)->record('tenant', 'created', SchoolCalendarDay::class, $entry->id, null, $entry->getAttributes());
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23505') {
                throw $exception;
            }
            throw ValidationException::withMessages([
                'date' => 'Já existe um lançamento nesta data para a abrangência escolhida. Remova-o antes de substituir.',
            ]);
        }

        return redirect()->route('school.calendar.index', [
            'municipio' => $municipality->slug, 'escola' => $school->id,
            'ano' => $year->id, 'mes' => $date->format('Y-m'),
        ])->with('status', 'Dia incluído no calendário escolar.');
    }

    /**
     * Inclui de uma vez os feriados nacionais do ano letivo. Pula as datas que
     * já têm lançamento visível para a escola. Gestão municipal grava para a
     * rede; a gestão da escola, só para a própria escola.
     */
    public function addNationalHolidays(Request $request, string $municipio, string $escola, SchoolCalendar $calendar): RedirectResponse
    {
        [$municipality, $school] = $this->managedSchool($request, $escola);
        $validated = $request->validate(['school_year_id' => ['required', 'uuid']], [
            'school_year_id.required' => 'Escolha o ano letivo.',
            'school_year_id.uuid' => 'Escolha um ano letivo válido.',
        ]);
        $year = SchoolYear::query()->findOrFail($validated['school_year_id']);
        if ($year->start_date === null || $year->end_date === null) {
            throw ValidationException::withMessages(['school_year_id' => 'Informe o início e o fim do ano letivo antes de incluir feriados.']);
        }
        $scopeId = $this->municipalManager($request) ? null : $school->id;

        [$added, $skipped] = DB::connection('tenant')->transaction(function () use ($year, $school, $scopeId, $calendar): array {
            $taken = $calendar->entries($year->id, $school->id)->map(fn (SchoolCalendarDay $day): string => $day->date->toDateString())->flip();
            $added = 0;
            $skipped = 0;
            foreach (range($year->start_date->year, $year->end_date->year) as $calendarYear) {
                foreach (BrazilianHolidays::forYear($calendarYear) as $date => $holiday) {
                    if ($date < $year->start_date->toDateString() || $date > $year->end_date->toDateString()) {
                        continue;
                    }
                    if ($taken->has($date)) {
                        $skipped++;

                        continue;
                    }
                    $entry = SchoolCalendarDay::query()->create([
                        'id' => (string) Str::uuid(), 'school_year_id' => $year->id, 'school_id' => $scopeId,
                        'date' => $date, 'kind' => $holiday['kind'], 'description' => $holiday['description'],
                    ]);
                    app(AuditTrail::class)->record('tenant', 'created', SchoolCalendarDay::class, $entry->id, null, $entry->getAttributes());
                    $added++;
                }
            }

            return [$added, $skipped];
        });

        $message = $added === 0
            ? 'Os feriados nacionais deste ano letivo já estavam no calendário.'
            : $added.' '.($added === 1 ? 'feriado nacional incluído' : 'feriados nacionais incluídos').($scopeId === null ? ' para toda a rede' : ' para esta escola')
                .($skipped > 0 ? ' ('.$skipped.' já '.($skipped === 1 ? 'estava' : 'estavam').' no calendário)' : '').'. Confira os feriados estaduais e municipais.';

        return redirect()->route('school.calendar.index', ['municipio' => $municipality->slug, 'escola' => $school->id, 'ano' => $year->id])
            ->with('status', $message);
    }

    public function destroy(Request $request, string $municipio, string $escola, string $dia): RedirectResponse
    {
        [$municipality, $school] = $this->managedSchool($request, $escola);
        DB::connection('tenant')->transaction(function () use ($request, $school, $dia): void {
            $entry = SchoolCalendarDay::query()
                ->where(function ($query) use ($school): void {
                    $query->whereNull('school_id')->orWhere('school_id', $school->id);
                })->lockForUpdate()->findOrFail($dia);
            if ($entry->school_id === null && $this->municipalManager($request) === false) {
                abort(403);
            }
            app(AuditTrail::class)->record('tenant', 'deleted', SchoolCalendarDay::class, $entry->id, $entry->getAttributes(), null);
            $entry->delete();
        });

        return back()->with('status', 'Lançamento removido do calendário escolar.');
    }

    private function authorizedSchool(Request $request, string $id): array
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($id);
        Gate::authorize('view', [$school, $municipality]);

        return [$municipality, $school];
    }

    private function managedSchool(Request $request, string $id): array
    {
        [$municipality, $school] = $this->authorizedSchool($request, $id);
        Gate::authorize('manageStudents', [$school, $municipality]);

        return [$municipality, $school];
    }

    private function municipalManager(Request $request): bool
    {
        $municipality = $request->attributes->get('astuti.municipality');

        return Membership::query()
            ->where('user_id', $request->user()->id)
            ->where('municipality_id', $municipality->id)
            ->where('role', 'municipal_manager')
            ->where('status', 'active')
            ->exists();
    }
}
