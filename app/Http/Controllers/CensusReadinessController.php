<?php

namespace App\Http\Controllers;

use App\Audit\AuditTrail;
use App\Census\CensusReadiness;
use App\Models\Tenant\School;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Conferência do Censo Escolar antes do envio, exclusiva da gestão municipal.
 * Só leitura: lista o que pode reduzir os repasses e estima a receita.
 */
class CensusReadinessController extends Controller
{
    private const LIMIT = 300;

    public function index(Request $request, string $municipio, CensusReadiness $readiness): Response
    {
        $municipality = $request->attributes->get('astuti.municipality');
        Gate::authorize('viewNetworkPanel', [School::class, $municipality]);

        $validated = $request->validate([
            'escola' => ['nullable', 'uuid'],
            'aviso' => ['nullable', Rule::in(array_keys(CensusReadiness::WARNINGS))],
        ]);

        $data = $readiness->forNetwork();
        $list = $data['rows']->filter(fn (array $item): bool => $item['warnings'] !== [])
            ->when($validated['escola'] ?? null, fn ($rows, string $school) => $rows->where('school_id', $school))
            ->when($validated['aviso'] ?? null, fn ($rows, string $type) => $rows->filter(fn (array $item): bool => in_array($type, $item['warnings'], true)))
            ->values();

        // A auditoria guarda apenas que houve a consulta, sem dados pessoais.
        app(AuditTrail::class)->record('tenant', 'census.readiness.viewed');

        return response()->view('census.readiness', [
            'municipality' => $municipality,
            'data' => $data,
            'list' => $list->take(self::LIMIT),
            'listTotal' => $list->count(),
            'limit' => self::LIMIT,
            'filters' => ['escola' => $validated['escola'] ?? null, 'aviso' => $validated['aviso'] ?? null],
            'vaafBase' => config('censo.vaaf_base'),
        ])->header('Cache-Control', 'private, no-store');
    }
}
