<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MultiprofessionalRequest extends TenantModel
{
    public const AREAS = [
        'psychology' => 'Psicologia',
        'social_work' => 'Serviço Social',
        'pedagogy' => 'Pedagogia',
        'undetermined' => 'A definir',
    ];

    public const REASONS = [
        'learning' => 'Aprendizagem',
        'behavior' => 'Comportamento ou relações',
        'attendance' => 'Faltas ou permanência',
        'family_social' => 'Situação familiar ou social',
        'inclusion' => 'Inclusão ou deficiência',
        'other' => 'Outro',
    ];

    public const STATUSES = [
        'open' => 'Aberto',
        'scheduled' => 'Agendado',
        'attending' => 'Em atendimento',
        'referred' => 'Encaminhado à rede',
        'closed' => 'Encerrado',
    ];

    public const CLOSINGS = [
        'completed' => 'Acompanhamento concluído',
        'network_followup' => 'Acompanhamento pela rede de proteção',
        'left_school' => 'Saída da escola',
        'no_contact' => 'Sem contato após providências',
        'other' => 'Outro',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'enrollment_id', 'school_id', 'student_id', 'school_year_id', 'active_search_case_id',
        'area', 'reason_category', 'status', 'requested_by', 'opened_at', 'assigned_to', 'assigned_at',
        'scheduled_at', 'school_guidance', 'guidance_at', 'closing_category', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime', 'assigned_at' => 'datetime', 'scheduled_at' => 'datetime',
            'guidance_at' => 'datetime', 'closed_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(MultiprofessionalAttendance::class, 'request_id');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(MultiprofessionalReferral::class, 'request_id');
    }
}
