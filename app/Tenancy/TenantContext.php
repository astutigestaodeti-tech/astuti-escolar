<?php

namespace App\Tenancy;

use App\Models\Platform\Municipality;
use Closure;
use LogicException;

class TenantContext
{
    private ?Municipality $municipality = null;

    public function __construct(private readonly TenantConnectionManager $connections)
    {
        $this->connections->reset();
    }

    public function enter(Municipality $municipality): void
    {
        if ($this->hasActive()) {
            throw new LogicException('O contexto municipal anterior deve ser encerrado.');
        }

        if (! $municipality->exists || $municipality->status !== 'active') {
            throw new LogicException('Municipio inexistente ou inativo.');
        }

        $this->connections->activate($municipality);
        $this->municipality = $municipality;
    }

    public function leave(): void
    {
        try {
            $this->connections->reset();
        } finally {
            $this->municipality = null;
        }
    }

    public function hasActive(): bool
    {
        return $this->municipality !== null;
    }

    public function current(): ?Municipality
    {
        return $this->municipality;
    }

    public function runFor(Municipality $municipality, Closure $operation): mixed
    {
        $this->enter($municipality);

        try {
            return $operation();
        } finally {
            $this->leave();
        }
    }
}
