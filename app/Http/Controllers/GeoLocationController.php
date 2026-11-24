<?php

namespace App\Http\Controllers;

use App\Address\BrazilianStates;
use App\Geo\Coordinates;
use App\Geo\Geocoder;
use App\Models\Tenant\School;
use App\Models\Tenant\Student;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Localização no mapa da residência do aluno e da escola. Só quem gere a
 * escola vê e altera as coordenadas; os demais veem apenas a distância.
 */
class GeoLocationController extends Controller
{
    public function editStudent(Request $request, string $municipio, string $escola, string $aluno, Geocoder $geocoder): View
    {
        [$municipality, $school] = $this->school($request, $escola);
        $student = $this->student($school, $aluno);
        $suggestion = $this->suggestion($request, $geocoder, $student->latitude === null ? $student : null);

        return view('geo.edit', [
            'municipality' => $municipality,
            'school' => $school,
            'subject' => $student,
            'subjectName' => $student->full_name,
            'kind' => 'aluno',
            'addressText' => $this->addressText($student),
            'suggestion' => $suggestion,
            'saveUrl' => route('school.students.geo.update', ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id]),
            'backUrl' => route('school.students.show', ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id]),
        ]);
    }

    public function updateStudent(Request $request, string $municipio, string $escola, string $aluno): RedirectResponse
    {
        [$municipality, $school] = $this->school($request, $escola);
        $student = $this->student($school, $aluno);
        $this->store($request, $student);

        return redirect()->route('school.students.show', ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id])
            ->with('status', $student->latitude === null ? 'Localização removida.' : 'Localização da residência salva.');
    }

    public function editSchool(Request $request, string $municipio, string $escola, Geocoder $geocoder): View
    {
        [$municipality, $school] = $this->school($request, $escola);
        // Sem ponto salvo, o endereço cadastrado já posiciona o marcador.
        $suggestion = $this->suggestion($request, $geocoder, $school->latitude === null ? $school : null);

        return view('geo.edit', [
            'municipality' => $municipality,
            'school' => $school,
            'subject' => $school,
            'subjectName' => $school->name,
            'kind' => 'escola',
            'addressText' => $school->addressLine(),
            'suggestion' => $suggestion,
            'reverseUrl' => route('schools.geo.reverse', ['municipio' => $municipality->slug, 'escola' => $school->id]),
            'saveUrl' => route('schools.geo.update', ['municipio' => $municipality->slug, 'escola' => $school->id]),
            'backUrl' => route('schools.show', ['municipio' => $municipality->slug, 'escola' => $school->id]),
        ]);
    }

    public function updateSchool(Request $request, string $municipio, string $escola): RedirectResponse
    {
        [$municipality, $school] = $this->school($request, $escola);
        if ($request->boolean('remover')) {
            $this->store($request, $school);

            return redirect()->route('schools.show', ['municipio' => $municipality->slug, 'escola' => $school->id])
                ->with('status', 'Localização da escola removida.');
        }

        // Endereço e ponto na mesma gravação: um botão só.
        $this->saveSchoolAddress($request, $school);
        if (trim((string) $request->input('latitude')) === '') {
            $params = ['municipio' => $municipality->slug, 'escola' => $school->id];
            if ($school->wasChanged(School::ADDRESS_FIELDS) && $school->addressLine() !== '') {
                // Endereço novo sem ponto: volta ao mapa já procurando o endereço.
                return redirect()->route('schools.geo.edit', [...$params, 'procurar' => $school->addressLine()])
                    ->with('status', 'Endereço salvo. Confira o ponto no mapa e salve de novo.');
            }
        }
        $this->store($request, $school);

        return redirect()->route('schools.show', ['municipio' => $municipality->slug, 'escola' => $school->id])
            ->with('status', 'Endereço e localização da escola salvos.');
    }

    /** Endereço sugerido para o ponto marcado no mapa (a pessoa confere e salva). */
    public function reverseSchool(Request $request, string $municipio, string $escola, Geocoder $geocoder): JsonResponse
    {
        $this->school($request, $escola);
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-34,6'],
            'lon' => ['required', 'numeric', 'between:-74,-28'],
        ]);
        $address = $geocoder->reverse((float) $data['lat'], (float) $data['lon']);

        return $address === null
            ? response()->json(['message' => 'Não encontrei o endereço deste ponto. Preencha pelo CEP.'], 404)
            : response()->json($address);
    }

    private function saveSchoolAddress(Request $request, School $school): void
    {
        $fields = School::ADDRESS_FIELDS;
        foreach ($fields as $field) {
            $value = $request->input($field);
            $request->merge([$field => is_string($value) ? (preg_replace('/\s+/u', ' ', trim($value)) ?: null) : $value]);
        }
        $data = $request->validate([
            'postal_code' => ['nullable', 'regex:/\A\d{5}-?\d{3}\z/'],
            'address_street' => ['nullable', 'string', 'max:180'],
            'address_number' => ['nullable', 'string', 'max:20'],
            'address_complement' => ['nullable', 'string', 'max:180'],
            'address_district' => ['nullable', 'string', 'max:120'],
            'address_city' => ['nullable', 'string', 'max:120'],
            'address_state' => ['nullable', 'string', 'size:2', BrazilianStates::VALIDATION_RULE],
        ], [
            'postal_code.regex' => 'Informe o CEP com oito números (00000-000).',
            'address_state.in' => 'Selecione uma UF válida.',
            'address_state.size' => 'Selecione uma UF válida.',
        ]);
        if (($data['postal_code'] ?? null) !== null && ! str_contains($data['postal_code'], '-')) {
            $data['postal_code'] = substr($data['postal_code'], 0, 5).'-'.substr($data['postal_code'], 5);
        }
        $school->fill(array_intersect_key($data, array_flip($fields)));
        if ($school->isDirty($fields)) {
            $school->save();
        }
    }

    /** @return array{0: mixed, 1: School} */
    private function school(Request $request, string $escola): array
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($escola);
        Gate::authorize('manageStudents', [$school, $municipality]);

        return [$municipality, $school];
    }

    private function student(School $school, string $aluno): Student
    {
        return Student::query()->whereHas('enrollments', fn ($enrollments) => $enrollments->where('school_id', $school->id)->where('status', 'active'))->findOrFail($aluno);
    }

    /**
     * Sugestão de ponto: o texto procurado pela pessoa ou, na primeira vez,
     * o endereço do cadastro. Nada é salvo sem o botão "Salvar".
     *
     * @return array{lat: float, lon: float, found: bool, query: string}|null
     */
    private function suggestion(Request $request, Geocoder $geocoder, Student|School|null $fromAddress): ?array
    {
        $query = trim((string) $request->query('procurar', ''));
        if ($query !== '') {
            // Coordenadas ou link do Google Maps vão direto para o ponto, sem procurar endereço.
            $coordinates = app(Coordinates::class);
            $point = $coordinates->parse($query);
            if ($point !== null) {
                return [...$point, 'found' => true, 'query' => mb_substr($query, 0, 200), 'source' => 'mapa'];
            }
            if ($coordinates->looksLikeMapLink($query)) {
                return ['lat' => 0.0, 'lon' => 0.0, 'found' => false, 'query' => mb_substr($query, 0, 200), 'link' => true];
            }
            $query = mb_substr($query, 0, 200);
            $point = $geocoder->search($query);

            return $point === null ? ['lat' => 0.0, 'lon' => 0.0, 'found' => false, 'query' => $query] : [...$point, 'found' => true, 'query' => $query];
        }
        if ($fromAddress !== null && $fromAddress->address_city !== null) {
            $point = $geocoder->locate($fromAddress->address_street, $fromAddress->address_number, $fromAddress->address_district, $fromAddress->address_city, $fromAddress->address_state, $fromAddress->postal_code);

            return $point === null ? null : [...$point, 'found' => true, 'query' => $fromAddress instanceof School ? $fromAddress->addressLine() : $this->addressText($fromAddress)];
        }

        return null;
    }

    private function store(Request $request, Student|School $subject): void
    {
        if ($request->boolean('remover')) {
            $subject->forceFill(['latitude' => null, 'longitude' => null, 'geo_source' => null, 'geo_updated_at' => null])->save();

            return;
        }

        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-34,6'],
            'longitude' => ['required', 'numeric', 'between:-74,-28'],
            'geo_source' => ['required', 'in:endereco,mapa'],
        ], [
            'latitude.required' => 'Marque o local no mapa antes de salvar.',
            'longitude.required' => 'Marque o local no mapa antes de salvar.',
            'latitude.between' => 'O ponto marcado fica fora do Brasil. Marque de novo no mapa.',
            'longitude.between' => 'O ponto marcado fica fora do Brasil. Marque de novo no mapa.',
        ]);

        $subject->forceFill([
            'latitude' => round((float) $data['latitude'], 6),
            'longitude' => round((float) $data['longitude'], 6),
            'geo_source' => $data['geo_source'],
            'geo_updated_at' => now(),
        ])->save();
    }

    private function addressText(Student $student): string
    {
        return implode(', ', array_filter([
            trim(implode(' ', array_filter([$student->address_street, $student->address_number]))) ?: null,
            $student->address_district, $student->address_city, $student->address_state, $student->postal_code,
        ]));
    }
}
