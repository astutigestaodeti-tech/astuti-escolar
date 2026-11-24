<?php

namespace App\Models\Tenant;

class ClassTeacherAssignmentCensus extends TenantModel
{
    protected $table = 'class_teacher_assignment_census';

    protected $primaryKey = 'assignment_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['assignment_id', 'class_function', 'employment_status', 'components', 'updated_by'];

    protected function casts(): array
    {
        return ['class_function' => 'integer', 'employment_status' => 'integer', 'components' => 'array'];
    }
}
