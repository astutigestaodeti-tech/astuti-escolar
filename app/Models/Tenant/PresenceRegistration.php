<?php

namespace App\Models\Tenant;

/** Frequência do período já lançada pelo operador no Sistema Presença. */
class PresenceRegistration extends TenantModel
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'school_id', 'school_year_id', 'student_id', 'period', 'registered_by', 'registered_at'];

    protected function casts(): array
    {
        return ['period' => 'integer', 'registered_at' => 'datetime'];
    }
}
