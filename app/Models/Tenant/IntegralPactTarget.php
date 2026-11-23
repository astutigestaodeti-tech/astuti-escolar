<?php

namespace App\Models\Tenant;

class IntegralPactTarget extends TenantModel
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'school_year_id', 'stage', 'target_enrollments', 'updated_by'];

    protected function casts(): array
    {
        return ['target_enrollments' => 'integer'];
    }
}
