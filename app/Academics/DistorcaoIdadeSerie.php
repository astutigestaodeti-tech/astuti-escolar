<?php

namespace App\Academics;

use App\Educacenso\SugestaoDeTurma;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolClassCensus;
use App\Models\Tenant\StudentEnrollment;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class DistorcaoIdadeSerie
{
    private const IDADES = [14 => 6, 15 => 7, 16 => 8, 17 => 9, 18 => 10, 19 => 11, 20 => 12, 21 => 13, 41 => 14];

    public function etapa(SchoolClass $class, ?SchoolClassCensus $census): array
    {
        $sugerida = $census?->inep_stage_code === null;
        $tipo = $census?->class_type ?? SugestaoDeTurma::tipo($class);
        $etapa = $census?->inep_stage_code ?? SugestaoDeTurma::etapa($class);

        return ['idade' => in_array($tipo, [4, 5], true) ? null : (self::IDADES[$etapa] ?? null),
            'sugerida' => $sugerida, 'etapa' => $etapa];
    }

    public function calcular(?CarbonInterface $nascimento, int $ano, ?int $esperada): ?array
    {
        $corte = Carbon::create($ano, 3, 31)->startOfDay();
        if ($nascimento === null || $esperada === null || $nascimento->gt($corte)) {
            return null;
        }
        $idade = (int) $nascimento->diffInYears($corte);

        return ['idade' => $idade, 'esperada' => $esperada, 'distorcao' => $idade >= $esperada + 2];
    }

    public function avaliar(StudentEnrollment $e, int $ano, array $etapa): ?array
    {
        return $this->calcular($e->student?->birth_date, $ano, $etapa['idade']);
    }
}
