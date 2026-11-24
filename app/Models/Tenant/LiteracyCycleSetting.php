<?php

namespace App\Models\Tenant;

class LiteracyCycleSetting extends TenantModel
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'school_year_id', 'retain_first', 'retain_second', 'updated_by'];

    protected function casts(): array
    {
        return ['retain_first' => 'boolean', 'retain_second' => 'boolean'];
    }
}
