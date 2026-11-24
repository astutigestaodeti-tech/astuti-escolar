<?php

namespace App\Models\Tenant;

class ClassCouncilStudent extends TenantModel
{
    protected $keyType = 'string';

    public $incrementing = false;

    public const REFERRALS = [
        'recovery' => 'Recuperação paralela',
        'support' => 'Reforço',
        'aee' => 'AEE',
        'team' => 'Equipe multiprofissional',
        'active_search' => 'Busca ativa',
        'family' => 'Conversa com a família',
        'none' => 'Sem encaminhamento',
    ];

    protected $fillable = ['id', 'class_council_id', 'student_enrollment_id', 'opinion', 'referral', 'observation', 'updated_by'];
}
