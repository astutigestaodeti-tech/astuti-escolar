<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolCalendarDay extends TenantModel
{
    public const KINDS = [
        'holiday' => 'Feriado',
        'recess' => 'Recesso',
        'non_school_day' => 'Dia não letivo',
        'extra_school_day' => 'Dia letivo extra',
        'event' => 'Evento',
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'school_year_id', 'school_id', 'date', 'kind', 'description'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
