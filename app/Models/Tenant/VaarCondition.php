<?php

namespace App\Models\Tenant;

class VaarCondition extends TenantModel
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id', 'reference_year', 'condition_number', 'status', 'responsible',
        'evidence_path', 'evidence_name', 'evidence_mime', 'evidence_uploaded_at', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'reference_year' => 'integer',
            'condition_number' => 'integer',
            'evidence_uploaded_at' => 'datetime',
        ];
    }
}
