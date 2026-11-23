<?php

namespace App\Models\Tenant;

class StudentFinalResult extends TenantModel
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id', 'school_class_id', 'student_enrollment_id', 'suggested_result',
        'result', 'override_reason', 'confirmed_by', 'confirmed_at',
    ];

    protected function casts(): array
    {
        return ['confirmed_at' => 'datetime'];
    }
}
