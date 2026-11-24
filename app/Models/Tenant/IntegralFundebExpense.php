<?php

namespace App\Models\Tenant;

class IntegralFundebExpense extends TenantModel
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'school_year_id', 'description', 'amount_cents',
        'evidence_path', 'evidence_name', 'evidence_mime', 'evidence_uploaded_at', 'created_by',
    ];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'evidence_uploaded_at' => 'datetime'];
    }
}
