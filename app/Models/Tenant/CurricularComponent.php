<?php

namespace App\Models\Tenant;

class CurricularComponent extends TenantModel
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id', 'code', 'name', 'is_optional', 'active', 'display_order',
    ];

    protected function casts(): array
    {
        return ['is_optional' => 'boolean', 'active' => 'boolean', 'display_order' => 'integer'];
    }
}
