<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicPeriod extends TenantModel
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id', 'school_year_id', 'kind', 'number', 'starts_on', 'ends_on',
        'closed_at', 'closed_by', 'close_reason',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }
}
