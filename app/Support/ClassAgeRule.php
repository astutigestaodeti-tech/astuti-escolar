<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Referência simplificada para conferência humana na data de corte de 31/03.
 * Não classifica EJA, multisseriadas ou códigos sem série inequívoca.
 */
final class ClassAgeRule
{
    public static function allowedAges(string $stage, string $name): ?array
    {
        $stage = Str::lower(Str::ascii(trim($stage)));
        $name = Str::lower(Str::ascii(trim($name)));
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

        if (preg_match('/\beja\b|jovens e adultos/u', $stage) === 1) {
            return null;
        }

        if (str_contains($stage, 'fundamental')) {
            if (preg_match('/\A([1-9])\s*(?:[oº°ª]\s*)?(?:ano|serie)\b/u', $name, $match) === 1
                || preg_match('/\A([1-9])\s*[a-z]\z/u', $name, $match) === 1) {
                return [((int) $match[1]) + 5];
            }

            return null;
        }

        if (! str_contains($stage, 'infantil') && ! str_contains($stage, 'pre-escola') && ! str_contains($stage, 'pre escola')) {
            return null;
        }

        if (preg_match('/\A(?:pre\s*(?:ii|2)|pii)(?:\b|(?=-))/u', $name) === 1
            || preg_match('/\A5\s*anos?\b/u', $name) === 1) {
            return [5];
        }

        if (preg_match('/\A(?:pre\s*(?:i|1)|pi)(?:\b|(?=-))/u', $name) === 1
            || preg_match('/\A4\s*anos?\b/u', $name) === 1) {
            return [4];
        }

        if (str_contains($stage, 'pre-escola') || str_contains($stage, 'pre escola')
            || preg_match('/\Apre[- ]escola\b/u', $name) === 1) {
            return [4, 5];
        }

        return null;
    }
}
