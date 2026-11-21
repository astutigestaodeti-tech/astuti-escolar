<?php

namespace App\Support\DatabaseMigration;

use InvalidArgumentException;

final class RowContentMismatchException extends InvalidArgumentException
{
    /** @param list<string> $columns */
    public function __construct(
        private readonly string $table,
        private readonly array $columns,
    ) {
        parent::__construct("Conteudo da tabela {$table} divergiu apos copia.");
    }

    public function table(): string
    {
        return $this->table;
    }

    /** @return list<string> */
    public function differingColumns(): array
    {
        return $this->columns;
    }
}
