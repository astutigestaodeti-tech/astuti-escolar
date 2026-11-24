<?php

namespace App\Http\Controllers;

use App\Academics\GradeAccess;
use App\Audit\AuditTrail;
use App\Models\Tenant\AcademicPeriod;
use App\Models\Tenant\ClassCouncil;
use App\Models\Tenant\CurricularComponent;
use App\Models\Tenant\GradeEntry;
use App\Models\Tenant\SchoolYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AcademicConfigController extends Controller
{
    private function municipality(Request $request, GradeAccess $access): mixed
    {
        $municipality = $request->attributes->get('astuti.municipality');
        abort_unless($access->isMunicipalManager($request->user(), $municipality), 403);

        return $municipality;
    }

    public function periods(Request $request, string $municipio, string $ano, GradeAccess $access): Response
    {
        $municipality = $this->municipality($request, $access);
        $year = SchoolYear::query()->findOrFail($ano);
        $periods = AcademicPeriod::query()->where('school_year_id', $year->id)->orderBy('number')->get();
        $requested = $request->validate(['formato' => ['nullable', Rule::in(['bimestre', 'trimestre'])]]);
        $kind = $requested['formato'] ?? $periods->first()?->kind ?? 'bimestre';
        $count = $kind === 'trimestre' ? 3 : 4;
        $formPeriods = [];
        $dayCount = (int) $year->start_date->diffInDays($year->end_date) + 1;

        for ($number = 1; $number <= $count; $number++) {
            $current = $periods->firstWhere('number', $number);
            if ($current !== null && $current->kind === $kind) {
                $formPeriods[$number] = [
                    'starts_on' => $current->starts_on->toDateString(),
                    'ends_on' => $current->ends_on->toDateString(),
                ];
            } else {
                $formPeriods[$number] = [
                    'starts_on' => $year->start_date->copy()->addDays((int) floor($dayCount * ($number - 1) / $count))->toDateString(),
                    'ends_on' => $year->start_date->copy()->addDays((int) floor($dayCount * $number / $count) - 1)->toDateString(),
                ];
            }
        }

        return response()->view('academics.periods', [
            'municipality' => $municipality,
            'year' => $year,
            'periods' => $periods,
            'formPeriods' => $formPeriods,
            'kind' => $kind,
            'years' => SchoolYear::query()->orderByDesc('year')->get(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function updatePeriods(
        Request $request,
        string $municipio,
        string $ano,
        GradeAccess $access,
    ): RedirectResponse {
        $municipality = $this->municipality($request, $access);
        $data = $request->validate([
            'kind' => ['required', Rule::in(['bimestre', 'trimestre'])],
            'periods' => ['required', 'array'],
            'periods.*.starts_on' => ['required', 'date_format:Y-m-d'],
            'periods.*.ends_on' => ['required', 'date_format:Y-m-d'],
        ]);

        $count = $data['kind'] === 'trimestre' ? 3 : 4;
        if (count($data['periods']) !== $count
            || array_map('intval', array_keys($data['periods'])) !== range(1, $count)) {
            throw ValidationException::withMessages(['periods' => 'Informe as datas de todos os períodos em ordem.']);
        }

        DB::connection('tenant')->transaction(function () use ($ano, $data, $count): void {
            $year = SchoolYear::query()->lockForUpdate()->findOrFail($ano);
            $old = AcademicPeriod::query()->where('school_year_id', $year->id)->orderBy('number')->lockForUpdate()->get();
            $start = $year->start_date->toDateString();
            $end = $year->end_date->toDateString();
            $previousEnd = null;

            for ($number = 1; $number <= $count; $number++) {
                $range = $data['periods'][$number];
                if ($range['starts_on'] < $start || $range['ends_on'] > $end
                    || $range['starts_on'] > $range['ends_on']
                    || ($previousEnd !== null && $range['starts_on'] <= $previousEnd)) {
                    throw ValidationException::withMessages([
                        'periods' => 'As datas devem pertencer ao ano letivo, em ordem e sem sobreposição.',
                    ]);
                }
                $previousEnd = $range['ends_on'];
            }

            if ($old->contains(fn (AcademicPeriod $period): bool => $period->closed_at !== null)) {
                throw ValidationException::withMessages(['periods' => 'Período fechado não pode ter datas nem formato alterados.']);
            }

            $changeType = $old->count() !== $count || $old->contains(
                fn (AcademicPeriod $period): bool => $period->kind !== $data['kind']
            );
            if (ClassCouncil::query()->whereIn('academic_period_id', $old->pluck('id'))->exists()) {
                $changedDates = $old->contains(fn (AcademicPeriod $p): bool => $p->starts_on->toDateString() !== ($data['periods'][$p->number]['starts_on'] ?? null)
                    || $p->ends_on->toDateString() !== ($data['periods'][$p->number]['ends_on'] ?? null));
                if ($changeType || $changedDates) {
                    throw ValidationException::withMessages(['periods' => 'Já existem conselhos vinculados. As datas e o formato dos períodos devem ser preservados.']);
                }
            }
            if ($changeType && GradeEntry::query()->whereIn('academic_period_id', $old->pluck('id'))->exists()) {
                throw ValidationException::withMessages([
                    'kind' => 'Já existem notas vinculadas. Não é possível trocar bimestres por trimestres ou alterar a quantidade.',
                ]);
            }

            if ($changeType) {
                foreach ($old as $period) {
                    app(AuditTrail::class)->record('tenant', 'academic.period.removed', AcademicPeriod::class, $period->id, [
                        'school_year_id' => $year->id, 'kind' => $period->kind,
                        'number' => $period->number,
                    ]);
                }
                AcademicPeriod::query()->where('school_year_id', $year->id)->delete();
            }

            for ($number = 1; $number <= $count; $number++) {
                $range = $data['periods'][$number];
                $period = $changeType ? null : $old->firstWhere('number', $number);
                $values = [
                    'school_year_id' => $year->id, 'kind' => $data['kind'], 'number' => $number,
                    'starts_on' => $range['starts_on'], 'ends_on' => $range['ends_on'],
                ];
                if ($period === null) {
                    $period = AcademicPeriod::query()->create(['id' => (string) Str::uuid(), ...$values]);
                    app(AuditTrail::class)->record('tenant', 'academic.period.created', AcademicPeriod::class, $period->id, null, $values);

                    continue;
                }

                $before = [
                    'starts_on' => $period->starts_on->toDateString(),
                    'ends_on' => $period->ends_on->toDateString(),
                ];
                if ($before === ['starts_on' => $range['starts_on'], 'ends_on' => $range['ends_on']]) {
                    continue;
                }
                $period->fill($values)->save();
                app(AuditTrail::class)->record('tenant', 'academic.period.updated', AcademicPeriod::class, $period->id, $before, $range);
            }
        });

        return redirect()->route('academic.periods.index', ['municipio' => $municipality->slug, 'ano' => $ano])
            ->with('status', 'Períodos avaliativos atualizados e auditados.');
    }

    public function closePeriod(
        Request $request,
        string $municipio,
        string $ano,
        string $periodo,
        GradeAccess $access,
    ): RedirectResponse {
        $municipality = $this->municipality($request, $access);
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']]);

        DB::connection('tenant')->transaction(function () use ($ano, $periodo, $request, $data): void {
            SchoolYear::query()->findOrFail($ano);
            $period = AcademicPeriod::query()->where('school_year_id', $ano)->lockForUpdate()->findOrFail($periodo);
            if ($period->closed_at !== null) {
                throw ValidationException::withMessages(['reason' => 'Este período já está fechado.']);
            }

            $period->forceFill([
                'closed_at' => now(), 'closed_by' => $request->user()->id,
                'close_reason' => trim($data['reason']),
            ])->save();

            app(AuditTrail::class)->record('tenant', 'academic.period.closed', AcademicPeriod::class, $period->id,
                ['closed_at' => null], [
                    'closed_at' => $period->closed_at, 'closed_by' => $period->closed_by,
                    'close_reason' => $period->close_reason,
                ]);
        });

        return redirect()->route('academic.periods.index', ['municipio' => $municipality->slug, 'ano' => $ano])
            ->with('status', 'Período fechado. Somente a gestão poderá corrigir notas com motivo e segundo fator recente.');
    }

    public function components(Request $request, string $municipio, GradeAccess $access): Response
    {
        $municipality = $this->municipality($request, $access);
        $components = CurricularComponent::query()->orderBy('display_order')->orderBy('name')->get();

        return response()->view('academics.components', compact('municipality', 'components'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function storeComponent(
        Request $request,
        string $municipio,
        GradeAccess $access,
    ): RedirectResponse {
        $municipality = $this->municipality($request, $access);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'is_optional' => ['nullable', 'boolean'],
        ]);
        $name = preg_replace('/\s+/u', ' ', trim($data['name']));
        $code = Str::slug($name);

        if ($code === '' || strlen($code) > 80
            || CurricularComponent::query()->whereRaw('lower(name) = lower(?)', [$name])->exists()
            || CurricularComponent::query()->where('code', $code)->exists()) {
            throw ValidationException::withMessages(['name' => 'Componente já cadastrado ou nome inválido.']);
        }

        DB::connection('tenant')->transaction(function () use ($name, $code, $data): void {
            $component = CurricularComponent::query()->create([
                'id' => (string) Str::uuid(),
                'code' => $code,
                'name' => $name,
                'is_optional' => (bool) ($data['is_optional'] ?? false),
                'active' => true,
                'display_order' => 100,
            ]);
            app(AuditTrail::class)->record('tenant', 'academic.component.created', CurricularComponent::class,
                $component->id, null, $component->only('code', 'name', 'is_optional', 'active'));
        });

        return redirect()->route('academic.components.index', ['municipio' => $municipality->slug])
            ->with('status', 'Componente curricular cadastrado.');
    }

    public function toggleComponent(
        Request $request,
        string $municipio,
        string $componente,
        GradeAccess $access,
    ): RedirectResponse {
        $municipality = $this->municipality($request, $access);
        $data = $request->validate(['active' => ['required', 'boolean']]);

        DB::connection('tenant')->transaction(function () use ($componente, $data): void {
            $component = CurricularComponent::query()->lockForUpdate()->findOrFail($componente);
            $before = $component->active;
            $new = (bool) $data['active'];
            if ($before === $new) {
                return;
            }
            $component->active = $new;
            $component->save();
            app(AuditTrail::class)->record('tenant', 'academic.component.updated', CurricularComponent::class,
                $component->id, ['active' => $before], ['active' => $new]);
        });

        return redirect()->route('academic.components.index', ['municipio' => $municipality->slug])
            ->with('status', 'Situação do componente atualizada. As notas anteriores permanecem preservadas.');
    }
}
