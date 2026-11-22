<?php

namespace App\Import\Xlsx;

use DateTimeImmutable;
use DateTimeZone;

final class ExcelDate
{
    /** Numeros de serie aceitos como data: 01/01/1900 a 31/12/2199. */
    public const MIN_SERIAL = 1;

    public const MAX_SERIAL = 109574;

    public static function fromSerial(float $serial, bool $date1904 = false): ?DateTimeImmutable
    {
        $days = (int) floor($serial);
        if ($days < self::MIN_SERIAL || $days > self::MAX_SERIAL) {
            return null;
        }

        // O sistema 1900 conta o inexistente 29/02/1900; por isso a base e 30/12/1899.
        $base = $date1904 ? '1904-01-01' : '1899-12-30';

        return (new DateTimeImmutable($base, new DateTimeZone('UTC')))->modify("+{$days} days");
    }
}
