<?php

namespace App\Http\Controllers;

use App\Address\BrazilianStates;
use App\Audit\AuditTrail;
use App\Educacenso\NomeDePessoa;
use App\Educacenso\Tabelas;
use App\Educacenso\Texto;
use App\Models\Tenant\School;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentCensus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Dados do aluno que só o Censo Escolar pede (registro 30): filiação,
 * municípios e países em código, povo indígena e localização diferenciada.
 * Mesmo acesso do cadastro do aluno; segundo fator para gravar; a auditoria
 * guarda só os nomes dos campos alterados.
 */
class StudentCensusController extends Controller
{
    private const FIELDS = [
        'filiation', 'parent1_name', 'parent2_name', 'indigenous_people_code', 'nationality_country_code',
        'birth_municipality_code', 'residence_country_code', 'residence_municipality_code', 'differentiated_location',
    ];

    public function edit(Request $request, string $municipio, string $escola, string $aluno): Response
    {
        [$municipality, $school, $student] = $this->authorizedStudent($request, $escola, $aluno);
        $tabelas = Tabelas::do(Tabelas::anoMaisRecente());
        $record = StudentCensus::query()->find($student->id);

        return response()->view('census.student-form', [
            'municipality' => $municipality,
            'school' => $school,
            'student' => $student,
            'record' => $record,
            'suggestions' => $this->suggestions($student, $tabelas),
            'birthMunicipality' => $record?->birth_municipality_code ? $tabelas->municipio($record->birth_municipality_code) : null,
            'residenceMunicipality' => $record?->residence_municipality_code ? $tabelas->municipio($record->residence_municipality_code) : null,
            'countries' => $tabelas->paises(),
            'peoples' => $tabelas->povosIndigenas(),
            'states' => BrazilianStates::NAMES,
            'tabelas' => $tabelas,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, string $municipio, string $escola, string $aluno): RedirectResponse
    {
        [$municipality, $school, $student] = $this->authorizedStudent($request, $escola, $aluno);
        $tabelas = Tabelas::do(Tabelas::anoMaisRecente());
        $ufs = array_keys(BrazilianStates::NAMES);

        $data = $request->validate([
            'filiacao' => ['required', Rule::in(['0', '1'])],
            'filiacao_1' => ['nullable', 'string', 'max:100'],
            'filiacao_2' => ['nullable', 'string', 'max:100'],
            'pais_nacionalidade' => ['nullable', 'integer', Rule::in(array_keys($tabelas->paises()))],
            'nascimento_uf' => ['nullable', Rule::in($ufs)],
            'nascimento_municipio' => ['nullable', 'string', 'max:120'],
            'povo_indigena' => ['nullable', 'integer', Rule::in(array_keys($tabelas->povosIndigenas()))],
            'residencia_pais' => ['nullable', 'integer', Rule::in(array_keys(StudentCensus::RESIDENCE_COUNTRIES))],
            'residencia_uf' => ['nullable', Rule::in($ufs)],
            'residencia_municipio' => ['nullable', 'string', 'max:120'],
            'localizacao_diferenciada' => ['nullable', 'integer', Rule::in(array_keys(StudentCensus::DIFFERENTIATED_LOCATIONS))],
        ], [
            'filiacao.required' => 'Informe se a filiação é conhecida.',
            'pais_nacionalidade.in' => 'Escolha o país na lista do Censo.',
            'povo_indigena.in' => 'Escolha o povo indígena na lista do Censo.',
            'residencia_pais.in' => 'Escolha o país de residência na lista.',
            'localizacao_diferenciada.in' => 'Escolha a localização na lista.',
        ]);

        $values = $this->values($data, $student, $tabelas);

        DB::connection('tenant')->transaction(function () use ($student, $values, $request): void {
            $record = StudentCensus::query()->lockForUpdate()->find($student->id)
                ?? new StudentCensus(['student_id' => $student->id]);
            $created = ! $record->exists;
            $record->fill([...$values, 'updated_by' => $request->user()?->getAuthIdentifier()]);
            $changed = array_values(array_filter(self::FIELDS, fn (string $field): bool => $record->isDirty($field)));
            if ($created || $changed !== []) {
                $record->save();
                app(AuditTrail::class)->record('tenant', $created ? 'student_census.created' : 'student_census.updated',
                    StudentCensus::class, $student->id, null, ['student_id' => $student->id, 'changed_fields' => $changed]);
            }
        });

        return redirect()->to(route('school.students.show', ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id]).'#dados-censo')
            ->with('status', 'Dados do Censo atualizados.');
    }

    /**
     * Converte o formulário nos códigos do Educacenso, aplicando as regras do
     * registro 30 que dependem só do próprio aluno.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function values(array $data, Student $student, Tabelas $tabelas): array
    {
        $erros = [];
        $filiacao = (int) $data['filiacao'];
        $pais1 = $filiacao === 1 ? self::nome($data['filiacao_1'] ?? null) : null;
        $pais2 = $filiacao === 1 ? self::nome($data['filiacao_2'] ?? null) : null;
        if ($filiacao === 1 && $pais1 === null && $pais2 === null) {
            $erros['filiacao_1'] = 'Informe pelo menos um nome, como está na certidão.';
        }
        // Regra 1 dos campos 9 e 10: vale para o nome informado sozinho, quando o aluno não tem CPF.
        if ($student->cpf === null) {
            foreach (['filiacao_1' => [$pais1, $pais2], 'filiacao_2' => [$pais2, $pais1]] as $campo => [$nome, $outro]) {
                if ($nome !== null && $outro === null && ($problemas = NomeDePessoa::problemas((string) Texto::normalizar($nome))) !== []) {
                    $erros[$campo] = 'O nome '.implode('; ', $problemas).'.';
                }
            }
        }

        $paisNacionalidade = match ($student->nationality) {
            'brasileira', 'naturalizada' => StudentCensus::BRASIL,
            'estrangeira' => isset($data['pais_nacionalidade']) ? (int) $data['pais_nacionalidade'] : null,
            default => null,
        };
        if ($student->nationality === 'estrangeira' && $paisNacionalidade === StudentCensus::BRASIL) {
            $erros['pais_nacionalidade'] = 'Para aluno estrangeiro, escolha um país diferente do Brasil.';
        }

        // Campo 16: só para nacionalidade brasileira (nascido no Brasil).
        $municipioNascimento = null;
        if ($student->nationality === 'brasileira' && filled($data['nascimento_municipio'] ?? null)) {
            $municipioNascimento = $tabelas->municipioPorNome($data['nascimento_municipio'], $data['nascimento_uf'] ?? null);
            if ($municipioNascimento === null) {
                $erros['nascimento_municipio'] = 'Não encontrei este município na tabela do Censo para a UF escolhida. Confira a grafia e a UF.';
            }
        }

        $povo = $student->race_color === 'indigena' && isset($data['povo_indigena']) ? (int) $data['povo_indigena'] : null;

        $paisResidencia = isset($data['residencia_pais']) ? (int) $data['residencia_pais'] : null;
        $municipioResidencia = null;
        $localizacao = null;
        if ($paisResidencia === StudentCensus::BRASIL) {
            if (filled($data['residencia_municipio'] ?? null)) {
                $municipioResidencia = $tabelas->municipioPorNome($data['residencia_municipio'], $data['residencia_uf'] ?? null);
                if ($municipioResidencia === null) {
                    $erros['residencia_municipio'] = 'Não encontrei este município na tabela do Censo para a UF escolhida. Confira a grafia e a UF.';
                }
            }
            $localizacao = isset($data['localizacao_diferenciada']) ? (int) $data['localizacao_diferenciada'] : null;
            if ($localizacao === 1 && $student->residence_zone === 'urban') {
                $erros['localizacao_diferenciada'] = 'Área de assentamento não combina com zona urbana. Confira a zona de residência no endereço.';
            }
        }

        if ($erros !== []) {
            throw ValidationException::withMessages($erros);
        }

        return [
            'filiation' => $filiacao,
            'parent1_name' => $pais1,
            'parent2_name' => $pais2,
            'indigenous_people_code' => $povo,
            'nationality_country_code' => $paisNacionalidade,
            'birth_municipality_code' => $municipioNascimento,
            'residence_country_code' => $paisResidencia,
            'residence_municipality_code' => $municipioResidencia,
            'differentiated_location' => $localizacao,
        ];
    }

    /**
     * Sugestões a partir do que a escola já cadastrou. Responsável cadastrado
     * como mãe ou pai é só sugestão: filiação é dado da certidão.
     *
     * @return array<string, mixed>
     */
    private function suggestions(Student $student, Tabelas $tabelas): array
    {
        $links = DB::connection('tenant')->table('student_guardians')
            ->join('guardians', 'guardians.id', '=', 'student_guardians.guardian_id')
            ->where('student_guardians.student_id', $student->id)
            ->whereIn('student_guardians.relationship', ['mother', 'father'])
            ->pluck('guardians.full_name', 'student_guardians.relationship');

        $nascimento = $student->nationality === 'brasileira' ? $tabelas->municipioPorNome($student->birthplace_city, $student->birthplace_state) : null;
        $residencia = $tabelas->municipioPorNome($student->address_city, $student->address_state);

        return [
            'mother' => $links['mother'] ?? null,
            'father' => $links['father'] ?? null,
            'birth' => $nascimento ? $tabelas->municipio($nascimento) : null,
            'residence' => $residencia ? $tabelas->municipio($residencia) : null,
        ];
    }

    private static function nome(?string $valor): ?string
    {
        $valor = trim((string) preg_replace('/\s+/u', ' ', (string) $valor));

        return $valor === '' ? null : mb_substr($valor, 0, 100);
    }

    /** @return array{0: mixed, 1: School, 2: Student} */
    private function authorizedStudent(Request $request, string $escola, string $aluno): array
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($escola);
        Gate::authorize('manageStudents', [$school, $municipality]);

        $student = Student::query()->whereHas('enrollments', fn ($query) => $query->where('school_id', $school->id)->where('status', 'active'))->find($aluno);
        abort_unless($student, 403, 'Este aluno não tem matrícula ativa nesta escola.');

        return [$municipality, $school, $student];
    }
}
