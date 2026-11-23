<?php

namespace App\Http\Controllers;

use App\Audit\AuditTrail;
use App\Integral\FullTimeReadiness;
use App\Models\Tenant\IntegralFundebExpense;
use App\Models\Tenant\IntegralFundebFinance;
use App\Models\Tenant\IntegralPactTarget;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class FullTimeEducationController extends Controller
{
    private const MONEY = ['required', 'regex:/^[0-9]{1,12}(?:\\.[0-9]{1,2})?$/'];

    public function index(Request $request, string $municipio, FullTimeReadiness $readiness): Response
    {
        $municipality = $request->attributes->get('astuti.municipality');
        Gate::authorize('viewNetworkPanel', [School::class, $municipality]);

        return $this->panel($request, $readiness, null);
    }

    public function school(
        Request $request, string $municipio, string $escola, FullTimeReadiness $readiness,
    ): Response {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($escola);
        Gate::authorize('manageStudents', [$school, $municipality]);

        return $this->panel($request, $readiness, $school);
    }

    private function panel(Request $request, FullTimeReadiness $readiness, ?School $school): Response
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $manager = Gate::allows('viewNetworkPanel', [School::class, $municipality]);
        if (! $manager && $school === null) {
            abort(403);
        }

        $validated = $request->validate(['ano' => ['nullable', 'integer', 'between:2024,2100']]);
        $years = SchoolYear::query()->orderByDesc('year')
            ->get(['id', 'year', 'start_date', 'end_date']);
        $year = $years->firstWhere('year', (int) ($validated['ano'] ?? today()->year)) ?? $years->first();
        if (isset($validated['ano']) && ($year === null || $year->year !== (int) $validated['ano'])) {
            throw ValidationException::withMessages(['ano' => 'Ano letivo não cadastrado.']);
        }

        $data = $year === null ? null : $readiness->forYear($year, $school?->id);
        $targets = $year !== null ? IntegralPactTarget::query()
            ->where('school_year_id', $year->id)->get()->keyBy('stage') : collect();
        $finance = $manager && $year !== null ? IntegralFundebFinance::query()
            ->where('school_year_id', $year->id)->first() : null;
        $expenses = $manager && $year !== null ? IntegralFundebExpense::query()
            ->where('school_year_id', $year->id)->orderByDesc('created_at')->get() : collect();

        $applied = (int) $expenses->sum('amount_cents');
        $minimum = $finance === null ? null : intdiv((int) $finance->revenue_cents * 4 + 99, 100);
        $remaining = $minimum === null ? null : max(0, $minimum - $applied);

        app(AuditTrail::class)->record('tenant', 'integral.panel.viewed');

        return response()->view('integral.panel', [
            'municipality' => $municipality,
            'manager' => $manager,
            'school' => $school,
            'years' => $years,
            'year' => $year,
            'data' => $data,
            'targets' => $targets,
            'finance' => $finance,
            'expenses' => $expenses,
            'applied' => $applied,
            'minimum' => $minimum,
            'remaining' => $remaining,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function updateHours(Request $request, string $municipio, string $escola, string $turma): RedirectResponse
    {
        $municipality = $request->attributes->get('astuti.municipality');
        Gate::authorize('viewNetworkPanel', [School::class, $municipality]);

        $school = School::query()->where('status', 'active')->findOrFail($escola);
        $class = SchoolClass::query()->where('school_id', $school->id)->where('status', 'active')
            ->findOrFail($turma);

        $data = $request->validate([
            'daily_hours' => ['nullable', 'numeric', 'between:0.5,16', 'decimal:0,1'],
        ], [], ['daily_hours' => 'jornada diária']);

        DB::connection('tenant')->transaction(function () use ($class, $data): void {
            $current = SchoolClass::query()->whereKey($class->id)->lockForUpdate()->firstOrFail();
            $current->daily_hours = $data['daily_hours'] ?? null;
            if ($current->isDirty()) {
                $current->save();
            }
        });

        return redirect()->route('school.classes.show', [
            'municipio' => $municipality->slug, 'escola' => $school->id, 'turma' => $class->id,
        ])->with('status', 'Jornada diária atualizada. A Conferência do Censo passa a usar as horas informadas.');
    }

    public function saveTarget(Request $request, string $municipio): RedirectResponse
    {
        $municipality = $request->attributes->get('astuti.municipality');
        Gate::authorize('viewNetworkPanel', [School::class, $municipality]);

        $data = $request->validate([
            'ano_id' => ['required', 'uuid', Rule::exists('tenant.school_years', 'id')],
            'etapa' => ['required', Rule::in(array_keys(FullTimeReadiness::STAGES))],
            'meta' => ['required', 'integer', 'between:0,1000000'],
        ]);
        $year = SchoolYear::query()->findOrFail($data['ano_id']);

        DB::connection('tenant')->transaction(function () use ($data, $request): void {
            $target = IntegralPactTarget::query()
                ->where('school_year_id', $data['ano_id'])->where('stage', $data['etapa'])
                ->lockForUpdate()->first();

            $before = $target?->only(['id', 'school_year_id', 'stage', 'target_enrollments']);
            if ($target === null) {
                $target = IntegralPactTarget::query()->create([
                    'id' => (string) Str::uuid(),
                    'school_year_id' => $data['ano_id'],
                    'stage' => $data['etapa'],
                    'target_enrollments' => (int) $data['meta'],
                    'updated_by' => $request->user()->id,
                ]);
            } else {
                $target->fill(['target_enrollments' => (int) $data['meta'], 'updated_by' => $request->user()->id])->save();
            }

            app(AuditTrail::class)->record(
                'tenant', 'integral.target.saved', IntegralPactTarget::class, $target->id,
                $before, $target->only(['id', 'school_year_id', 'stage', 'target_enrollments', 'updated_by']),
            );
        });

        return redirect()->route('integral.index', ['municipio' => $municipality->slug, 'ano' => $year->year])
            ->with('status', 'Meta pactuada registrada.');
    }

    public function saveRevenue(Request $request, string $municipio): RedirectResponse
    {
        $municipality = $request->attributes->get('astuti.municipality');
        Gate::authorize('viewNetworkPanel', [School::class, $municipality]);

        $data = $request->validate([
            'ano_id' => ['required', 'uuid'],
            'receita' => self::MONEY,
        ]);
        $year = SchoolYear::query()->findOrFail($data['ano_id']);
        $cents = self::cents($data['receita']);

        DB::connection('tenant')->transaction(function () use ($data, $request, $cents): void {
            $finance = IntegralFundebFinance::query()
                ->where('school_year_id', $data['ano_id'])->lockForUpdate()->first();
            $before = $finance?->only(['id', 'school_year_id', 'revenue_cents']);

            if ($finance === null) {
                $finance = IntegralFundebFinance::query()->create([
                    'id' => (string) Str::uuid(), 'school_year_id' => $data['ano_id'],
                    'revenue_cents' => $cents, 'updated_by' => $request->user()->id,
                ]);
            } else {
                $finance->fill(['revenue_cents' => $cents, 'updated_by' => $request->user()->id])->save();
            }

            app(AuditTrail::class)->record(
                'tenant', 'integral.revenue.saved', IntegralFundebFinance::class, $finance->id,
                $before, $finance->only(['id', 'school_year_id', 'revenue_cents', 'updated_by']),
            );
        });

        return redirect()->route('integral.index', ['municipio' => $municipality->slug, 'ano' => $year->year])
            ->with('status', 'Receita do Fundeb registrada para o ano.');
    }

    public function storeExpense(Request $request, string $municipio): RedirectResponse
    {
        $municipality = $request->attributes->get('astuti.municipality');
        Gate::authorize('viewNetworkPanel', [School::class, $municipality]);

        $data = $request->validate([
            'ano_id' => ['required', 'uuid'],
            'descricao' => ['required', 'string', 'min:4', 'max:240'],
            'valor' => self::MONEY,
            'documento' => ['nullable', 'file', 'mimes:pdf', 'max:8192'],
        ]);
        $year = SchoolYear::query()->findOrFail($data['ano_id']);
        $cents = self::cents($data['valor']);
        if ($cents <= 0) {
            throw ValidationException::withMessages(['valor' => 'Informe um valor positivo para a despesa.']);
        }

        $upload = $request->file('documento');
        $path = null;
        if ($upload !== null) {
            $path = $upload->storeAs('integral/evidencias/'.$year->year, (string) Str::uuid().'.pdf', 'local');
            if ($path === false) {
                throw ValidationException::withMessages(['documento' => 'Falha ao salvar o PDF privado.']);
            }
        }

        try {
            DB::connection('tenant')->transaction(function () use ($request, $data, $cents, $upload, $path): void {
                $expense = IntegralFundebExpense::query()->create([
                    'id' => (string) Str::uuid(),
                    'school_year_id' => $data['ano_id'],
                    'description' => trim($data['descricao']),
                    'amount_cents' => $cents,
                    'evidence_path' => $path,
                    'evidence_name' => $upload === null ? null : mb_substr(basename(str_replace('\\', '/', $upload->getClientOriginalName())), 0, 180),
                    'evidence_mime' => $upload === null ? null : 'application/pdf',
                    'evidence_uploaded_at' => $upload === null ? null : now(),
                    'created_by' => $request->user()->id,
                ]);

                app(AuditTrail::class)->record(
                    'tenant', 'integral.expense.created', IntegralFundebExpense::class, $expense->id,
                    null, [
                        ...$expense->only(['id', 'school_year_id', 'description', 'amount_cents', 'created_by']),
                        'has_evidence' => $path !== null,
                    ],
                );
            });
        } catch (Throwable $e) {
            if ($path !== null) {
                Storage::disk('local')->delete($path);
            }

            throw $e;
        }

        return redirect()->route('integral.index', ['municipio' => $municipality->slug, 'ano' => $year->year])
            ->with('status', 'Aplicação de recursos registrada e auditada.');
    }

    public function evidence(Request $request, string $municipio, string $despesa): StreamedResponse
    {
        $municipality = $request->attributes->get('astuti.municipality');
        Gate::authorize('viewNetworkPanel', [School::class, $municipality]);
        $expense = IntegralFundebExpense::query()->findOrFail($despesa);
        abort_unless($expense->evidence_path !== null && Storage::disk('local')->exists($expense->evidence_path), 404);

        app(AuditTrail::class)->record('tenant', 'integral.expense.evidence.viewed', IntegralFundebExpense::class, $expense->id);

        return Storage::disk('local')->download($expense->evidence_path, $expense->evidence_name, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private static function cents(string $value): int
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }
}
