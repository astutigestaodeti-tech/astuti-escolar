<?php

namespace App\Import\Xlsx;

use DateTimeImmutable;

final readonly class XlsxSheet
{
    /**
     * @param  array<int, array<int, string|int|float|bool|DateTimeImmutable>>  $rows  linha (1..n) => coluna (0..n) => valor
     * @param  array<int, float>  $confidence  linha => confianca da leitura (0 a 100); vazio quando nao houve OCR
     */
    public function __construct(
        public string $name,
        public array $rows,
        public array $confidence = [],
    ) {}
}
