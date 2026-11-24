<?php

namespace App\Models\Tenant;

class SchoolMealMenu extends TenantModel
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'school_year_id', 'week_start', 'weekday', 'stage', 'meal_type',
        'description', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['week_start' => 'date', 'weekday' => 'integer'];
    }
}
