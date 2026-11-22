<?php

namespace App\Validation;

/**
 * RG e certidão de nascimento. O RG muda de formato em cada estado, então
 * guardamos só números (e o X final de SP), sem tentar validar dígito.
 * Certidão: a matrícula nacional tem 32 números (modelo desde 2010); o
 * modelo antigo é texto curto com livro, folha e termo.
 */
final class IdentityDocuments
{
    public static function normalizeRg(?string $value): ?string
    {
        $value = strtoupper(trim((string) $value));
        if ($value === '') {
            return null;
        }
        $clean = preg_replace('/[^0-9X]/', '', $value) ?? '';

        return $clean === '' ? $value : $clean;
    }

    public static function rgIsValid(string $rg): bool
    {
        return preg_match('/\A[0-9]{4,14}[0-9X]?\z/', $rg) === 1;
    }

    /** "123456789" vira "12.345.678-9" quando tem 9 caracteres (padrão de SP). */
    public static function formatRg(?string $rg): ?string
    {
        if ($rg === null || $rg === '') {
            return null;
        }

        return strlen($rg) === 9 ? substr($rg, 0, 2).'.'.substr($rg, 2, 3).'.'.substr($rg, 5, 3).'-'.substr($rg, 8) : $rg;
    }

    public static function normalizeBirthCertificate(?string $value): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
        if ($value === '') {
            return null;
        }
        $digits = preg_replace('/\D/', '', $value) ?? '';

        // Só números: é a matrícula nacional, guardada sem pontuação.
        return preg_match('/\A[\d\s.\-\/]+\z/', $value) === 1 ? $digits : $value;
    }

    public static function birthCertificateIsValid(string $certificate): bool
    {
        if (ctype_digit($certificate)) {
            return strlen($certificate) === 32;
        }

        return mb_strlen($certificate) <= 80 && preg_match('/\A[\p{L}\p{N}][\p{L}\p{N} .,ºª°\/\-]*\z/u', $certificate) === 1;
    }

    /** Matrícula de 32 números em blocos, como impressa na certidão. */
    public static function formatBirthCertificate(?string $certificate): ?string
    {
        if ($certificate === null || ! ctype_digit($certificate) || strlen($certificate) !== 32) {
            return $certificate;
        }

        return implode(' ', [substr($certificate, 0, 6), substr($certificate, 6, 2), substr($certificate, 8, 2), substr($certificate, 10, 4), substr($certificate, 14, 1), substr($certificate, 15, 5), substr($certificate, 20, 3), substr($certificate, 23, 7), substr($certificate, 30, 2)]);
    }
}
