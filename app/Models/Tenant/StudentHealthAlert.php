<?php

namespace App\Models\Tenant;

class StudentHealthAlert extends TenantModel
{
    public const KINDS = ['allergy' => 'Alergia', 'condition' => 'Condição de saúde', 'care' => 'Cuidado na rotina'];

    public const SEVERITIES = ['critical' => 'Grave: o professor precisa saber', 'attention' => 'Atenção: só a gestão vê'];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'student_id', 'kind', 'severity', 'alert_text', 'care_instructions', 'created_by'];

    public function isCritical(): bool
    {
        return $this->severity === 'critical';
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? 'Alerta';
    }
}
