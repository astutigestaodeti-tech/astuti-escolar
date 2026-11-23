<?php

namespace App\Documents;

final class VerificationCode
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public static function generate(): string
    {
        $code = '';
        for ($index = 0; $index < 10; $index++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }

    public static function abbreviate(string $fullName): string
    {
        $parts = preg_split('/\s+/u', trim($fullName)) ?: [];
        $first = array_shift($parts);
        if (! is_string($first)) {
            return '';
        }

        foreach ($parts as &$part) {
            $part = mb_strtoupper(mb_substr($part, 0, 1)).'.';
        }
        unset($part);

        return implode(' ', [$first, ...$parts]);
    }
}
