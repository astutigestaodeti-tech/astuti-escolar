<?php

namespace App\Audit;

use Closure;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** Autoria explícita para comandos e jobs sem sessão web. */
class AuditActor
{
    /** @var list<string> */
    private array $stack = [];

    public function current(): ?string
    {
        return $this->stack[count($this->stack) - 1] ?? null;
    }

    public function enter(string $userId): void
    {
        if (! Str::isUuid($userId)) {
            throw new InvalidArgumentException('A autoria da tarefa deve ser um identificador UUID válido.');
        }

        $this->stack[] = $userId;
    }

    public function leave(): void
    {
        array_pop($this->stack);
    }

    public function runAs(string $userId, Closure $callback): mixed
    {
        $this->enter($userId);

        try {
            return $callback();
        } finally {
            $this->leave();
        }
    }
}
