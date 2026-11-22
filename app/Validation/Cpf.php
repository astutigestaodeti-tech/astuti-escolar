<?php

namespace App\Validation;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** CPF com dígitos verificadores corretos. Aceita com ou sem pontuação. */
class Cpf implements ValidationRule
{
    /** "12345678909" ou "123.456.789-09" viram "123.456.789-09"; qualquer outra coisa, null. */
    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/\A[\d.\-\s]+\z/', $value) !== 1) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return strlen($digits) === 11
            ? substr($digits, 0, 3).'.'.substr($digits, 3, 3).'.'.substr($digits, 6, 3).'-'.substr($digits, 9)
            : null;
    }

    public static function isValid(mixed $value): bool
    {
        $formatted = self::normalize($value);
        if ($formatted === null) {
            return false;
        }

        $digits = preg_replace('/\D+/', '', $formatted) ?? '';
        if (preg_match('/\A(\d)\1{10}\z/', $digits) === 1) {
            return false;
        }

        foreach ([9, 10] as $length) {
            $sum = 0;
            for ($i = 0; $i < $length; $i++) {
                $sum += (int) $digits[$i] * ($length + 1 - $i);
            }
            if ((($sum * 10) % 11) % 10 !== (int) $digits[$length]) {
                return false;
            }
        }

        return true;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (self::normalize($value) === null) {
            $fail('O CPF tem 11 números. Exemplo: 123.456.789-09.');
        } elseif (! self::isValid($value)) {
            $fail('CPF inválido: os dígitos verificadores não conferem. Confira com o documento.');
        }
    }
}
