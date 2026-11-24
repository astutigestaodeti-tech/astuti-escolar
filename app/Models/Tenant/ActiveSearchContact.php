<?php

namespace App\Models\Tenant;

class ActiveSearchContact extends TenantModel
{
    public const CHANNELS = ['phone' => 'Telefone', 'visit' => 'Visita', 'message' => 'Mensagem'];

    public const OUTCOMES = [
        'no_answer' => 'Sem resposta',
        'contacted' => 'Contato realizado',
        'return_agreed' => 'Retorno combinado',
        'declined' => 'Contato recusado',
        'other' => 'Outro resultado',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'active_search_case_id', 'contact_date', 'channel', 'outcome', 'recorded_by'];

    protected function casts(): array
    {
        return ['contact_date' => 'date'];
    }
}
