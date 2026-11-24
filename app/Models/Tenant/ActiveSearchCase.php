<?php

namespace App\Models\Tenant;

class ActiveSearchCase extends TenantModel
{
    public const STATUSES = [
        'open' => 'Aberto',
        'returned' => 'Retornou',
        'referred' => 'Encaminhado',
        'closed' => 'Encerrado',
    ];

    public const REASONS = [
        'health' => 'Saúde',
        'transport' => 'Transporte',
        'work' => 'Trabalho',
        'move' => 'Mudança',
        'other' => 'Outro',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'enrollment_id', 'school_id', 'school_year_id', 'student_id', 'status', 'reason_category', 'opened_at', 'returned_at', 'updated_by'];

    protected function casts(): array
    {
        return ['opened_at' => 'datetime', 'returned_at' => 'datetime'];
    }
}
