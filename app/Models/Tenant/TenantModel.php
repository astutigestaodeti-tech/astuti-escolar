<?php

namespace App\Models\Tenant;

use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use LogicException;

abstract class TenantModel extends Model
{
    protected $connection = 'tenant';

    private ?string $boundMunicipalityId = null;

    public function getConnectionName(): ?string
    {
        $context = app(TenantContext::class);

        if (! $context->hasActive()) {
            throw new LogicException('Acesso a dados escolares sem contexto municipal.');
        }

        $activeId = (string) $context->current()->id;
        if ($this->boundMunicipalityId !== null && $this->boundMunicipalityId !== $activeId) {
            throw new LogicException('Instância escolar pertence a outro município.');
        }

        $this->boundMunicipalityId ??= $activeId;

        return parent::getConnectionName();
    }

    public function newFromBuilder($attributes = [], $connection = null)
    {
        $model = parent::newFromBuilder($attributes, $connection);
        $model->boundMunicipalityId = (string) app(TenantContext::class)->current()?->id;

        return $model;
    }
}
