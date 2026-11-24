<?php

namespace App\Models\Tenant;

class SchoolMealServing extends TenantModel
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'school_id', 'school_year_id', 'meal_date', 'stage', 'meal_type',
        'quantity', 'suggested_present', 'confirmed_by', 'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'meal_date' => 'date', 'quantity' => 'integer',
            'suggested_present' => 'integer', 'confirmed_at' => 'datetime',
        ];
    }
}
