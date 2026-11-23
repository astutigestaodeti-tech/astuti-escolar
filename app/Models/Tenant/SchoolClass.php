<?php

namespace App\Models\Tenant;

use App\Models\Tenant\Concerns\HasSchoolHistory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolClass extends TenantModel
{
    use HasSchoolHistory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id', 'school_id', 'school_year_id', 'name', 'stage', 'modality', 'shift', 'daily_hours', 'capacity', 'status',
    ];

    protected function casts(): array
    {
        return ['daily_hours' => 'decimal:1'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class)->withTrashed();
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class)->withTrashed();
    }
}
