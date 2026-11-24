<?php

namespace App\Educacenso;

final class OpcoesDeCampo
{
    /** @return array<int, string> */
    public static function de(Campo $campo): array
    {
        $opcoes = [];
        foreach (explode("\n", (string) $campo->permitidos) as $linha) {
            if (preg_match('/^(\d+)\s*-\s*(.+)$/u', trim($linha), $m) === 1) {
                $opcoes[(int) $m[1]] = $m[2];
            }
        }

        return $opcoes;
    }
}
