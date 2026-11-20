<?php

namespace App\Models\Tenant;

class School extends TenantModel
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'inep_code',
        'status',
    ];
}
