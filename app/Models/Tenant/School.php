<?php

namespace App\Models\Tenant;

use App\Models\Tenant\Concerns\HasSchoolHistory;

class School extends TenantModel
{
    use HasSchoolHistory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'inep_code',
        'status',
    ];
}
