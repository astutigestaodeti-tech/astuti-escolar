<?php

namespace App\Census;

use App\Models\Tenant\School;
use App\Models\Tenant\StudentEnrollment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Conferência do Censo Escolar antes do envio: o que, em cada matrícula
 * ativa da rede, pode fazer a Prefeitura receber menos (Fundeb, PNAE, PNATE
 * e salário-educação), e uma estimativa do que as matrículas valem.
 *
 * Uma consulta para a rede inteira. Só leitura; nenhum dado é alterado.
 */
class CensusReadiness
{
    /** Ordem = prioridade: o que tira a matrícula do Censo vem primeiro. */
    public const WARNINGS = [
        'sem_turma' => 'Sem turma: a matrícula não entra no Censo',
        'duplicada' => 'Matrícula ativa em mais de uma escola',
        'etapa' => 'Etapa da turma não reconhecida',
        'turno' => 'Turno da turma não indica se é integral ou parcial',
        'nascimento' => 'Sem data de nascimento',
        'cor_raca' => 'Cor/raça não informada (usada no VAAR)',
        'zona' => 'Zona de residência não informada',
        'transporte_rural' => 'Mora na zona rural e o transporte não foi informado (PNATE)',
        'aee' => 'Educação especial sem a situação do AEE informada',
    ];

    /** Avisos que impedem contar a matrícula na categoria certa. */
    private const UNCOUNTED = ['sem_turma', 'etapa'];

