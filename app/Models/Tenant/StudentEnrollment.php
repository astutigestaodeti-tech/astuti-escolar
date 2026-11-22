<?php

namespace App\Models\Tenant;

use App\Models\Tenant\Concerns\HasSchoolHistory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentEnrollment extends TenantModel
{
    use HasSchoolHistory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'student_id',
        'school_id',
        'school_year_id',
        'school_class_id',
        'status',
        'started_at',
        'ended_at',
        'transport_status',
        'transport_route',
        'transport_stop',
        'transport_adapted_vehicle',
    ];

    protected function casts(): array
    {
        return ['started_at' => 'date', 'ended_at' => 'date', 'transport_adapted_vehicle' => 'boolean'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class)->withTrashed();
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id')->withTrashed();
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class)->withTrashed();
    }
}
