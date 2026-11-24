<?php

namespace App\Http\Controllers;

use App\Address\BrazilianStates;
use App\Audit\AuditTrail;
use App\Educacenso\NomeDePessoa;
use App\Educacenso\Tabelas;
use App\Educacenso\Texto;
use App\Models\Tenant\School;
use App\Models\Tenant\StaffCensus;
use App\Models\Tenant\StaffMember;
use App\Models\Tenant\StudentCensus;
use App\Models\Tenant\StudentSpecialEducation;
use App\Validation\Cpf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Dados do servidor que só o Censo Escolar pede (registro 30 de gestores e
 * profissionais em sala). Quem gerencia a escola edita; segundo fator para
 * abrir e gravar; a auditoria guarda só os nomes dos campos alterados,
 * porque há CPF e deficiência.
 */
class StaffCensusController extends Controller
{
    private const FIELDS = [
        'cpf', 'birth_date', 'filiation', 'parent1_name', 'parent2_name', 'sex', 'race_color', 'indigenous_people_code',
        'nationality', 'nationality_country_code', 'birth_municipality_code', 'conditions', 'education_level',
        'high_school_type', 'higher_courses', 'pedagogical_areas', 'postgraduates', 'no_postgraduate', 'continuing_education',
    ];

    public function edit(Request $request, string $municipio, string $escola, string $servidor): Response
    {
        [$municipality, $school, $member] = $this->authorizedMember($request, $escola, $servidor);
        $tabelas = Tabelas::do(Tabelas::anoMaisRecente());
        $record = StaffCensus::query()->find($member->id);

        return response()->view('census.staff-form', [
            'municipality' => $municipality,
            'school' => $school,
            'member' => $member,
            'record' => $record,
            'birthMunicipality' => $record?->birth_municipality_code ? $tabelas->municipio($record->birth_municipality_code) : null,
            'countries' => $tabelas->paises(),
            'peoples' => $tabelas->povosIndigenas(),
            'courses' => $tabelas->cursosSuperiores(),
            'areas' => array_diff_key($tabelas->areasDoConhecimento(), array_flip(StaffCensus::AREAS_NOT_FOR_COMPLEMENT)),
            'postgraduateAreas' => $tabelas->areasDePosGraduacao(),
            'states' => BrazilianStates::NAMES,
            'tabelas' => $tabelas,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, string $municipio, string $escola, string $servidor): RedirectResponse
    {
        [$municipality, $school, $member] = $this->authorizedMember($request, $escola, $servidor);
        $tabelas = Tabelas::do(Tabelas::anoMaisRecente());
        $ano = $tabelas->ano;

        $data = $request->validate([
            'cpf' => ['nullable', 'string', new Cpf],
            'nascimento' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:1920-01-01'],
            'filiacao' => ['required', Rule::in(['0', '1'])],
            'filiacao_1' => ['nullable', 'string', 'max:100'],
            'filiacao_2' => ['nullable', 'string', 'max:100'],
            'sexo' => ['required', Rule::in(array_keys(StudentRecordController::SEX_LABELS))],
            'cor' => ['required', Rule::in(array_keys(StudentRecordController::RACE_LABELS))],
            'povo_indigena' => ['nullable', 'integer', Rule::in(array_keys($tabelas->povosIndigenas()))],
            'nacionalidade' => ['required', Rule::in(array_keys(StudentRecordController::NATIONALITY_LABELS))],
            'pais_nacionalidade' => ['nullable', 'integer', Rule::in(array_keys($tabelas->paises()))],
            'nascimento_uf' => ['nullable', Rule::in(array_keys(BrazilianStates::NAMES))],
            'nascimento_municipio' => ['nullable', 'string', 'max:120'],
            'condicoes' => ['nullable', 'array'],
            'condicoes.*' => [Rule::in(array_keys(StudentSpecialEducation::CONDITIONS))],
            'escolaridade' => ['nullable', 'integer', Rule::in(array_keys(StaffCensus::EDUCATION_LEVELS))],
            'ensino_medio' => ['nullable', 'integer', Rule::in(array_keys(StaffCensus::HIGH_SCHOOL_TYPES))],
            'cursos' => ['nullable', 'array', 'max:'.StaffCensus::MAX_COURSES],
            'cursos.*.curso' => ['nullable', Rule::in(array_keys($tabelas->cursosSuperiores()))],
            'cursos.*.ano' => ['nullable', 'integer', 'between:1940,'.$ano],
            'cursos.*.ies' => ['nullable', 'integer', 'min:1'],
            'complementacao' => ['nullable', 'array', 'max:3'],
            'complementacao.*' => ['nullable', 'integer', Rule::in(array_keys($tabelas->areasDoConhecimento())), Rule::notIn(StaffCensus::AREAS_NOT_FOR_COMPLEMENT)],
            'pos' => ['nullable', 'array', 'max:'.StaffCensus::MAX_POSTGRADUATES],
            'pos.*.tipo' => ['nullable', 'integer', Rule::in(array_keys(StaffCensus::POSTGRADUATE_TYPES))],
            'pos.*.area' => ['nullable', 'integer', Rule::in(array_keys($tabelas->areasDePosGraduacao()))],
            'pos.*.ano' => ['nullable', 'integer', 'between:1940,'.$ano],
            'sem_pos' => ['nullable', 'boolean'],
            'formacao' => ['nullable', 'array'],
            'formacao.*' => [Rule::in([...array_keys(StaffCensus::CONTINUING_EDUCATION), 'nenhum'])],
        ], [
            'filiacao.required' => 'Informe se a filiação é conhecida.',
            'sexo.required' => 'Informe o sexo.',
            'cor.required' => 'Informe a cor/raça (ou "Não declarada").',
            'nacionalidade.required' => 'Informe a nacionalidade.',
            'nascimento.date_format' => 'Informe a data de nascimento.',
            'nascimento.before_or_equal' => 'A data de nascimento não pode estar no futuro.',
            'cursos.*.curso.in' => 'Escolha o curso na lista do Censo.',
            'cursos.*.ano.between' => "O ano de conclusão do curso deve ficar entre 1940 e {$ano}.",
            'pos.*.ano.between' => "O ano de conclusão da pós-graduação deve ficar entre 1940 e {$ano}.",
            'complementacao.*.not_in' => 'Estágio supervisionado e "outras áreas" não valem como complementação pedagógica.',
        ]);

        $values = $this->values($data, $member, $tabelas);

        DB::connection('tenant')->transaction(function () use ($member, $school, $values, $request): void {
            if ($values['cpf'] !== null && StaffCensus::query()->where('cpf', $values['cpf'])->where('staff_member_id', '<>', $member->id)
                ->whereIn('staff_member_id', StaffMember::query()->where('school_id', $school->id)->select('id'))->exists()) {
                throw ValidationException::withMessages(['cpf' => 'Já existe outro servidor desta escola com este CPF.']);
            }
            $record = StaffCensus::query()->lockForUpdate()->find($member->id)
                ?? new StaffCensus(['staff_member_id' => $member->id]);
            $created = ! $record->exists;
            $record->fill([...$values, 'updated_by' => $request->user()?->getAuthIdentifier()]);
            $changed = array_values(array_filter(self::FIELDS, fn (string $field): bool => $record->isDirty($field)));
            if ($created || $changed !== []) {
                $record->save();
                app(AuditTrail::class)->record('tenant', $created ? 'staff_census.created' : 'staff_census.updated',
                    StaffCensus::class, $member->id, null, ['staff_member_id' => $member->id, 'changed_fields' => $changed]);
            }
        });

        return redirect()->route('school.staff.index', ['municipio' => $municipality->slug, 'escola' => $school->id])
            ->with('status', 'Dados do Censo de '.$member->full_name.' atualizados.');
    }

    /**
     * Converte o formulário nos valores gravados, aplicando as regras do
     * registro 30 que dependem só do próprio servidor.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function values(array $data, StaffMember $member, Tabelas $tabelas): array
    {
        $erros = [];
        $cpf = filled($data['cpf'] ?? null) ? preg_replace('/\D/', '', (string) $data['cpf']) : null;

        $filiacao = (int) $data['filiacao'];
        $pais1 = $filiacao === 1 ? self::nome($data['filiacao_1'] ?? null) : null;
        $pais2 = $filiacao === 1 ? self::nome($data['filiacao_2'] ?? null) : null;
        if ($filiacao === 1 && $pais1 === null && $pais2 === null) {
            $erros['filiacao_1'] = 'Informe pelo menos um nome, como está no documento.';
        }
        // Regra 1 dos campos 6, 9 e 10: vale para pessoa sem CPF.
        if ($cpf === null) {
            foreach (['filiacao_1' => [$pais1, $pais2], 'filiacao_2' => [$pais2, $pais1]] as $campo => [$nome, $outro]) {
                if ($nome !== null && $outro === null && ($problemas = NomeDePessoa::problemas((string) Texto::normalizar($nome))) !== []) {
                    $erros[$campo] = 'O nome '.implode('; ', $problemas).'.';
                }
            }
        }

        $nacionalidade = $data['nacionalidade'];
        $paisNacionalidade = $nacionalidade === 'estrangeira'
            ? (isset($data['pais_nacionalidade']) ? (int) $data['pais_nacionalidade'] : null)
            : StudentCensus::BRASIL;
        if ($nacionalidade === 'estrangeira' && $paisNacionalidade === StudentCensus::BRASIL) {
            $erros['pais_nacionalidade'] = 'Para estrangeiro, escolha um país diferente do Brasil.';
        }
        $municipioNascimento = null;
        if ($nacionalidade === 'brasileira' && filled($data['nascimento_municipio'] ?? null)) {
            $municipioNascimento = $tabelas->municipioPorNome($data['nascimento_municipio'], $data['nascimento_uf'] ?? null);
            if ($municipioNascimento === null) {
                $erros['nascimento_municipio'] = 'Não encontrei este município na tabela do Censo para a UF escolhida. Confira a grafia e a UF.';
            }
        }
        $povo = $data['cor'] === 'indigena' && isset($data['povo_indigena']) ? (int) $data['povo_indigena'] : null;

        $escolaridade = isset($data['escolaridade']) ? (int) $data['escolaridade'] : null;
        $ensinoMedio = in_array($escolaridade, [StaffCensus::HIGH_SCHOOL, StaffCensus::HIGHER_EDUCATION], true) && isset($data['ensino_medio'])
            ? (int) $data['ensino_medio'] : null;

        $cursos = $areas = null;
        if ($escolaridade === StaffCensus::HIGHER_EDUCATION) {
            $cursos = [];
            foreach (array_values($data['cursos'] ?? []) as $i => $linha) {
                $curso = $linha['curso'] ?? null;
                if (blank($curso) && blank($linha['ano'] ?? null) && blank($linha['ies'] ?? null)) {
                    continue;
                }
                $chave = "cursos.{$i}";
                if (blank($curso) || blank($linha['ano'] ?? null) || blank($linha['ies'] ?? null)) {
                    $erros[$chave.'.curso'] = 'Em cada curso superior, informe curso, ano de conclusão e instituição.';

                    continue;
                }
                if ($tabelas->ies((int) $linha['ies']) === null) {
                    $erros[$chave.'.ies'] = 'Código de instituição '.$linha['ies'].' não está na Tabela de IES do Inep.';
                }
                if (in_array($curso, array_column($cursos, 'curso'), true)) {
                    $erros[$chave.'.curso'] = 'O mesmo curso foi informado duas vezes.';
                }
                if (filled($data['nascimento'] ?? null) && (int) $linha['ano'] <= (int) substr((string) $data['nascimento'], 0, 4)) {
                    $erros[$chave.'.ano'] = 'O ano de conclusão do curso deve ser depois do ano de nascimento.';
                }
                // Chaves na ordem em que o jsonb as guarda, para a comparação de alterações não acusar diferença falsa.
                $cursos[] = ['ano' => (int) $linha['ano'], 'ies' => (int) $linha['ies'], 'curso' => (string) $curso];
            }
            $areas = array_values(array_unique(array_map('intval', array_filter($data['complementacao'] ?? [], 'filled'))));
            $cursos = $cursos === [] ? null : $cursos;
            $areas = $areas === [] ? null : $areas;
        }

        $pos = [];
        foreach (array_values($data['pos'] ?? []) as $i => $linha) {
            if (blank($linha['tipo'] ?? null) && blank($linha['area'] ?? null) && blank($linha['ano'] ?? null)) {
                continue;
            }
            if (blank($linha['tipo'] ?? null) || blank($linha['area'] ?? null) || blank($linha['ano'] ?? null)) {
                $erros["pos.{$i}.tipo"] = 'Em cada pós-graduação, informe tipo, área e ano de conclusão.';

                continue;
            }
            if ($cursos !== null && (int) $linha['ano'] < min(array_column($cursos, 'ano'))) {
                $erros["pos.{$i}.ano"] = 'A pós-graduação não pode terminar antes da primeira graduação.';
            }
            $pos[] = ['ano' => (int) $linha['ano'], 'area' => (int) $linha['area'], 'tipo' => (int) $linha['tipo']];
        }
        $semPos = (bool) ($data['sem_pos'] ?? false);
        if ($semPos && $pos !== []) {
            $erros['sem_pos'] = '"Não tem pós-graduação concluída" não combina com pós-graduação informada.';
        }

        $formacao = $data['formacao'] ?? [];
        $continuada = null;
        if (in_array('nenhum', $formacao, true)) {
            if (count($formacao) > 1) {
                $erros['formacao'] = '"Nenhum" não combina com outra formação continuada marcada.';
            }
            $continuada = [];
        } elseif ($formacao !== []) {
            $continuada = array_values(array_intersect(array_keys(StaffCensus::CONTINUING_EDUCATION), $formacao));
        }

        if ($erros !== []) {
            throw ValidationException::withMessages($erros);
        }

        return [
            'cpf' => $cpf,
            'birth_date' => $data['nascimento'] ?? null,
            'filiation' => $filiacao,
            'parent1_name' => $pais1,
            'parent2_name' => $pais2,
            'sex' => $data['sexo'],
            'race_color' => $data['cor'],
            'indigenous_people_code' => $povo,
            'nationality' => $nacionalidade,
            'nationality_country_code' => $paisNacionalidade,
            'birth_municipality_code' => $municipioNascimento,
            'conditions' => array_values(array_intersect(array_keys(StudentSpecialEducation::CONDITIONS), $data['condicoes'] ?? [])),
            'education_level' => $escolaridade,
            'high_school_type' => $ensinoMedio,
            'higher_courses' => $cursos,
            'pedagogical_areas' => $areas,
            'postgraduates' => $pos === [] ? null : $pos,
            'no_postgraduate' => $semPos,
            'continuing_education' => $continuada,
        ];
    }

    private static function nome(?string $valor): ?string
    {
        $valor = preg_replace('/\s+/u', ' ', trim((string) $valor));

        return $valor === '' ? null : mb_substr($valor, 0, 100);
    }

    /** @return array{0: mixed, 1: School, 2: StaffMember} */
    private function authorizedMember(Request $request, string $escola, string $servidor): array
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($escola);
        Gate::authorize('manageStudents', [$school, $municipality]);
        $member = StaffMember::query()->where('school_id', $school->id)->findOrFail($servidor);

        return [$municipality, $school, $member];
    }
}
