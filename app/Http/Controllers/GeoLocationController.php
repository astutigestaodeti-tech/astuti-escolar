<?php

namespace App\Http\Controllers;

use App\Geo\Geocoder;
use App\Models\Tenant\School;
use App\Models\Tenant\Student;
use Illuminate\Contracts\View\View;
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
        $suggestion = $this->suggestion($request, $geocoder, null);

        return view('geo.edit', [
            'municipality' => $municipality,
            'school' => $school,
            'subject' => $school,
            'subjectName' => $school->name,
            'kind' => 'escola',
            'addressText' => '',
            'suggestion' => $suggestion,
            'saveUrl' => route('schools.geo.update', ['municipio' => $municipality->slug, 'escola' => $school->id]),
            'backUrl' => route('schools.show', ['municipio' => $municipality->slug, 'escola' => $school->id]),
        ]);
    }

    public function updateSchool(Request $request, string $municipio, string $escola): RedirectResponse
    {
        [$municipality, $school] = $this->school($request, $escola);
        $this->store($request, $school);

        return redirect()->route('schools.show', ['municipio' => $municipality->slug, 'escola' => $school->id])
            ->with('status', $school->latitude === null ? 'Localização da escola removida.' : 'Localização da escola salva.');
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
    private function suggestion(Request $request, Geocoder $geocoder, ?Student $fromAddress): ?array
    {
        $query = trim((string) $request->query('procurar', ''));
        if ($query !== '') {
            $query = mb_substr($query, 0, 200);
            $point = $geocoder->locate($query, null, null, null, null, null);

            return $point === null ? ['lat' => 0.0, 'lon' => 0.0, 'found' => false, 'query' => $query] : [...$point, 'found' => true, 'query' => $query];
        }
        if ($fromAddress !== null && $fromAddress->address_city !== null) {
            $point = $geocoder->locate($fromAddress->address_street, $fromAddress->address_number, $fromAddress->address_district, $fromAddress->address_city, $fromAddress->address_state, $fromAddress->postal_code);

            return $point === null ? null : [...$point, 'found' => true, 'query' => $this->addressText($fromAddress)];
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
