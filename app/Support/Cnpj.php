<?php

namespace App\Support;

/** CNPJ numérico: limpeza, dígitos verificadores e máscara 00.000.000/0000-00. */
final class Cnpj
{
    public static function digits(?string $value): string
    {
        return (string) preg_replace('/\D/', '', (string) $value);
    }

    public static function isValid(?string $value): bool
    {
        $cnpj = self::digits($value);
        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj) === 1) {
            return false;
        }

        return substr($cnpj, 12, 2) === self::checkDigits(substr($cnpj, 0, 12));
    }

    public static function checkDigits(string $base): string
    {
        $weights = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $first = self::digit($base, $weights);
        $second = self::digit($base.$first, [6, ...$weights]);

        return $first.$second;
    }

    public static function format(string $value): string
    {
        $cnpj = self::digits($value);

        return strlen($cnpj) === 14
            ? sprintf('%s.%s.%s/%s-%s', substr($cnpj, 0, 2), substr($cnpj, 2, 3), substr($cnpj, 5, 3), substr($cnpj, 8, 4), substr($cnpj, 12))
            : $value;
    }

    /** @param list<int> $weights */
    private static function digit(string $numbers, array $weights): int
    {
        $sum = 0;
        foreach ($weights as $index => $weight) {
            $sum += (int) $numbers[$index] * $weight;
        }
        $rest = $sum % 11;

        return $rest < 2 ? 0 : 11 - $rest;
    }
}
