<?php

namespace App\Import\Xlsx;

use DateTimeImmutable;

final readonly class XlsxSheet
{
    /**
     * @param  array<int, array<int, string|int|float|bool|DateTimeImmutable>>  $rows  linha (1..n) => coluna (0..n) => valor
     */
    public function __construct(
        public string $name,
        public array $rows,
    ) {}
}
