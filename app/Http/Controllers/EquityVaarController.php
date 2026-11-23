<?php

namespace App\Http\Controllers;

use App\Audit\AuditTrail;
use App\Equity\EquityReadiness;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\VaarCondition;
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

class EquityVaarController extends Controller
{
    public function index(Request $request, string $municipio, EquityReadiness $readiness): Response
    {
        $municipality = $request->attributes->get('astuti.municipality');
        Gate::authorize('viewNetworkPanel', [School::class, $municipality]);

        return $this->panel($request, $municipality, $readiness, null);
    }

    public function school(
        Request $request, string $municipio, string $escola, EquityReadiness $readiness,
    ): Response {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($escola);
        Gate::authorize('viewEquity', [$school, $municipality]);

        return $this->panel($request, $municipality, $readiness, $school);
    }

    public function updateCondition(
        Request $request, string $municipio, string $condicao,
    ): RedirectResponse {
        $municipality = $request->attributes->get('astuti.municipality');
        Gate::authorize('viewNetworkPanel', [School::class, $municipality]);
        abort_unless(in_array((int) $condicao, [1, 2, 3, 4, 5], true), 404);

        $data = $request->validate([
            'ano' => ['required', 'integer', 'between:2024,2100'],
            'status' => ['required', Rule::in(array_keys(EquityReadiness::STATUSES))],
            'responsavel' => ['required', 'string', 'min:2', 'max:120'],
            'documento' => ['nullable', 'file', 'mimes:pdf', 'max:8192'],
            'remover_documento' => ['nullable', 'boolean'],
        ]);
        $year = (int) $data['ano'];
        $number = (int) $condicao;
        $upload = $request->file('documento');
        if ($upload !== null && $request->boolean('remover_documento')) {
            throw ValidationException::withMessages(['documento' => 'Escolha substituir ou remover o anexo, não ambos.']);
        }

        $newPath = null;
        if ($upload !== null) {
            // O disco local do Laravel é privado. Nunca armazenar em public.
            $newPath = $upload->storeAs(
                'vaar/evidencias/'.$year,
                Str::uuid()->toString().'.pdf',
                'local',
            );
            if ($newPath === false) {
                throw ValidationException::withMessages(['documento' => 'Falha ao guardar o PDF com acesso restrito.']);
            }
        }

        $oldPath = null;
        try {
            DB::connection('tenant')->transaction(function () use (
                $request, $year, $number, $data, $upload, $newPath, &$oldPath,
            ): void {
                $record = VaarCondition::query()
                    ->where('reference_year', $year)
                    ->where('condition_number', $number)
                    ->lockForUpdate()->first();

                $oldPath = $record?->evidence_path;
                $before = $record?->only([
                    'id', 'reference_year', 'condition_number', 'status', 'responsible', 'updated_by',
                ]);

                $values = [
                    'status' => $data['status'],
                    'responsible' => trim($data['responsavel']),
                    'updated_by' => $request->user()->id,
                ];
                if ($upload !== null) {
                    $original = basename(str_replace('\\', '/', $upload->getClientOriginalName()));
                    $values = [
                        ...$values,
                        'evidence_path' => $newPath,
                        'evidence_name' => mb_substr($original, 0, 180),
                        'evidence_mime' => 'application/pdf',
                        'evidence_uploaded_at' => now(),
                    ];
                } elseif ($request->boolean('remover_documento')) {
                    $values = [
                        ...$values,
                        'evidence_path' => null,
                        'evidence_name' => null,
                        'evidence_mime' => null,
                        'evidence_uploaded_at' => null,
                    ];
                }

                if ($record === null) {
                    $record = VaarCondition::query()->create([
                        'id' => (string) Str::uuid(),
                        'reference_year' => $year,
                        'condition_number' => $number,
                        ...$values,
                    ]);
                } else {
                    $record->forceFill($values)->save();
                }

                app(AuditTrail::class)->record(
                    'tenant', 'equity.vaar.condition.updated', VaarCondition::class, $record->id,
                    $before, [
                        ...$record->only([
                            'id', 'reference_year', 'condition_number', 'status', 'responsible', 'updated_by',
                        ]),
                        'has_evidence' => $record->evidence_path !== null,
                    ],
                );
            });
        } catch (Throwable $e) {
            if ($newPath !== null) {
                Storage::disk('local')->delete($newPath);
            }

            throw $e;
        }

        if ($oldPath !== null && ($newPath !== null || $request->boolean('remover_documento'))) {
            Storage::disk('local')->delete($oldPath);
        }

        return redirect()->route('equity.vaar.index', [
            'municipio' => $municipality->slug, 'ano' => $year,
        ])->with('status', 'Condicionalidade '.$number.' atualizada. A avaliação oficial continua a cargo dos órgãos competentes.');
    }

