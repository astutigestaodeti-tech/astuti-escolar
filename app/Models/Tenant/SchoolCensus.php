<?php

namespace App\Models\Tenant;

/** Dados da escola para o Censo Escolar (registro 00 do Educacenso). */
class SchoolCensus extends TenantModel
{
    public const FIELDS = [
        'operating_status',
        'location_zone',
        'differentiated_location',
        'administrative_dependency',
        'regulation',
        'regulation_sphere',
        'linked_unit',
        'district_code',
        'phone_ddd',
        'phone',
        'phone_other',
        'email',
        'regional_body_code',
        'host_school_code',
        'ies_code',
    ];

    protected $table = 'school_census';

    protected $primaryKey = 'school_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['school_id', ...self::FIELDS, 'updated_by'];

    protected function casts(): array
    {
        return [
            'operating_status' => 'integer',
            'location_zone' => 'integer',
            'differentiated_location' => 'integer',
            'administrative_dependency' => 'integer',
            'regulation' => 'integer',
            'regulation_sphere' => 'integer',
            'linked_unit' => 'integer',
        ];
    }
}
