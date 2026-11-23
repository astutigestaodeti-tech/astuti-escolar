<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

class WaitlistRequest extends TenantModel
{
    use SoftDeletes;

    public const STAGES = [
        'bercario' => 'Berçário',
        'maternal_1' => 'Maternal I',
        'maternal_2' => 'Maternal II',
        'pre_1' => 'Pré I',
        'pre_2' => 'Pré II',
    ];

    public const STATUSES = [
        'aguardando' => 'Aguardando',
        'convocado' => 'Convocado',
        'desistiu' => 'Desistiu',
        'atendido' => 'Atendido',
    ];

    public const PRIORITIES = [
        'sibling' => 'Irmão matriculado na escola',
        'disability' => 'Criança com deficiência',
        'protective' => 'Medida protetiva ou Conselho Tutelar',
        'vulnerability' => 'Responsável em situação de vulnerabilidade',
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id', 'protocol', 'child_name', 'birth_date', 'cpf', 'guardian_name',
        'guardian_phone', 'postal_code', 'address_street', 'address_number',
        'address_complement', 'address_district', 'address_city', 'address_state',
        'intake_year', 'cutoff_date', 'stage', 'desired_shift',
        'priority_criteria', 'priority_count', 'status', 'requested_at', 'created_by',
        'deleted_by', 'deletion_reason', 'student_id', 'enrollment_id',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'cutoff_date' => 'date',
            'requested_at' => 'datetime',
            'priority_criteria' => 'array',
            'priority_count' => 'integer',
            'intake_year' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    public function preferences(): HasMany
    {
        return $this->hasMany(WaitlistPreference::class)->orderBy('preference_order');
    }

    public function forceDelete(): ?bool
    {
        throw new LogicException('Pedidos de vaga não podem ser excluídos fisicamente.');
    }
}
