<?php

namespace App\Equity;

use App\Models\Tenant\SchoolYear;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Consulta em lote, uma só passagem pelas matrículas da rede. Nenhuma
 * informação individual de raça, sexo ou zona sai desta classe.
 *
 * Os indicadores são sinais escolares, não o cálculo oficial do VAAR/Inep.
 */
class EquityReadiness
{
    public const MIN_GROUP = 5;

    public const DIMENSIONS = [
        'raca' => 'Cor/raça',
        'sexo' => 'Sexo',
        'zona' => 'Zona de residência',
    ];

    private const RACE_LABELS = [
        'preta' => 'Preta',
        'parda' => 'Parda',
        'indigena' => 'Indígena',
        'branca' => 'Branca',
        'amarela' => 'Amarela',
        'nao_declarada' => 'Não declarada',
        'sem_informacao' => 'Não informada',
    ];

    private const SEX_LABELS = [
        'female' => 'Feminino',
        'male' => 'Masculino',
        'sem_informacao' => 'Não informado',
    ];

    private const ZONE_LABELS = [
        'urban' => 'Urbana',
        'rural' => 'Rural',
        'sem_informacao' => 'Não informada',
    ];

    public const CONDITIONS = [
        1 => 'Seleção de gestores por critérios técnicos de mérito e desempenho',
        2 => 'Participação de pelo menos 80% dos estudantes avaliados no Saeb',
        3 => 'Redução das desigualdades socioeconômicas e raciais aferidas no Saeb',
        4 => 'Regime de colaboração e ICMS Educacional conforme legislação estadual',
        5 => 'Referenciais curriculares alinhados à BNCC, aprovados pelo sistema de ensino',
    ];

    public const STATUSES = [
        'pendente' => 'Pendente',
        'a_comprovar' => 'A comprovar',
        'cumprida' => 'Cumprida (registro interno)',
    ];

    /**
     * @return array{summary: array, groups: array, schools: Collection, classes: Collection, schoolSummaries: Collection, classSummaries: Collection, saeb: Collection, year: SchoolYear}
     */
    public function forYear(SchoolYear $year, ?string $schoolId = null, ?string $classId = null): array
    {
        $schools = DB::connection('tenant')->table('schools')
            ->where('status', 'active')->whereNull('deleted_at')
            ->when($schoolId, fn ($q, string $id) => $q->where('id', $id))
            ->select('id', 'name')->orderBy('name')->get();

        $classes = DB::connection('tenant')->table('school_classes')
            ->where('school_year_id', $year->id)->whereNull('deleted_at')
            ->whereIn('school_id', $schools->pluck('id'))
            ->when($classId, fn ($q, string $id) => $q->where('id', $id))
            ->select('id', 'school_id', 'name', 'stage')
            ->orderBy('name')->get();

        $rows = DB::connection('tenant')->table('student_enrollments as e')
            ->join('students as s', 's.id', '=', 'e.student_id')
            ->join('school_classes as c', 'c.id', '=', 'e.school_class_id')
            ->where('e.school_year_id', $year->id)
            ->whereIn('e.status', ['active', 'completed'])
            ->whereIn('e.school_id', $schools->pluck('id'))
            ->whereIn('e.school_class_id', $classes->pluck('id'))
            ->whereNull('s.deleted_at')->whereNull('c.deleted_at')
            ->select([
                'e.id as enrollment_id', 'e.student_id', 'e.school_id', 'e.school_class_id', 'e.status',
                's.full_name', 's.race_color', 's.sex', 's.residence_zone',
            ])->get();

        $ids = $rows->pluck('enrollment_id');
        $studentIds = $rows->pluck('student_id')->unique();
        $averages = $ids->isEmpty() ? collect() : DB::connection('tenant')->table('grade_entries')
            ->whereIn('student_enrollment_id', $ids)
            ->whereNotNull('grade')
            ->groupBy('student_enrollment_id', 'curricular_component_id')
            ->select('student_enrollment_id', 'curricular_component_id')
            ->selectRaw('avg(grade) as component_mean')
            ->get()
            ->groupBy('student_enrollment_id')
            ->map(fn (Collection $entries): float => round($entries->avg('component_mean'), 1));

        $attendance = $studentIds->isEmpty() ? collect() : DB::connection('tenant')
            ->table('attendance_marks as mark')
            ->join('attendance_sessions as session', 'session.id', '=', 'mark.attendance_session_id')
            ->join('school_classes as room', 'room.id', '=', 'session.school_class_id')
            ->where('room.school_year_id', $year->id)
            ->whereIn('room.id', $classes->pluck('id'))
            ->whereIn('mark.student_id', $studentIds)
            ->whereBetween('session.attendance_date', [
                $year->start_date->toDateString(),
                min(today()->toDateString(), $year->end_date->toDateString()),
            ])
            ->groupBy('room.id', 'mark.student_id')
            ->select('room.id as class_id', 'mark.student_id')
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when mark.status = 'present' then 1 else 0 end) as present")
            ->get()->keyBy(fn ($item): string => $item->class_id.'|'.$item->student_id);

