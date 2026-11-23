<?php

namespace App\Models\Tenant;

class ClassYearClosure extends TenantModel
{
    protected $primaryKey = 'school_class_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['school_class_id'];

    protected function casts(): array
    {
        return ['closed_at' => 'datetime', 'reopened_at' => 'datetime'];
    }
}
