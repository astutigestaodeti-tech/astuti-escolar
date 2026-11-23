<?php

namespace App\Models\Tenant;

class IntegralFundebFinance extends TenantModel
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'school_year_id', 'revenue_cents', 'updated_by'];

    protected function casts(): array
    {
        return ['revenue_cents' => 'integer'];
    }
}
