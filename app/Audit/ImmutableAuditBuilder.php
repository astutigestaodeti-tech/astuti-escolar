<?php

namespace App\Audit;

use Illuminate\Database\Eloquent\Builder;
use LogicException;

class ImmutableAuditBuilder extends Builder
{
    public function upsert(array $values, $uniqueBy, $update = null)
    {
        throw new LogicException('Registros de auditoria são imutáveis.');
    }

    public function updateOrInsert(array $attributes, array|callable $values = [])
    {
        throw new LogicException('Registros de auditoria são imutáveis.');
    }

    public function insertOrIgnore(array $values)
    {
        throw new LogicException('Registros de auditoria não podem ser ignorados silenciosamente.');
    }

    public function update(array $values)
    {
        throw new LogicException('Registros de auditoria são imutáveis.');
    }

    public function delete()
    {
        throw new LogicException('Registros de auditoria não podem ser excluídos.');
    }

    public function forceDelete()
    {
        throw new LogicException('Registros de auditoria não podem ser excluídos fisicamente.');
    }
}
