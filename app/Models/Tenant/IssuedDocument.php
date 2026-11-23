<?php

namespace App\Models\Tenant;

class IssuedDocument extends TenantModel
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id', 'kind', 'student_id', 'school_id', 'enrollment_id', 'verification_code',
        'issued_by', 'issued_at', 'purpose', 'revoked_at', 'revoked_by', 'revoke_reason',
        'snapshot_student_name', 'snapshot_school_name', 'snapshot_municipality_name',
        'snapshot_school_year', 'snapshot_stage', 'snapshot_class_name', 'snapshot_shift',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'revoked_at' => 'datetime',
            'snapshot_school_year' => 'integer',
        ];
    }
}
