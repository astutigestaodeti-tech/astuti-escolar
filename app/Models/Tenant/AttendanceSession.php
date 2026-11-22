<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceSession extends TenantModel
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id', 'school_class_id', 'attendance_date', 'recorded_by', 'recorded_at',
        'last_corrected_by', 'last_corrected_at', 'correction_reason',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'recorded_at' => 'datetime',
            'last_corrected_at' => 'datetime',
        ];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function marks(): HasMany
    {
        return $this->hasMany(AttendanceMark::class, 'attendance_session_id');
    }
}
