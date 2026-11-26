<?php

namespace App\Http\Controllers;

use App\Audit\AuditTrail;
use App\Census\CensusReadiness;
use App\Educacenso\ConferenciaDeAlunos;
use App\Educacenso\ConferenciaDeServidores;
use App\Educacenso\ConferenciaDeTurmas;
use App\Educacenso\Registro00;
use App\Educacenso\Registro10;
use App\Educacenso\Tabelas;
use App\Models\Tenant\CensusParameter;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolCensus;
use App\Models\Tenant\SchoolInfrastructureCensus;
use App\Models\Tenant\SchoolYear;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Conferência do Censo Escolar antes do envio, exclusiva da gestão municipal.
 * Só leitura: lista o que pode reduzir os repasses e estima a receita.
 */
class CensusReadinessController extends Controller
{
    private const LIMIT = 300;

    public function index(Request $request, string $municipio, CensusReadiness $readiness, ConferenciaDeTurmas $turmas, ConferenciaDeAlunos $alunos, ConferenciaDeServidores $servidores): Response
    {
        $municipality = $request->attributes->get('astuti.municipality');
        Gate::authorize('viewNetworkPanel', [School::class, $municipality]);

        $validated = $request->validate([
            'escola' => ['nullable', 'uuid'],
            'aviso' => ['nullable', Rule::in(array_keys(CensusReadiness::WARNINGS))],
        ]);

        $reference = (int) config('censo.year_reference');
        $ano = Tabelas::anoMaisRecente();
        $year = SchoolYear::query()->where('year', $reference)->first();
        // Sem ano correspondente, a consulta deve retornar zero matrículas.
        $data = $readiness->forNetwork(CarbonImmutable::create($reference, 3, 31), $year?->id ?? '00000000-0000-0000-0000-000000000000');
        $layoutYear = $reference === $ano ? $year : null;
        $list = $data['rows']->filter(fn (array $item): bool => $item['warnings'] !== [])
            ->when($validated['escola'] ?? null, fn ($rows, string $school) => $rows->where('school_id', $school))
            ->when($validated['aviso'] ?? null, fn ($rows, string $type) => $rows->filter(fn (array $item): bool => in_array($type, $item['warnings'], true)))
            ->values();

        // Turmas no formato do Educacenso (registro 20), por escola.
        $schools = School::query()->where('status', 'active')->get();
        $classes = $layoutYear === null ? collect() : $turmas->para($schools, $layoutYear)
            ->map(fn (array $linhas): array => [
                'total' => count($linhas),
                'ready' => count(array_filter($linhas, fn (array $linha): bool => $linha['registro']->pronto())),
            ]);
        // Alunos no formato do Educacenso (registros 30 e 60), por escola.
        $students = $layoutYear === null ? collect() : $alunos->para($schools, $layoutYear)
            ->map(fn (array $linhas): array => [
                'total' => count($linhas),
                'ready' => count(array_filter($linhas, fn (array $linha): bool => $linha['r30']->pronto() && $linha['r60']->pronto())),
            ]);

        $staff = $layoutYear === null ? collect() : $servidores->para($schools, $layoutYear)->map(fn (array $linhas): array => [
            'total' => count($linhas),
            'ready' => count(array_filter($linhas, fn (array $linha): bool => $linha['pronto'])),
        ]);

        // Registros 00 e 10: ano correspondente ao leiaute, sem consultas por escola.
        $anoLetivo = $layoutYear;
        $dadosEscolas = SchoolCensus::query()->whereIn('school_id', $schools->pluck('id'))->get()->keyBy('school_id');
        $infraestruturas = SchoolInfrastructureCensus::query()->where('census_year', $ano)->whereIn('school_id', $schools->pluck('id'))->get()->keyBy('school_id');
        $schoolReadiness = $layoutYear === null ? collect() : $schools->mapWithKeys(fn (School $school): array => [
            $school->id => Registro00::para($school, $dadosEscolas->get($school->id), $anoLetivo, $ano)->pronto()
                && Registro10::para($school, $infraestruturas->get($school->id), $dadosEscolas->get($school->id), $ano)->pronto(),
        ]);

        // A auditoria guarda apenas que houve a consulta, sem dados pessoais.
        app(AuditTrail::class)->record('tenant', 'census.readiness.viewed');

        return response()->view('census.readiness', [
            'municipality' => $municipality,
            'data' => $data,
            'list' => $list->take(self::LIMIT),
            'classes' => $classes,
            'students' => $students,
            'staff' => $staff,
            'schoolReadiness' => $schoolReadiness,
            'listTotal' => $list->count(),
            'limit' => self::LIMIT,
            'filters' => ['escola' => $validated['escola'] ?? null, 'aviso' => $validated['aviso'] ?? null],
            'vaafBase' => CensusParameter::vaafFor((int) config('censo.year_reference')),
            'parameter' => CensusParameter::query()->where('reference_year', (int) config('censo.year_reference'))->first(),
        ])->header('Cache-Control', 'private, no-store');
    }