        $results = $ids->isEmpty() ? collect() : DB::connection('tenant')->table('student_final_results')
            ->whereIn('student_enrollment_id', $ids)
            ->select('student_enrollment_id', 'result')
            ->get()->keyBy('student_enrollment_id');

        $students = $rows->map(function ($row) use ($averages, $attendance, $results): array {
            $mark = $attendance->get($row->school_class_id.'|'.$row->student_id);
            $total = (int) ($mark?->total ?? 0);
            $present = (int) ($mark?->present ?? 0);

            return [
                'school_id' => $row->school_id,
                'class_id' => $row->school_class_id,
                'student_id' => $row->student_id,
                'name' => $row->full_name,
                'status' => $row->status,
                'race' => self::raceKey($row->race_color),
                'sex' => array_key_exists((string) $row->sex, self::SEX_LABELS) && $row->sex !== null
                    ? $row->sex : 'sem_informacao',
                'zone' => array_key_exists((string) $row->residence_zone, self::ZONE_LABELS) && $row->residence_zone !== null
                    ? $row->residence_zone : 'sem_informacao',
                'attendance' => $total > 0 ? round(100 * $present / $total, 1) : null,
                'absences' => $total - $present,
                'sessions' => $total,
                'average' => $averages->get($row->enrollment_id),
                'final' => $results->get($row->enrollment_id)?->result,
            ];
        });

        // Nenhuma linha individual, em especial raça, sexo e zona, vai para a view.
        $selected = $students
            ->when($schoolId, fn (Collection $items, string $id) => $items->where('school_id', $id))
            ->when($classId, fn (Collection $items, string $id) => $items->where('class_id', $id));

        $groups = $this->dimensions($selected);
        $summary = $this->metrics($selected);

        $schoolSummaries = $schools->map(fn ($school): array => [
            'id' => $school->id,
            'name' => $school->name,
            'metrics' => $this->metrics($students->where('school_id', $school->id)),
        ]);
        $classSummaries = $classes->when($schoolId, fn (Collection $items, string $id) => $items->where('school_id', $id))
            ->map(fn ($class): array => [
                'id' => $class->id, 'name' => $class->name, 'stage' => $class->stage,
                'metrics' => $this->metrics($students->where('class_id', $class->id)),
            ])->values();

        // Lista nominal exclusiva de acompanhamento de faltas. Ela não recebe
        // nenhum campo sensível e não tem vínculo visual com linhas por raça.
        $classNames = $classes->keyBy('id');
        $schoolNames = $schools->keyBy('id');
        $saeb = $selected->filter(function (array $row) use ($classNames): bool {
            $class = $classNames->get($row['class_id']);
            $label = Str::lower(Str::ascii((string) ($class?->name ?? '')));

            return $row['status'] === 'active'
                && preg_match('/\b(2|5|9)\s*o?\s*ano\b/', $label) === 1
                && $row['absences'] > 0;
        })->sortByDesc('absences')->take(50)->values()->map(function (array $row) use ($classNames, $schoolNames): array {
            return [
                'name' => $row['name'],
                'school' => $schoolNames->get($row['school_id'])?->name,
                'class' => $classNames->get($row['class_id'])?->name,
                'absences' => $row['absences'],
                'attendance' => $row['attendance'],
            ];
        });

