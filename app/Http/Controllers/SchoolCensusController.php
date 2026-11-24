<?php

namespace App\Http\Controllers;

use App\Audit\AuditTrail;
use App\Educacenso\Registro00;
use App\Educacenso\Tabelas;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolCensus;
use App\Models\Tenant\SchoolYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SchoolCensusController extends Controller
{
    public function edit(Request $request, string $municipio, string $escola): Response
    {
        [$municipality, $school] = $this->authorizedSchool($request, $escola);
        $ano = Tabelas::anoMaisRecente();
        $tabelas = Tabelas::do($ano);
        $codigo = $tabelas->municipioPorNome($school->address_city, $school->address_state);
        $census = SchoolCensus::query()->find($school->id);
        $year = SchoolYear::query()->where('year', $ano)->first();

        return response()->view('census.school-form', [
            'municipality' => $municipality,
            'school' => $school,
            'census' => $census,
            'year' => $year,
            'ano' => $ano,
            'municipioInep' => $codigo === null ? null : $tabelas->municipio($codigo),
            'districts' => $codigo === null ? [] : $tabelas->distritos($codigo),
            'regionalBodies' => $tabelas->orgaosRegionais((string) $school->address_state),
            'registro' => Registro00::para($school, $census, $year, $ano),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, string $municipio, string $escola): RedirectResponse
    {
        [$municipality, $school] = $this->authorizedSchool($request, $escola);
        $data = $request->validate([
            'operating_status' => ['required', Rule::in([1, 2, 3])],
            'district_code' => ['nullable', 'string', 'regex:/^[0-9]{2}$/D'],
            'phone_ddd' => ['nullable', 'string', 'regex:/^[0-9]{2}$/D'],
            'phone' => ['nullable', 'string', 'regex:/^[0-9]{8,9}$/D'],
            'phone_other' => ['nullable', 'string', 'regex:/^[0-9]{8,9}$/D'],
            'email' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.@]+$/D'],
            'regional_body_code' => ['nullable', 'string', 'regex:/^[A-Z0-9]{1,5}$/D'],
            'location_zone' => ['required', Rule::in([1, 2])],
            'differentiated_location' => ['required', Rule::in([1, 2, 3, 7, 8])],
            'administrative_dependency' => ['required', Rule::in([1, 2, 3, 4])],
            'regulation' => ['nullable', Rule::in([0, 1, 2])],
            'regulation_sphere' => ['nullable', Rule::in([1, 2, 3, 4, 6])],
            'linked_unit' => ['nullable', Rule::in([0, 1, 2])],
            'host_school_code' => ['nullable', 'string', 'regex:/^[0-9]{8}$/D'],
            'ies_code' => ['nullable', 'string', 'regex:/^[0-9]{1,9}$/D'],
        ], [
            'operating_status.required' => 'Informe a situação de funcionamento.',
            'location_zone.required' => 'Informe a zona da escola (urbana ou rural).',
            'differentiated_location.required' => 'Informe a localização diferenciada.',
            'administrative_dependency.required' => 'Informe a dependência administrativa.',
            'district_code.regex' => 'O código de distrito deve ter dois dígitos.',
            'phone_ddd.regex' => 'O DDD deve ter dois dígitos.',
            'phone.regex' => 'O telefone deve ter 8 ou 9 dígitos.',
            'phone_other.regex' => 'O outro telefone deve ter 8 ou 9 dígitos.',
            'email.regex' => 'Use apenas os caracteres permitidos na coluna do leiaute do Inep: letras, números, ponto, sublinhado e arroba.',
            'regional_body_code.regex' => 'Escolha um órgão regional da lista.',
            'host_school_code.regex' => 'O código da escola sede deve ter 8 dígitos.',
            'ies_code.regex' => 'O código da IES deve ter até 9 dígitos.',
        ]);

        $ano = Tabelas::anoMaisRecente();
        DB::connection('tenant')->transaction(function () use ($school, $data, $request, $ano): void {
            // Bloqueia a escola também no primeiro preenchimento, quando ainda não há linha do Censo.
            $school = School::query()->lockForUpdate()->findOrFail($school->id);
            $record = SchoolCensus::query()->find($school->id)
                ?? new SchoolCensus(['school_id' => $school->id]);
            $created = ! $record->exists;
            $values = array_replace(array_fill_keys(SchoolCensus::FIELDS, null), $data);
            $record->fill($values);
            $year = SchoolYear::query()->where('year', $ano)->first();
            $registro = Registro00::para($school, $record, $year, $ano);
            $errors = [];
            foreach ($registro->pendencias as $pendencia) {
                $campo = $pendencia->campo;
                if (! $pendencia->recusa() || ! isset(Registro00::CAMPOS[$campo])) {
                    continue;
                }
                // A escola fictícia pode ter município fora da tabela. O distrito
                // em branco fica como pendência, nunca se inventa um código.
                if ($campo === 9 && $registro->valores[8] === null && $record->district_code === null) {
                    continue;
                }
                // A dependência privada é um dado da escola, mas não está pronta
                // para o arquivo enquanto os campos específicos estiverem fora do escopo.
                if ($campo === 21 && $record->administrative_dependency === 4) {
                    continue;
                }
                $errors[Registro00::CAMPOS[$campo]][] = $pendencia->mensagem;
            }
            if ($record->district_code !== null && $registro->valores[8] === null) {
                $errors['district_code'][] = 'Corrija o município no cadastro da escola antes de escolher um distrito.';
            }
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
            $changed = array_keys(array_intersect_key($record->getDirty(), array_flip(SchoolCensus::FIELDS)));
            if ($created || $changed !== []) {
                $record->updated_by = $request->user()?->getAuthIdentifier();
                $record->save();
                app(AuditTrail::class)->record('tenant', $created ? 'school_census.created' : 'school_census.updated',
                    SchoolCensus::class, $school->id, null, ['school_id' => $school->id, 'changed_fields' => $changed]);
            }
        });

        return redirect()->route('school.census.school.edit', ['municipio' => $municipality->slug, 'escola' => $school->id])
            ->with('status', 'Dados da escola para o Censo atualizados. Confira as pendências.')
            ->header('Cache-Control', 'private, no-store');
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
