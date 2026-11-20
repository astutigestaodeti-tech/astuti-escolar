<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentEnrollment extends TenantModel
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'student_id',
        'school_id',
        'school_year_id',
        'status',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return ['started_at' => 'date', 'ended_at' => 'date'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }
}