        return compact('summary', 'groups', 'schools', 'classes', 'schoolSummaries', 'classSummaries', 'saeb', 'year');
    }

    /** @return array<string, array{label: string, rows: list<array>}> */
    private function dimensions(Collection $students): array
    {
        $dimensions = [];
        foreach ([
            'raca' => ['field' => 'race', 'labels' => self::RACE_LABELS],
            'sexo' => ['field' => 'sex', 'labels' => self::SEX_LABELS],
            'zona' => ['field' => 'zone', 'labels' => self::ZONE_LABELS],
        ] as $code => $definition) {
            $buckets = [];
            foreach ($definition['labels'] as $key => $label) {
                $members = $students->where($definition['field'], $key);
                if ($members->isNotEmpty()) {
                    $buckets[$key] = ['label' => $label, 'members' => $members];
                }
            }

            $small = collect($buckets)->contains(fn (array $bucket): bool => $bucket['members']->count() < self::MIN_GROUP);
            // Supressão complementar impede recuperar uma célula pequena
            // por subtração do total e das demais categorias.
            $complement = null;
            if ($small) {
                $eligible = collect($buckets)->filter(fn (array $bucket): bool => $bucket['members']->count() >= self::MIN_GROUP)
                    ->sortByDesc(fn (array $bucket): int => $bucket['members']->count());
                $complement = $eligible->keys()->first();
            }

            $items = [];
            foreach ($buckets as $key => $bucket) {
                $hidden = $bucket['members']->count() < self::MIN_GROUP || $key === $complement;
                $items[] = [
                    'label' => $bucket['label'],
                    'suppressed' => $hidden,
                    'metrics' => $hidden ? null : $this->metrics($bucket['members']),
                ];
            }

            if ($code === 'raca') {
                $protected = $students->filter(fn (array $row): bool => in_array($row['race'], ['preta', 'parda', 'indigena'], true));
                // Não publicar a soma quando permitiria inferir uma categoria
                // suprimida pelo grupo combinado ou pelo complemento.
                if ($protected->isNotEmpty()) {
                    $items[] = [
                        'label' => 'Preta + parda + indígena',
                        'suppressed' => $protected->count() < self::MIN_GROUP || $small,
                        'metrics' => $protected->count() < self::MIN_GROUP || $small ? null : $this->metrics($protected),
                    ];
                }
            }

            $dimensions[$code] = ['label' => $definition['field'] === 'race' ? 'Cor/raça' : self::DIMENSIONS[$code], 'rows' => $items];
        }

        return $dimensions;
    }

    private function metrics(Collection $students): array
    {
        $n = $students->count();
        if ($n < self::MIN_GROUP) {
            return ['small' => true, 'count' => null, 'attendance' => null, 'average' => null, 'retained' => null];
        }

        $attendance = $students->whereNotNull('attendance')->pluck('attendance');
        $averages = $students->whereNotNull('average')->pluck('average');
        $finals = $students->whereNotNull('final');

        // Uma média calculada a partir de menos de 5 crianças também é
        // suprimida, mesmo quando a categoria tem 5 ou mais matriculados.
        $retained = $finals->where('final', 'retido')->count();

        return [
            'small' => false,
            'count' => $n,
            'attendance' => $attendance->count() >= self::MIN_GROUP ? round($attendance->avg(), 1) : null,
            'average' => $averages->count() >= self::MIN_GROUP ? round($averages->avg(), 1) : null,
            'retained' => $finals->count() >= self::MIN_GROUP && ($retained === 0 || $retained >= self::MIN_GROUP)
                ? $retained : null,
        ];
    }

    private static function raceKey(?string $race): string
    {
        return $race !== null && array_key_exists($race, self::RACE_LABELS) ? $race : 'sem_informacao';
    }
}
