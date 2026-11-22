<?php

namespace App\Import\Students;

use DateTimeImmutable;

/**
 * Escola escolhida pela pessoa que importa. A escola nunca e adivinhada pelo
 * nome do arquivo: quem envia a planilha diz de qual escola ela e.
 */
final readonly class ImportTarget
{
    /** @param list<ClassOption> $classes */
    public function __construct(
        public string $schoolId,
        public array $classes,
        public DateTimeImmutable $referenceDate,
    ) {}
}