    /**
     * @return array{
     *     rows: Collection<int, array{student_id: string, name: string, school_id: string, school: string, class: ?string, category: ?string, warnings: list<string>}>,
     *     schools: Collection<int, array{school: School, enrollments: int, with_warnings: int}>,
     *     schoolsWithoutInep: Collection<int, School>,
     *     byType: array<string, int>,
     *     categories: list<array{key: string, label: string, count: int, weight: float, weighted: float, fundeb: ?float, pnae: float}>,
     *     totals: array{enrollments: int, with_warnings: int, weighted: float, fundeb: ?float, pnae: float, at_risk_enrollments: int, at_risk_fundeb: ?float, at_risk_pnae: float}
     * }
     */
    public function forNetwork(?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();

        $rows = StudentEnrollment::query()
            ->join('students', 'students.id', '=', 'student_enrollments.student_id')
            ->join('schools', 'schools.id', '=', 'student_enrollments.school_id')
            ->leftJoin('school_classes', 'school_classes.id', '=', 'student_enrollments.school_class_id')
            ->leftJoin('student_special_education', 'student_special_education.student_id', '=', 'students.id')
            ->where('student_enrollments.status', 'active')
            ->whereNull('students.deleted_at')
            ->where('schools.status', 'active')
            ->select([
                'student_enrollments.id as enrollment_id', 'student_enrollments.school_id', 'student_enrollments.school_class_id',
                'student_enrollments.transport_status', 'students.id as student_id', 'students.full_name', 'students.birth_date',
                'students.race_color', 'students.residence_zone', 'schools.name as school_name',
                'school_classes.name as class_name', 'school_classes.stage', 'school_classes.shift',
                'student_special_education.aee_status',
            ])
            ->selectRaw('COALESCE(jsonb_array_length(student_special_education.conditions), 0) AS special_conditions')
            ->get();

        $schoolsPerStudent = $rows->groupBy('student_id')->map(fn (Collection $items): int => $items->pluck('school_id')->unique()->count());

        $result = $rows->map(function (StudentEnrollment $row) use ($schoolsPerStudent, $today): array {
            $warnings = [];
            $category = null;

            if ($row->school_class_id === null) {
                $warnings[] = 'sem_turma';
            } else {
                $age = $row->birth_date === null ? null : CarbonImmutable::parse($row->birth_date)
                    ->diffInYears(CarbonImmutable::create($today->year, 3, 31));
                $stage = self::stage($row->stage.' '.$row->class_name, $age === null ? null : (int) $age);
                $shift = self::shift((string) $row->shift);
                if ($stage === null) {
                    $warnings[] = 'etapa';
                }
                if ($shift === null) {
                    $warnings[] = 'turno';
                }
                if ($stage !== null) {
                    $category = self::category($stage, $shift ?? 'parcial');
                }
            }
            if (($schoolsPerStudent[$row->student_id] ?? 1) > 1) {
                $warnings[] = 'duplicada';
            }
            if ($row->birth_date === null) {
                $warnings[] = 'nascimento';
            }
            if ($row->race_color === null) {
                $warnings[] = 'cor_raca';
            }
            if ($row->residence_zone === null) {
                $warnings[] = 'zona';
            } elseif ($row->residence_zone === 'rural' && $row->transport_status === null) {
                $warnings[] = 'transporte_rural';
            }
            $special = (int) $row->special_conditions > 0;
            if ($special && $row->aee_status === null) {
                $warnings[] = 'aee';
            }

            $order = array_flip(array_keys(self::WARNINGS));
            usort($warnings, fn (string $a, string $b): int => $order[$a] <=> $order[$b]);

            return [
                'student_id' => $row->student_id,
                'name' => $row->full_name,
                'school_id' => $row->school_id,
                'school' => $row->school_name,
                'class' => $row->class_name,
                'category' => $category,
                'special' => $special,
                'aee' => $row->aee_status === 'participa',
                'warnings' => $warnings,
            ];
        })->sortBy(fn (array $item): string => Str::lower(Str::ascii($item['school'].'|'.$item['name'])))->values();

        $byType = array_fill_keys(array_keys(self::WARNINGS), 0);
        foreach ($result as $item) {
            foreach ($item['warnings'] as $type) {
                $byType[$type]++;
            }
        }

        $schools = School::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'inep_code']);
        $perSchool = $schools->map(fn (School $school): array => [
            'school' => $school,
            'enrollments' => $result->where('school_id', $school->id)->count(),
            'with_warnings' => $result->where('school_id', $school->id)->filter(fn (array $item): bool => $item['warnings'] !== [])->count(),
        ]);

        [$categories, $totals] = $this->estimate($result);

        return [
            'rows' => $result,
            'schools' => $perSchool,
            'schoolsWithoutInep' => $schools->filter(fn (School $school): bool => trim((string) $school->inep_code) === '')->values(),
            'byType' => $byType,
            'categories' => $categories,
            'totals' => $totals,
        ];
    }

    /**
     * Estimativa anual por categoria do Censo. As matrículas sem turma ou
     * sem etapa reconhecida são avaliadas como se fossem do Ensino
     * Fundamental parcial, só para mostrar a ordem de grandeza em risco.
     *
     * @param  Collection<int, array{category: ?string, warnings: list<string>}>  $rows
     */
    private function estimate(Collection $rows): array
    {
        $config = config('censo.categories');
        $base = config('censo.vaaf_base');
        $days = (int) config('censo.pnae_days', 200);
        $counts = $rows->whereNotNull('category')->countBy('category');

        $categories = [];
        foreach ($config as $key => $item) {
            $count = (int) ($counts[$key] ?? 0);
            $weighted = round($count * (float) $item['weight'], 2);
            $categories[] = [
                'key' => $key,
                'label' => $item['label'],
                'count' => $count,
                'weight' => (float) $item['weight'],
                'weighted' => $weighted,
                'fundeb' => $base === null ? null : round($weighted * (float) $base, 2),
                'pnae' => round($count * $days * (float) $item['pnae'], 2),
            ];
        }

        $atRisk = $rows->filter(fn (array $item): bool => array_intersect(self::UNCOUNTED, $item['warnings']) !== [])->count();
        $reference = $config['fundamental_iniciais'];
        $weightedTotal = array_sum(array_column($categories, 'weighted'));

        return [$categories, [
            'enrollments' => $rows->count(),
            'with_warnings' => $rows->filter(fn (array $item): bool => $item['warnings'] !== [])->count(),
            'weighted' => round($weightedTotal, 2),
            'fundeb' => $base === null ? null : round($weightedTotal * (float) $base, 2),
            'pnae' => round(array_sum(array_column($categories, 'pnae')), 2),
            'at_risk_enrollments' => $atRisk,
            'at_risk_fundeb' => $base === null ? null : round($atRisk * (float) $reference['weight'] * (float) $base, 2),
            'at_risk_pnae' => round($atRisk * $days * (float) $reference['pnae'], 2),
            'special' => $rows->where('special', true)->unique('student_id')->count(),
            'aee' => $rows->where('aee', true)->unique('student_id')->count(),
            'aee_pnae' => round($rows->where('aee', true)->unique('student_id')->count() * $days * (float) config('censo.pnae_aee', 0.78), 2),
        ]];
    }

    /**
     * Etapa do Censo pelo texto da turma; na dúvida dentro da Educação
     * Infantil ou do Fundamental, decide pela idade em 31 de março.
     */
    public static function stage(string $text, ?int $age): ?string
    {
        $value = Str::lower(Str::ascii($text));

        if (preg_match('/\beja\b|jovens e adultos/', $value)) {
            return 'eja';
        }
        if (preg_match('/creche|bercario|bercario|maternal|\bberc/', $value)) {
            return 'creche';
        }
        if (preg_match('/\bpre\b|pre-escola|pre escola|\bpre[- ]?(i|ii|1|2)\b|jardim/', $value)) {
            return 'pre_escola';
        }
        if (str_contains($value, 'infantil')) {
            return $age === null ? null : ($age <= 3 ? 'creche' : 'pre_escola');
        }
        if (str_contains($value, 'anos finais')) {
            return 'fundamental_finais';
        }
        if (str_contains($value, 'anos iniciais')) {
            return 'fundamental_iniciais';
        }
        if (preg_match('/\b([1-9])\s*(o|º|°)?\s*ano\b/u', $value, $match) || str_contains($value, 'fundamental')) {
            $year = isset($match[1]) ? (int) $match[1] : null;
            if ($year !== null) {
                return $year <= 5 ? 'fundamental_iniciais' : 'fundamental_finais';
            }

            return $age === null ? null : ($age <= 10 ? 'fundamental_iniciais' : 'fundamental_finais');
        }

        return null;
    }

    /** Jornada pelo turno escrito na turma: integral, parcial ou não reconhecida. */
    public static function shift(string $text): ?string
    {
        $value = Str::lower(Str::ascii($text));
        if (str_contains($value, 'integral')) {
            return 'integral';
        }
        if (preg_match('/manha|tarde|noite|matutino|vespertino|noturno|parcial|intermediario/', $value)) {
            return 'parcial';
        }

        return null;
    }

    private static function category(string $stage, string $shift): string
    {
        return match ($stage) {
            'creche' => $shift === 'integral' ? 'creche_integral' : 'creche_parcial',
            'pre_escola' => $shift === 'integral' ? 'pre_integral' : 'pre_parcial',
            'eja' => 'eja',
            default => $shift === 'integral' ? 'fundamental_integral' : $stage,
        };
    }
}