    public function evidence(Request $request, string $municipio, string $condicao): StreamedResponse
    {
        $municipality = $request->attributes->get('astuti.municipality');
        Gate::authorize('viewNetworkPanel', [School::class, $municipality]);
        abort_unless(in_array((int) $condicao, [1, 2, 3, 4, 5], true), 404);

        $data = $request->validate(['ano' => ['required', 'integer', 'between:2024,2100']]);
        $condition = VaarCondition::query()
            ->where('reference_year', (int) $data['ano'])
            ->where('condition_number', (int) $condicao)->firstOrFail();
        abort_unless($condition->evidence_path !== null && Storage::disk('local')->exists($condition->evidence_path), 404);

        app(AuditTrail::class)->record('tenant', 'equity.vaar.evidence.viewed',
            VaarCondition::class, $condition->id);

        return Storage::disk('local')->download(
            $condition->evidence_path,
            $condition->evidence_name,
            ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'],
        );
    }

    private function panel(Request $request, $municipality, EquityReadiness $readiness, ?School $school): Response
    {
        $filters = $request->validate([
            'ano' => ['nullable', 'integer', 'between:2024,2100'],
            'escola' => ['nullable', 'uuid'],
            'turma' => ['nullable', 'uuid'],
        ]);

        $municipalManager = Gate::allows('viewNetworkPanel', [School::class, $municipality]);
        $schoolId = $school?->id ?? ($filters['escola'] ?? null);

        if ($school !== null && isset($filters['escola']) && $filters['escola'] !== $school->id) {
            abort(404);
        }
        if ($schoolId !== null) {
            $target = School::query()->where('status', 'active')->findOrFail($schoolId);
            Gate::authorize('viewEquity', [$target, $municipality]);
        } elseif (! $municipalManager) {
            abort(403);
        }

        $years = SchoolYear::query()->orderByDesc('year')->get(['id', 'year']);
        $year = $years->firstWhere('year', (int) ($filters['ano'] ?? today()->year)) ?? $years->first();
        if (isset($filters['ano']) && ($year === null || $year->year !== (int) $filters['ano'])) {
            throw ValidationException::withMessages(['ano' => 'Ano letivo não cadastrado.']);
        }

        $classId = $filters['turma'] ?? null;
        if ($classId !== null) {
            abort_unless($schoolId !== null && $year !== null, 404);
            SchoolClass::query()->where('school_year_id', $year->id)
                ->where('school_id', $schoolId)->findOrFail($classId);
        }

        $data = $year === null ? null : $readiness->forYear($year, $schoolId, $classId);

        $conditions = $municipalManager && $year !== null
            ? VaarCondition::query()->where('reference_year', $year->year)->get()->keyBy('condition_number')
            : collect();

        app(AuditTrail::class)->record('tenant', 'equity.vaar.panel.viewed');

        $allSchools = $municipalManager
            ? School::query()->where('status', 'active')->orderBy('name')->get(['id', 'name'])
            : collect([$school]);

        return response()->view('equity.panel', [
            'allSchools' => $allSchools,
            'municipality' => $municipality,
            'data' => $data,
            'years' => $years,
            'year' => $year,
            'school' => $school,
            'schoolId' => $schoolId,
            'classId' => $classId,
            'manager' => $municipalManager,
            'conditions' => $conditions,
        ])->header('Cache-Control', 'private, no-store');
    }

}
