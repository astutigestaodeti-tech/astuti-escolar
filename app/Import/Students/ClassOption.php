<?php

namespace App\Import\Students;

/** Turma existente na escola de destino, contra a qual a planilha e conferida. */
final readonly class ClassOption
{
    public function __construct(
        public string $id,
        public string $code,
        public string $shift,
    ) {}
}
