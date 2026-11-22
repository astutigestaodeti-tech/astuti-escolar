<?php

namespace App\Models\Tenant;

/** CEP da base local da Prefeitura. Não é dado pessoal. */
class PostalCode extends TenantModel
{
    protected $table = 'postal_codes';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    public const CREATED_AT = null;

    protected $fillable = ['code', 'street', 'district', 'city', 'state', 'residence_zone', 'source'];
}
