<?php

namespace App\Models\Tenant;

use App\Models\Tenant\Concerns\HasSchoolHistory;

class School extends TenantModel
{
    use HasSchoolHistory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'inep_code',
        'status',
        'postal_code',
        'address_street',
        'address_number',
        'address_complement',
        'address_district',
        'address_city',
        'address_state',
    ];

    public const ADDRESS_FIELDS = ['postal_code', 'address_street', 'address_number', 'address_complement', 'address_district', 'address_city', 'address_state'];

    /** Endereço em uma linha, para o mapa e os documentos. */
    public function addressLine(): string
    {
        return implode(', ', array_filter([
            trim(implode(' ', array_filter([$this->address_street, $this->address_number]))) ?: null,
            $this->address_district,
            trim(implode('/', array_filter([$this->address_city, $this->address_state]))) ?: null,
            $this->postal_code,
        ]));
    }
}
