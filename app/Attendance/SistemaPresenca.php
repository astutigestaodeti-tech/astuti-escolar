<?php

namespace App\Attendance;

use App\Models\Tenant\PresenceRegistration;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\StudentEnrollment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Frequência para o Sistema Presença (condicionalidade de educação do Bolsa
 * Família): cinco períodos bimestrais, mínimo mensal de 60% de 4 a 6 anos
 * incompletos e de 75% de 6 a 18 anos incompletos (Portaria Interministerial
 * MEC/MDS nº 12/2025).
 *
 * O Astuti não guarda quem é beneficiário nem o motivo da baixa frequência:
 * apura a frequência de todos os alunos na faixa etária, e o operador cruza
 * com a lista do próprio Sistema Presença, onde registra o motivo.
 * Falta justificada conta como falta: a justificativa vira motivo no Presença.
 */
final class SistemaPresenca
{
    /** Período => [mês inicial, mês final]. */
    public const PERIODOS = [1 => [2, 3], 2 => [4, 5], 3 => [6, 7], 4 => [8, 9], 5 => [10, 11]];

    public const NOMES_DOS_MESES = [2 => 'fevereiro', 3 => 'março', 4 => 'abril', 5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto', 9 => 'setembro', 10 => 'outubro', 11 => 'novembro'];

    public const ABAIXO = 'abaixo';

    public const SEM_CHAMADA = 'sem_chamada';

    public const EM_DIA = 'em_dia';

    public const SITUACOES = [
        self::ABAIXO => 'Abaixo do mínimo',
        self::SEM_CHAMADA => 'Mês sem frequência registrada',
        self::EM_DIA => 'Frequência mínima atingida',
    ];

    /** Período em que a data está, ou o último período encerrado antes dela. */
    public static function periodoDe(CarbonImmutable $data): int
    {
        foreach (self::PERIODOS as $periodo => [$de, $ate]) {
            if ($data->month <= $ate) {
                return max(1, $data->month < $de ? $periodo - 1 : $periodo);
            }
        }

        return 5;
    }

    public static function rotulo(int $periodo): string
    {
        [$de, $ate] = self::PERIODOS[$periodo];

        return $periodo.'º período ('.self::NOMES_DOS_MESES[$de].' e '.self::NOMES_DOS_MESES[$ate].')';
    }

    /** Mínimo exigido pela idade no início do período; null fora da faixa de 4 a 18 anos incompletos. */
    public static function minimo(?int $idade): ?int
    {
        return match (true) {
            $idade === null, $idade < 4, $idade >= 18 => null,
            $idade < 6 => 60,
            default => 75,
        };
    }

    /**
     * Linhas por escola: um aluno por linha, com frequência de cada mês.
     * Consultas fixas, independentemente do número de escolas e alunos.
     *
     * @param  list<string>  $escolas
     * @return Collection<string, list<array>> escola => linhas
     */
    public function para(array $escolas, SchoolYear $ano, int $periodo): Collection
    {
        [$mesInicial, $mesFinal] = self::PERIODOS[$periodo];
        $inicio = CarbonImmutable::create($ano->year, $mesInicial, 1);
        $fim = CarbonImmutable::create($ano->year, $mesFinal, 1)->endOfMonth();
        $meses = [$mesInicial, $mesFinal];

        $matriculas = StudentEnrollment::query()
            ->join('students as aluno', 'aluno.id', '=', 'student_enrollments.student_id')
            ->join('school_classes as turma', 'turma.id', '=', 'student_enrollments.school_class_id')
            ->whereIn('student_enrollments.school_id', $escolas)
            ->where('student_enrollments.school_year_id', $ano->id)
            ->whereIn('student_enrollments.status', ['active', 'transferred', 'left_network', 'abandoned', 'completed'])
            ->whereNull('aluno.deleted_at')
            ->where(fn ($q) => $q->whereNull('student_enrollments.started_at')->orWhereDate('student_enrollments.started_at', '<=', $fim->toDateString()))
            ->where(fn ($q) => $q->whereNull('student_enrollments.ended_at')->orWhereDate('student_enrollments.ended_at', '>=', $inicio->toDateString()))
            ->get([
                'student_enrollments.id', 'student_enrollments.student_id', 'student_enrollments.school_id',
                'student_enrollments.school_class_id', 'student_enrollments.status',
                'aluno.full_name', 'aluno.birth_date', 'turma.name as class_name',
            ]);

        $turmas = $matriculas->pluck('school_class_id')->unique()->values();
        // Marcas por aluno, mês e situação, só nas turmas das matrículas.
        $marcas = $turmas->isEmpty() ? collect() : DB::connection('tenant')->table('attendance_marks as m')
            ->join('attendance_sessions as s', 's.id', '=', 'm.attendance_session_id')
            ->whereIn('s.school_class_id', $turmas)
            ->whereIn('m.student_id', $matriculas->pluck('student_id')->unique())
            ->whereBetween('s.attendance_date', [$inicio->toDateString(), $fim->toDateString()])
            ->selectRaw('m.student_id, extract(month from s.attendance_date)::int as mes, m.status, count(*) as total')
            ->groupBy('m.student_id', 'mes', 'm.status')->get()
            ->groupBy('student_id');
        $lancados = PresenceRegistration::query()->where('school_year_id', $ano->id)->where('period', $periodo)
            ->whereIn('student_id', $matriculas->pluck('student_id')->unique())->get()->keyBy(fn ($r) => $r->school_id.'|'.$r->student_id);

        $linhas = [];
        foreach ($matriculas->groupBy(fn ($m) => $m->school_id.'|'.$m->student_id) as $vinculos) {
            $m = $vinculos->sortByDesc(fn ($v) => $v->status === 'active')->first();
            $idade = $m->birth_date === null ? null : (int) CarbonImmutable::parse($m->birth_date)->diffInYears($inicio);
            $minimo = self::minimo($idade);
            if ($minimo === null) {
                continue;
            }
            $porMes = [];
            $situacao = self::EM_DIA;
            foreach ($meses as $mes) {
                $contas = ['present' => 0, 'absent' => 0, 'justified' => 0];
                foreach ($marcas->get($m->student_id, collect())->where('mes', $mes) as $linha) {
                    $contas[$linha->status] = (int) $linha->total;
                }
                $total = array_sum($contas);
                $percentual = $total > 0 ? (int) floor($contas['present'] * 100 / $total) : null;
                $porMes[$mes] = ['percentual' => $percentual, 'faltas' => $contas['absent'] + $contas['justified'], 'justificadas' => $contas['justified'], 'aulas' => $total];
                if ($percentual !== null && $percentual < $minimo) {
                    $situacao = self::ABAIXO;
                } elseif ($percentual === null && $situacao !== self::ABAIXO) {
                    $situacao = self::SEM_CHAMADA;
                }
            }
            $somaPresencas = 0;
            $somaAulas = 0;
            foreach ($porMes as $mes => $dados) {
                $somaAulas += $dados['aulas'];
                $somaPresencas += $dados['aulas'] - $dados['faltas'];
            }
            $registro = $lancados->get($m->school_id.'|'.$m->student_id);
            $linhas[$m->school_id][] = [
                'matricula' => $m->id,
                'aluno' => $m->student_id,
                'nome' => $m->full_name,
                'turma' => $m->class_name,
                'idade' => $idade,
                'minimo' => $minimo,
                'meses' => $porMes,
                'bimestre' => $somaAulas > 0 ? (int) floor($somaPresencas * 100 / $somaAulas) : null,
                'situacao' => $situacao,
                'encerrada' => $m->status !== 'active',
                'lancado' => $registro?->registered_at,
            ];
        }

        return collect($linhas)->map(fn (array $lista): array => collect($lista)
            ->sortBy(fn (array $l): string => Str::lower(Str::ascii($l['turma'].'|'.$l['nome'])))->values()->all());
    }
}
