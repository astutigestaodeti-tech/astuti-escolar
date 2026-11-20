<?php

namespace App\Models\Tenant;

use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use LogicException;

abstract class TenantModel extends Model
{
    protected $connection = 'tenant';

    public function getConnectionName(): ?string
    {
        if (! app(TenantContext::class)->hasActive()) {
            throw new LogicException('Acesso a dados escolares sem contexto municipal.');
        }

        return parent::getConnectionName();
    }
}