    /** A gestão informa o VAAF do estado no ano, publicado pelo FNDE. */
    public function updateParameters(Request $request, string $municipio): RedirectResponse
    {
        $municipality = $request->attributes->get('astuti.municipality');
        Gate::authorize('viewNetworkPanel', [School::class, $municipality]);

        $raw = $request->input('vaaf_base');
        if (is_string($raw)) {
            // Aceita "6.123,45", "6123,45" e "6123.45".
            $raw = str_contains($raw, ',') ? str_replace(['.', ','], ['', '.'], trim($raw)) : trim($raw);
            $request->merge(['vaaf_base' => $raw]);
        }
        $data = $request->validate([
            'vaaf_base' => ['required', 'numeric', 'gt:0', 'lt:100000'],
            'source_note' => ['required', 'string', 'min:5', 'max:200'],
        ], [
            'vaaf_base.required' => 'Informe o valor anual por aluno do Fundeb no estado.',
            'vaaf_base.numeric' => 'Informe o valor em reais, por exemplo 6.123,45.',
            'vaaf_base.gt' => 'O valor precisa ser maior que zero.',
            'vaaf_base.lt' => 'Confira o valor: ele é por aluno, por ano.',
            'source_note.required' => 'Diga de onde veio o valor (ex.: portaria do FNDE do ano).',
            'source_note.min' => 'Diga de onde veio o valor (ex.: portaria do FNDE do ano).',
            'source_note.max' => 'Use até 200 caracteres na origem do valor.',
        ]);

        $year = (int) config('censo.year_reference');
        DB::connection('tenant')->transaction(function () use ($data, $year, $request): void {
            $parameter = CensusParameter::query()->lockForUpdate()->where('reference_year', $year)->first()
                ?? new CensusParameter(['id' => (string) Str::uuid(), 'reference_year' => $year]);
            $before = $parameter->exists ? $parameter->only(['reference_year', 'vaaf_base', 'source_note']) : null;
            $parameter->fill([
                'vaaf_base' => round((float) $data['vaaf_base'], 2),
                'source_note' => trim($data['source_note']),
                'updated_by' => (string) $request->user()?->getAuthIdentifier(),
            ]);
            if (! $parameter->exists || $parameter->isDirty()) {
                $parameter->save();
                app(AuditTrail::class)->record('tenant', $before === null ? 'census.parameters.created' : 'census.parameters.updated',
                    CensusParameter::class, $parameter->id, $before, $parameter->only(['reference_year', 'vaaf_base', 'source_note']));
            }
        });

        return redirect()->to(route('census.readiness.index', ['municipio' => $municipality->slug]).'#titulo-censo-estimativa')
            ->with('status', 'Valor do Fundeb atualizado. A estimativa já usa o novo valor.');
    }
}
