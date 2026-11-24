<?php

namespace App\Models\Tenant;

class ActiveSearchParameter extends TenantModel
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'school_year_id', 'consecutive_days', 'monthly_percentage', 'updated_by'];

    protected function casts(): array
    {
        return ['consecutive_days' => 'integer', 'monthly_percentage' => 'integer'];
    }
}
