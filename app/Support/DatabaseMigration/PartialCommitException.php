<?php

namespace App\Support\DatabaseMigration;

use RuntimeException;
use Throwable;

final class PartialCommitException extends RuntimeException
{
    public function __construct(Throwable $previous)
    {
        parent::__construct(
            'Commit parcial. O destino municipal PostgreSQL foi confirmado e o commit do destino central ficou sem confirmação. Não repita a migração.',
            0,
            $previous,
        );
    }
}
