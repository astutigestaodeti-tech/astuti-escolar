<?php

namespace App\Http\Controllers;

use App\Audit\AuditTrail;
use App\Models\Tenant\School;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\TransportRouteCensus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Transporte escolar no Censo: poder público e veículos por rota da escola
 * (registro 60, campos 23 a 33). As rotas vêm das matrículas ativas que usam
 * transporte; a tela não mostra alunos.
 */
class TransportCensusController extends Controller
{
    public function edit(Request $request, string $municipio, string $escola): Response
    {
        [$municipality, $school] = $this->authorizedSchool($request, $escola);
        $rotas = $this->routes($school);

        return response()->view('census.transport', [
            'municipality' => $municipality,
            'school' => $school,
            'routes' => $rotas['com_rota'],
            'withoutRoute' => $rotas['sem_rota'],
            'saved' => TransportRouteCensus::query()->where('school_id', $school->id)->get()->keyBy('route_name'),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, string $municipio, string $escola): RedirectResponse
    {
        [$municipality, $school] = $this->authorizedSchool($request, $escola);
        $rotas = $this->routes($school)['com_rota'];

        $data = $request->validate([
            'rotas' => ['required', 'array', 'max:200'],
            'rotas.*.nome' => ['required', 'string', 'max:60'],
            'rotas.*.poder' => ['nullable', Rule::in(array_map('strval', array_keys(TransportRouteCensus::PUBLIC_POWERS)))],
            'rotas.*.veiculos' => ['nullable', 'array'],
            'rotas.*.veiculos.*' => ['string', Rule::in(array_keys(TransportRouteCensus::VEHICLES))],
        ], [
            'rotas.*.poder.in' => 'Escolha o poder público na lista.',
            'rotas.*.veiculos.*.in' => 'Escolha os veículos na lista do Censo.',
        ]);

        $erros = [];
        $linhas = [];
        foreach ($data['rotas'] as $indice => $rota) {
            $nome = trim($rota['nome']);
            if (! array_key_exists($nome, $rotas)) {
                continue;
            }
            $veiculos = array_values(array_unique($rota['veiculos'] ?? []));
            $poder = isset($rota['poder']) ? (int) $rota['poder'] : null;
            if ($veiculos !== [] && $poder === null) {
                $erros["rotas.{$indice}.poder"] = "Rota {$nome}: informe se o transporte é municipal ou estadual.";
            }
            // Campos 24 a 33: não podem ser todos 0 nem todos 1.
            if ($poder !== null && $veiculos === []) {
                $erros["rotas.{$indice}.veiculos"] = "Rota {$nome}: marque pelo menos um tipo de veículo.";
            }
            if (count($veiculos) === count(TransportRouteCensus::VEHICLES)) {
                $erros["rotas.{$indice}.veiculos"] = "Rota {$nome}: o Censo não aceita todos os tipos de veículo marcados ao mesmo tempo.";
            }
            $linhas[$nome] = ['public_power' => $poder, 'vehicles' => $veiculos];
        }
        if ($erros !== []) {
            throw ValidationException::withMessages($erros);
        }

        $alteradas = DB::connection('tenant')->transaction(function () use ($school, $linhas, $request): int {
            $alteradas = 0;
            foreach ($linhas as $nome => $valores) {
                $registro = TransportRouteCensus::query()->where('school_id', $school->id)->where('route_name', $nome)->lockForUpdate()->first()
                    ?? new TransportRouteCensus(['id' => (string) Str::uuid(), 'school_id' => $school->id, 'route_name' => $nome]);
                $registro->fill([...$valores, 'updated_by' => $request->user()?->getAuthIdentifier()]);
                if (! $registro->exists || $registro->isDirty(['public_power', 'vehicles'])) {
                    $registro->save();
                    $alteradas++;
                }
            }

            return $alteradas;
        });
        if ($alteradas > 0) {
            app(AuditTrail::class)->record('tenant', 'transport_census.updated', School::class, $school->id, null, ['routes_changed' => $alteradas]);
        }

        return redirect()->route('school.census.transport.edit', ['municipio' => $municipality->slug, 'escola' => $school->id])
            ->with('status', $alteradas > 0 ? 'Transporte no Censo atualizado.' : 'Nada mudou.');
    }

    /** @return array{com_rota: array<string, int>, sem_rota: int} nome da rota => alunos */
    private function routes(School $school): array
    {
        $contagem = StudentEnrollment::query()
            ->where('school_id', $school->id)->where('status', 'active')->where('transport_status', 'active')
            ->whereHas('student', fn ($query) => $query->whereNull('deleted_at'))
            ->get(['transport_route'])
            ->countBy(fn (StudentEnrollment $e): string => trim((string) $e->transport_route));
        $semRota = (int) ($contagem[''] ?? 0);
        $comRota = $contagem->except([''])->sortKeysUsing(fn (string $a, string $b): int => strnatcasecmp(Str::ascii($a), Str::ascii($b)))->all();

        return ['com_rota' => $comRota, 'sem_rota' => $semRota];
    }

    /** @return array{0: mixed, 1: School} */
    private function authorizedSchool(Request $request, string $escola): array
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($escola);
        Gate::authorize('manageStudents', [$school, $municipality]);

        return [$municipality, $school];
    }
}
