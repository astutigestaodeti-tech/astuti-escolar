<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\SoftDeletes;

class StudentPriorSchoolYear extends TenantModel
{
    use SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id', 'student_id', 'school_year', 'school_stage', 'school_name', 'city', 'state',
        'result', 'attendance_percent', 'component_grades', 'recorded_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'school_year' => 'integer',
            'attendance_percent' => 'decimal:1',
            'component_grades' => 'array',
            'deleted_at' => 'datetime',
        ];
    }
}
