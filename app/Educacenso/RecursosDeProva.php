<?php

namespace App\Educacenso;

use App\Models\Tenant\StudentSpecialEducation;

/**
 * Combinação de deficiência, TEA ou transtorno com recursos de prova
 * (registro 30, Anexo 4). Altas habilidades não autorizam recurso.
 */
final class RecursosDeProva
{
    /**
     * Recursos marcados que nenhuma das condições marcadas autoriza.
     *
     * @param  list<string>  $condicoes  chaves de StudentSpecialEducation::CONDITIONS
     * @param  list<string>  $transtornos  chaves de StudentSpecialEducation::LEARNING_DISORDERS
     * @param  list<string>  $recursos  chaves de StudentSpecialEducation::EXAM_RESOURCES
     * @return list<string> chaves dos recursos recusados
     */
    public static function naoPermitidos(array $condicoes, array $transtornos, array $recursos, int $ano): array
    {
        $leiaute = Leiaute::do($ano);
        $porCondicao = Tabelas::do($ano)->recursosPorCondicao();
        $campos = StudentSpecialEducation::CENSUS_FIELDS;

        $permitidos = [];
        foreach ([...array_map(fn ($c) => $campos['conditions'][$c] ?? null, $condicoes), ...array_map(fn ($t) => $campos['learning_disorders'][$t] ?? null, $transtornos)] as $campo) {
            if ($campo !== null) {
                $permitidos = [...$permitidos, ...($porCondicao[Tabelas::chaveDeNome($leiaute->campo('30', $campo)->nome)] ?? [])];
            }
        }

        return array_values(array_filter($recursos, function (string $recurso) use ($campos, $leiaute, $permitidos): bool {
            $campo = $campos['exam_resources'][$recurso] ?? null;

            return $campo === null || ! in_array(Tabelas::chaveDeNome($leiaute->campo('30', $campo)->nome), $permitidos, true);
        }));
    }
}
