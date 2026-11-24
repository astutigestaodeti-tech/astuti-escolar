<?php

namespace Tests\Support;

use App\Educacenso\Leiaute;

/** Respostas sintéticas, sem dados de qualquer escola real. */
final class InfrastructureCensusFixture
{
    public static function valid(): array
    {
        $respostas = [];
        foreach (Leiaute::do(2026)->campos('10') as $numero => $campo) {
            if ($numero >= 3 && $campo->obrigatorio === 's') {
                $respostas[$numero] = '0';
            }
        }
        foreach ([3, 9, 18, 24, 28, 32, 37, 41, 81, 96, 111, 119, 139, 140, 174] as $numero) {
            $respostas[$numero] = '1';
        }
        $respostas[10] = '0';
        $respostas[91] = '4';
        $respostas[93] = $respostas[94] = $respostas[95] = '1';
        $respostas[117] = '0';
        ksort($respostas);

        return $respostas;
    }
}
