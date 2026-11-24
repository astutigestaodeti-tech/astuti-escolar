<?php

namespace App\Models\Tenant;

class MultiprofessionalReferral extends TenantModel
{
    public const DESTINATIONS = [
        'cras' => 'CRAS',
        'creas' => 'CREAS',
        'capsi' => 'CAPSi',
        'ubs' => 'UBS',
        'conselho_tutelar' => 'Conselho Tutelar',
        'apae' => 'APAE',
        'ministerio_publico' => 'Ministério Público',
        'defensoria' => 'Defensoria',
        'other' => 'Outro',
    ];

    public const RETURNS = [
        'received' => 'Retorno recebido',
        'in_progress' => 'Acompanhamento em andamento',
        'not_admitted' => 'Não admitido pelo serviço',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'request_id', 'destination', 'referred_on', 'returned_on', 'return_category', 'recorded_by'];

    protected function casts(): array
    {
        return ['referred_on' => 'date', 'returned_on' => 'date'];
    }
}
