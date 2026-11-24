<?php

namespace App\Educacenso;

use Normalizer;

/**
 * Texto no formato exigido pelas regras gerais do leiaute: sem letras
 * minúsculas, sem acentos (regra geral 28) e em ISO-8859-1 (regra geral 1).
 *
 * Só faz as conversões que o Inep documenta. Qualquer outro caractere fora do
 * conjunto permitido do campo fica como está e é apontado pelo validador, para
 * a escola corrigir no cadastro, nunca descartado em silêncio.
 */
final class Texto
{
    /** Ordinais aceitos pelo Inep em alguns campos ("A-Z 0-9 ª º -"). */
    private const ORDINAIS = ['ª', 'º'];

    public static function normalizar(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        $valor = trim((string) preg_replace('/\s+/u', ' ', $valor));
        if ($valor === '') {
            return null;
        }

        $saida = '';
        foreach (mb_str_split($valor) as $caractere) {
            if (in_array($caractere, self::ORDINAIS, true)) {
                $saida .= $caractere;

                continue;
            }
            $decomposto = Normalizer::normalize($caractere, Normalizer::FORM_D);
            $saida .= mb_strtoupper((string) preg_replace('/\p{Mn}+/u', '', $decomposto === false ? $caractere : $decomposto));
        }

        return $saida;
    }

    /** Bytes do arquivo em ISO-8859-1. Recusa o que não cabe nessa codificação. */
    public static function iso88591(string $utf8): string
    {
        $convertido = mb_convert_encoding($utf8, 'ISO-8859-1', 'UTF-8');
        if (mb_convert_encoding($convertido, 'UTF-8', 'ISO-8859-1') !== $utf8) {
            throw new \InvalidArgumentException('Texto com caractere que não existe em ISO-8859-1.');
        }

        return $convertido;
    }
}
