<?php

namespace App\Http\Controllers;

use App\Attendance\SchoolAbsenceAlerts;
use App\Audit\AuditTrail;
use App\Educacenso\ConferenciaDeTurmas;
use App\Educacenso\Registro20;
use App\Educacenso\SugestaoDeTurma;
use App\Educacenso\Tabelas;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolClassCensus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Dados das turmas para o Censo Escolar (registro 20 do Educacenso). A gestão
 * da escola preenche; o que não fecha com as regras do Inep não é gravado, e o
 * que depende de outros cadastros aparece como pendência na lista.
 */
class ClassCensusController extends Controller
{
    private const FIELDS = [
        'inep_stage_code', 'mediation', 'class_type', 'schedule_sunday', 'schedule_monday', 'schedule_tuesday',
        'schedule_wednesday', 'schedule_thursday', 'schedule_friday', 'schedule_saturday', 'complementary_activities',
        'differentiated_location', 'special_class', 'organization', 'alternance', 'curricular_areas', 'bilingual_deaf',
    ];

    public function index(Request $request, string $municipio, string $escola, ConferenciaDeTurmas $conferencia): Response
    {
        [$municipality, $school] = $this->managedSchool($request, $escola);
        $year = app(SchoolAbsenceAlerts::class)->currentYear();
        $rows = $year === null ? [] : ($conferencia->para(collect([$school]), $year)->get($school->id) ?? []);
        $suggestions = [];
        foreach ($rows as $row) {
            $suggestions[$row['turma']->id] = SugestaoDeTurma::etapa($row['turma']);
        }

        return response()->view('census.classes', [
            'municipality' => $municipality,
            'school' => $school,
            'year' => $year,
            'rows' => $rows,
            'suggestions' => $suggestions,
            'tabelas' => Tabelas::do(Tabelas::anoMaisRecente()),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function edit(Request $request, string $municipio, string $escola, string $turma): Response
    {
        [$municipality, $school] = $this->managedSchool($request, $escola);
        $class = $this->classOf($school, $turma);
        $census = SchoolClassCensus::query()->find($class->id);
        $tabelas = Tabelas::do(Tabelas::anoMaisRecente());

        $suggested = null;
        if ($census === null) {
            $stage = SugestaoDeTurma::etapa($class);
            $type = SugestaoDeTurma::tipo($class);
            $curricular = in_array($type, [6, 9], true);
            $suggested = [
                'inep_stage_code' => $stage,
                'class_type' => $type,
                'curricular_areas' => $stage === null ? [] : SugestaoDeTurma::areas($stage, $tabelas),
                // Respostas mais comuns, só como ponto de partida: a escola confere antes de salvar.
                'organization' => $stage !== null && ! in_array($stage, [1, 2, 3], true) ? 1 : null,
                'special_class' => $curricular ? false : null,
                'alternance' => false,
                'bilingual_deaf' => false,
            ];
        }

        return response()->view('census.class-form', [
            'municipality' => $municipality,
            'school' => $school,
            'class' => $class,
            'census' => $census,
            'suggested' => $suggested,
            'tabelas' => $tabelas,
            'etapas' => array_intersect_key($tabelas->etapas(), array_flip(Registro20::ETAPAS_DA_REDE)),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, string $municipio, string $escola, string $turma): RedirectResponse
    {
        [$municipality, $school] = $this->managedSchool($request, $escola);
        $class = $this->classOf($school, $turma);
        $tabelas = Tabelas::do(Tabelas::anoMaisRecente());
        $values = $this->validated($request, $tabelas);

        DB::connection('tenant')->transaction(function () use ($class, $values, $request): void {
            $census = SchoolClassCensus::query()->lockForUpdate()->find($class->id)
                ?? new SchoolClassCensus(['school_class_id' => $class->id]);
            $this->save($census, $values, $request);
        });

        return redirect()->route('school.census.classes.index', ['municipio' => $municipality->slug, 'escola' => $school->id])
            ->with('status', "Dados do Censo da turma {$class->name} salvos.");
    }

    /** Valores comuns aplicados de uma vez às turmas escolhidas (um só envio). */
    public function batch(Request $request, string $municipio, string $escola): RedirectResponse
    {
        [$municipality, $school] = $this->managedSchool($request, $escola);
        $year = app(SchoolAbsenceAlerts::class)->currentYear();
        $data = $request->validate([
            'turmas' => ['required', 'array', 'min:1'],
            'turmas.*' => ['uuid'],
            'aplicar_etapa_sugerida' => ['nullable', 'boolean'],
            'mediation' => ['nullable', Rule::in(['1'])],
            'class_type' => ['nullable', Rule::in(['6'])],
            'differentiated_location' => ['nullable', Rule::in(['0', '1', '2', '3'])],
            'special_class' => ['nullable', Rule::in(['sim', 'nao'])],
            'alternance' => ['nullable', Rule::in(['nao'])],
            'bilingual_deaf' => ['nullable', Rule::in(['sim', 'nao'])],
            'organization' => ['nullable', Rule::in(['1'])],
            'semana_inicio' => ['nullable', 'required_with:semana_fim', 'date_format:H:i'],
            'semana_fim' => ['nullable', 'required_with:semana_inicio', 'date_format:H:i'],
        ], [
            'turmas.required' => 'Escolha pelo menos uma turma.',
            'semana_inicio.required_with' => 'Informe o início e o fim do horário.',
            'semana_fim.required_with' => 'Informe o início e o fim do horário.',
        ]);
        $week = null;
        if (! empty($data['semana_inicio'])) {
            $week = $this->schedule($data['semana_inicio'], $data['semana_fim'], 'semana_inicio');
        }

        $classes = SchoolClass::query()->where('school_id', $school->id)->where('status', 'active')
            ->when($year, fn ($query) => $query->where('school_year_id', $year->id))
            ->whereIn('id', $data['turmas'])->get();
        if ($classes->count() !== count(array_unique($data['turmas']))) {
            throw ValidationException::withMessages(['turmas' => 'Escolha turmas ativas desta escola.']);
        }

        $updated = DB::connection('tenant')->transaction(function () use ($classes, $data, $week, $request): int {
            $count = 0;
            foreach ($classes as $class) {
                $census = SchoolClassCensus::query()->lockForUpdate()->find($class->id)
                    ?? new SchoolClassCensus(['school_class_id' => $class->id, 'curricular_areas' => [], 'complementary_activities' => []]);
                $values = [];
                if (! empty($data['aplicar_etapa_sugerida']) && $census->inep_stage_code === null && ($stage = SugestaoDeTurma::etapa($class)) !== null) {
                    $values['inep_stage_code'] = $stage;
                    $values['class_type'] = $census->class_type ?? 6;
                }
                foreach (['mediation', 'class_type', 'differentiated_location', 'organization'] as $field) {
                    if (($data[$field] ?? null) !== null) {
                        $values[$field] = (int) $data[$field];
                    }
                }
                foreach (['special_class', 'alternance', 'bilingual_deaf'] as $field) {
                    if (($data[$field] ?? null) !== null) {
                        $values[$field] = $data[$field] === 'sim';
                    }
                }
                // Educação infantil não tem forma de organização (registro 20, campo 28).
                $stage = $values['inep_stage_code'] ?? $census->inep_stage_code;
                if (isset($values['organization']) && $stage !== null && in_array($stage, [1, 2, 3], true)) {
                    unset($values['organization']);
                }
                if ($week !== null) {
                    foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday'] as $day) {
                        $values['schedule_'.$day] = $week;
                    }
                }
                if ($values !== [] && $this->save($census, $values, $request)) {
                    $count++;
                }
            }

            return $count;
        });

        return redirect()->route('school.census.classes.index', ['municipio' => $municipality->slug, 'escola' => $school->id])
            ->with('status', $updated === 0 ? 'Nenhuma turma foi alterada.' : "Valores aplicados a {$updated} ".($updated === 1 ? 'turma' : 'turmas').'. Confira as pendências que restam.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, Tabelas $tabelas): array
    {
        $curricular = in_array($request->input('class_type'), ['6', '9'], true);
        $data = $request->validate([
            'class_type' => ['required', Rule::in(['4', '5', '6', '9'])],
            'mediation' => ['required', Rule::in(['1', '2'])],
            'inep_stage_code' => [Rule::requiredIf($curricular), 'nullable', Rule::prohibitedIf(! $curricular), Rule::in(array_map('strval', Registro20::ETAPAS_DA_REDE))],
            'special_class' => [Rule::requiredIf($curricular), 'nullable', Rule::prohibitedIf(! $curricular), Rule::in(['sim', 'nao'])],
            'differentiated_location' => ['required', Rule::in(['0', '1', '2', '3'])],
            'organization' => ['nullable', Rule::in(['1', '2', '3', '4', '5'])],
            'alternance' => ['required', Rule::in(['sim', 'nao'])],
            'bilingual_deaf' => ['required', Rule::in(['sim', 'nao'])],
            'curricular_areas' => ['nullable', 'array'],
            'curricular_areas.*' => [Rule::in(array_map('strval', array_keys($tabelas->camposDasAreas())))],
            'complementary_activities' => ['nullable', 'array', 'max:6'],
            'complementary_activities.*' => [Rule::in(array_map('strval', array_keys($tabelas->atividadesComplementares())))],
            'horario' => ['nullable', 'array'],
            'horario.*.inicio' => ['nullable', 'date_format:H:i'],
            'horario.*.fim' => ['nullable', 'date_format:H:i'],
        ], [
            'class_type.required' => 'Escolha o tipo de turma.',
            'mediation.required' => 'Escolha o tipo de mediação.',
            'inep_stage_code.required' => 'Escolha a etapa de ensino do Inep.',
            'inep_stage_code.prohibited' => 'Turma de AEE ou de atividade complementar não tem etapa.',
            'special_class.required' => 'Informe se é classe especial.',
            'special_class.prohibited' => 'Classe especial só se aplica a turma curricular.',
            'differentiated_location.required' => 'Informe o local de funcionamento da turma.',
            'alternance.required' => 'Informe se é turma de formação por alternância.',
            'bilingual_deaf.required' => 'Informe se é turma de educação bilíngue de surdos.',
            'complementary_activities.max' => 'São no máximo 6 atividades complementares.',
            'horario.*.inicio.date_format' => 'Use o formato hh:mm no horário.',
            'horario.*.fim.date_format' => 'Use o formato hh:mm no horário.',
        ]);

        $stage = isset($data['inep_stage_code']) ? (int) $data['inep_stage_code'] : null;
        $type = (int) $data['class_type'];
        $mediation = (int) $data['mediation'];
        $areas = array_values(array_unique(array_map('intval', $data['curricular_areas'] ?? [])));
        $activities = array_values(array_unique(array_map('intval', $data['complementary_activities'] ?? [])));
        $organization = isset($data['organization']) ? (int) $data['organization'] : null;
        $errors = [];

        $values = ['schedule_sunday' => null, 'schedule_monday' => null, 'schedule_tuesday' => null, 'schedule_wednesday' => null,
            'schedule_thursday' => null, 'schedule_friday' => null, 'schedule_saturday' => null];
        foreach (array_keys(SchoolClassCensus::DAYS) as $day) {
            $start = $data['horario'][$day]['inicio'] ?? null;
            $end = $data['horario'][$day]['fim'] ?? null;
            if ($start === null && $end === null) {
                continue;
            }
            if ($start === null || $end === null) {
                $errors["horario.{$day}.inicio"] = 'Informe o início e o fim do horário de '.mb_strtolower(SchoolClassCensus::DAYS[$day]).'.';

                continue;
            }
            try {
                $values['schedule_'.$day] = $this->schedule($start, $end, "horario.{$day}.inicio");
            } catch (ValidationException $exception) {
                $errors = [...$errors, ...array_map(fn (array $m): string => $m[0], $exception->errors())];
            }
        }
        $hasSchedule = array_filter($values) !== [];
        if ($mediation === 1 && ! $hasSchedule) {
            $errors['horario'] = 'Turma presencial precisa do horário de pelo menos um dia da semana.';
        }
        if ($mediation !== 1 && $hasSchedule) {
            $errors['horario'] = 'Turma que não é presencial não informa horário.';
        }
        if (! $tabelas->combinacaoPermitida($mediation, $type, $type === 6 || $type === 9 ? $stage : null)) {
            $errors['mediation'] = 'Essa combinação de mediação, tipo de turma e etapa não é aceita pelo Inep.';
        }
        if ((int) $data['differentiated_location'] === 1 && $mediation !== 1) {
            $errors['differentiated_location'] = 'Sala anexa só vale para turma presencial.';
        }
        if ($stage !== null && in_array($stage, [1, 2, 3, 56], true) && in_array((int) $data['differentiated_location'], [2, 3], true)) {
            $errors['differentiated_location'] = 'Educação infantil e multietapa não funcionam em unidade socioeducativa ou prisional.';
        }
        if (in_array($type, [4, 9], true) && $activities === []) {
            $errors['complementary_activities'] = 'Escolha pelo menos uma atividade complementar.';
        }
        if (! in_array($type, [4, 9], true)) {
            $activities = [];
        }
        $usesAreas = $stage !== null && ! in_array($stage, [1, 2, 3], true);
        if ($usesAreas) {
            if ($areas === []) {
                $errors['curricular_areas'] = 'Marque pelo menos uma área do conhecimento ou componente oferecido.';
            }
            if (array_diff($areas, $tabelas->areasPermitidas($stage)) !== []) {
                $errors['curricular_areas'] = 'Há área que o Inep não aceita para esta etapa.';
            }
            if ($organization === null) {
                $errors['organization'] = 'Escolha a forma de organização da turma.';
            } elseif (! in_array($organization, $tabelas->organizacoes($stage), true)) {
                $errors['organization'] = 'Essa forma de organização não é aceita para esta etapa.';
            }
        } else {
            $areas = [];
            $organization = null;
        }
        $alternance = $data['alternance'] === 'sim';
        if ($alternance && ($stage === null || ! $tabelas->alternanciaPermitida($stage))) {
            $errors['alternance'] = 'Formação por alternância não é aceita para esta etapa.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [...$values,
            'inep_stage_code' => $stage,
            'mediation' => $mediation,
            'class_type' => $type,
            'complementary_activities' => $activities,
            'differentiated_location' => (int) $data['differentiated_location'],
            'special_class' => isset($data['special_class']) ? $data['special_class'] === 'sim' : null,
            'organization' => $organization,
            'alternance' => $alternance,
            'curricular_areas' => $areas,
            'bilingual_deaf' => $data['bilingual_deaf'] === 'sim',
        ];
    }

    /** Horário no formato do registro 20 (campos 7 a 13): minutos múltiplos de 5, início antes do fim. */
    private function schedule(string $start, string $end, string $field): string
    {
        foreach ([$start, $end] as $time) {
            if ((int) substr($time, 3, 2) % 5 !== 0) {
                throw ValidationException::withMessages([$field => 'O Censo só aceita minutos múltiplos de 5 (ex.: 07:00, 07:05, 07:30).']);
            }
        }
        if ($start >= $end) {
            throw ValidationException::withMessages([$field => 'O horário de início deve ser antes do fim.']);
        }

        return $start.'-'.$end;
    }

    /** @param  array<string, mixed>  $values */
    private function save(SchoolClassCensus $census, array $values, Request $request): bool
    {
        $created = ! $census->exists;
        $census->fill([...$values, 'updated_by' => $request->user()?->getAuthIdentifier()]);
        $changed = array_values(array_filter(self::FIELDS, fn (string $field): bool => $census->isDirty($field)));
        if (! $created && $changed === []) {
            return false;
        }
        $before = $created ? null : array_intersect_key($census->getOriginal(), array_flip($changed));
        $census->save();
        app(AuditTrail::class)->record('tenant', $created ? 'class_census.created' : 'class_census.updated', SchoolClass::class,
            $census->school_class_id, $before, $census->only($created ? self::FIELDS : $changed));

        return true;
    }

    private function classOf(School $school, string $turma): SchoolClass
    {
        return SchoolClass::query()->where('school_id', $school->id)->where('status', 'active')->findOrFail($turma);
    }

    /** @return array{0: mixed, 1: School} */
    private function managedSchool(Request $request, string $escola): array
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($escola);
        Gate::authorize('manageStudents', [$school, $municipality]);

        return [$municipality, $school];
    }
}
