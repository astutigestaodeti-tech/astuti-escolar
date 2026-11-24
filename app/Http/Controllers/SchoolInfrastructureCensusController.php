<?php

namespace App\Http\Controllers;

use App\Audit\AuditTrail;
use App\Educacenso\Leiaute;
use App\Educacenso\Registro10;
use App\Educacenso\Tabelas;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolCensus;
use App\Models\Tenant\SchoolInfrastructureCensus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SchoolInfrastructureCensusController extends Controller
{
    public function edit(Request $request, string $municipio, string $escola): Response
    {
        [$municipality, $school] = $this->authorizedSchool($request, $escola);
        $ano = Tabelas::anoMaisRecente();
        $leiaute = Leiaute::do($ano);
        $censo = SchoolCensus::query()->find($school->id);
        $questionario = SchoolInfrastructureCensus::query()->where('school_id', $school->id)->where('census_year', $ano)->first();
        $anterior = $questionario === null
            ? SchoolInfrastructureCensus::query()->where('school_id', $school->id)->where('census_year', $ano - 1)->first()
            : null;

        $answers = (array) ($questionario?->answers ?? $anterior?->answers);
        // Só os campos deste leiaute aparecem como sugestões. Nunca grava no GET.
        $answers = array_intersect_key($answers, array_flip(range(3, 187)));

        return response()->view('census.infrastructure', [
            'municipality' => $municipality,
            'school' => $school,
            'ano' => $ano,
            'copiado' => $anterior !== null,
            'salvo' => $questionario !== null,
            'answers' => $answers,
            'blocos' => $this->blocos($leiaute),
            'linguas' => Tabelas::do($ano)->linguasIndigenas(),
            'registro' => Registro10::para($school, $questionario, $censo, $ano),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, string $municipio, string $escola): RedirectResponse
    {
        [$municipality, $school] = $this->authorizedSchool($request, $escola);
        $ano = Tabelas::anoMaisRecente();
        $data = $request->validate([
            'answers' => ['required', 'array', 'max:185'],
            'answers.*' => ['nullable', 'string', 'max:8'],
        ], [
            'answers.required' => 'Preencha o questionário de infraestrutura.',
            'answers.*.string' => 'Informe apenas um valor por campo.',
            'answers.*.max' => 'A resposta excede o tamanho máximo.',
        ]);
        $answers = [];
        foreach ($data['answers'] as $numero => $valor) {
            if (! is_int($numero) || $numero < 3 || $numero > 187) {
                throw ValidationException::withMessages(['answers' => 'Há campo fora do leiaute do registro 10.']);
            }
            if ($valor !== null && $valor !== '') {
                $answers[$numero] = $valor;
            }
        }
        ksort($answers);

        DB::connection('tenant')->transaction(function () use ($school, $ano, $answers, $request): void {
            $school = School::query()->where('status', 'active')->lockForUpdate()->findOrFail($school->id);
            $censo = SchoolCensus::query()->find($school->id);
            $record = SchoolInfrastructureCensus::query()->where('school_id', $school->id)->where('census_year', $ano)->first()
                ?? new SchoolInfrastructureCensus(['id' => (string) Str::uuid(), 'school_id' => $school->id, 'census_year' => $ano]);
            $before = (array) $record->answers;
            $created = ! $record->exists;
            $record->answers = (object) $answers;
            $registro = Registro10::para($school, $record, $censo, $ano);
            if (! $registro->aplicavel) {
                throw ValidationException::withMessages(['answers' => 'Escola paralisada ou extinta não envia o registro 10. O histórico foi preservado.']);
            }
            $errors = [];
            foreach ($registro->pendencias as $pendencia) {
                // A situação da escola e seu código são corrigidos no registro 00.
                // O questionário pode ser preenchido enquanto esses dados estão pendentes.
                if ($pendencia->recusa() && $pendencia->campo !== null && $pendencia->campo >= 3) {
                    $errors['answers.'.$pendencia->campo][] = $pendencia->mensagem;
                }
            }
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
            $changed = [];
            foreach (array_unique([...array_keys($before), ...array_keys($answers)]) as $numero) {
                if (($before[$numero] ?? null) !== ($answers[$numero] ?? null)) {
                    $changed[] = (string) $numero;
                }
            }
            if ($created || $changed !== []) {
                $record->updated_by = $request->user()?->getAuthIdentifier();
                $record->save();
                app(AuditTrail::class)->record('tenant', $created ? 'school_infrastructure_census.created' : 'school_infrastructure_census.updated',
                    SchoolInfrastructureCensus::class, $record->id, null, ['school_id' => $school->id, 'census_year' => $ano, 'changed_fields' => $changed]);
            }
        });

        return redirect()->route('school.census.infrastructure.edit', ['municipio' => $municipality->slug, 'escola' => $school->id])
            ->with('status', 'Questionário de infraestrutura salvo. Confira as pendências.')
            ->header('Cache-Control', 'private, no-store');
    }

    /** Grupos oficiais e campos avulsos entre eles, na ordem do leiaute. */
    private function blocos(Leiaute $leiaute): array
    {
        $blocos = [];
        $campos = $leiaute->campos('10');
        $inicio = 3;
        foreach ($leiaute->grupos('10') as $grupo) {
            if ($inicio < $grupo['de']) {
                $blocos[] = ['nome' => 'Campos complementares ('.$inicio.' a '.($grupo['de'] - 1).')', 'campos' => array_intersect_key($campos, array_flip(range($inicio, $grupo['de'] - 1)))];
            }
            $blocos[] = ['nome' => $grupo['nome'], 'regras' => $grupo['regras'], 'campos' => array_intersect_key($campos, array_flip(range($grupo['de'], $grupo['ate'])))];
            $inicio = $grupo['ate'] + 1;
        }
        if ($inicio <= count($campos)) {
            $blocos[] = ['nome' => 'Campos complementares', 'campos' => array_intersect_key($campos, array_flip(range($inicio, count($campos))))];
        }

        return $blocos;
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
