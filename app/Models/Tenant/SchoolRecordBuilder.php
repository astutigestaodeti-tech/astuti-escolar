<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Builder;
use LogicException;

class SchoolRecordBuilder extends Builder
{
    public function delete()
    {
        throw new LogicException('Exclusões escolares em lote sem autoria e motivo são proibidas.');
    }

    public function forceDelete()
    {
        throw new LogicException('Exclusão física de registros escolares é proibida.');
    }
}
