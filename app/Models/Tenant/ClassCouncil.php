<?php

namespace App\Models\Tenant;

class ClassCouncil extends TenantModel
{
    protected $keyType = 'string';

    public $incrementing = false;

    public const STATUSES = [
        'not_started' => 'Não iniciado',
        'in_progress' => 'Em andamento',
        'completed' => 'Concluído',
    ];

    protected $fillable = [
        'id', 'school_class_id', 'academic_period_id', 'status', 'meeting_date', 'participants', 'class_actions',
        'closed_at', 'closed_by', 'reopened_at', 'reopened_by', 'reopen_reason',
    ];

    protected function casts(): array
    {
        return ['meeting_date' => 'date', 'closed_at' => 'datetime', 'reopened_at' => 'datetime'];
    }
}
