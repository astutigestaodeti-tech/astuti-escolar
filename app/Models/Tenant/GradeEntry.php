<?php

namespace App\Models\Tenant;

class GradeEntry extends TenantModel
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id', 'student_enrollment_id', 'school_class_id', 'academic_period_id',
        'curricular_component_id', 'grade', 'absences', 'recorded_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['grade' => 'decimal:1', 'absences' => 'integer'];
    }
}
