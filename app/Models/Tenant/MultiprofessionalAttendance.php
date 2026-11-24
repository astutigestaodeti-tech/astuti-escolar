<?php

namespace App\Models\Tenant;

class MultiprofessionalAttendance extends TenantModel
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'request_id', 'attended_on', 'recorded_by'];

    protected function casts(): array
    {
        return ['attended_on' => 'date'];
    }
}
