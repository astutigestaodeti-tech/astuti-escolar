<?php

namespace App\Import\Students;

final readonly class ImportIssue
{
    /** Precisa de decisao humana antes de gravar (duplicidade, turma, conflito). */
    public const CONFIRM = 'conferir';

    /** Dado que o arquivo nao traz: fica pendente, nunca inventado. */
    public const PENDING = 'pendente';

    public function __construct(
        public string $kind,
        public string $field,
        public string $message,
    ) {}
}
