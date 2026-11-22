<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceMark extends TenantModel
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id', 'attendance_session_id', 'student_id', 'status', 'observation',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'attendance_session_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
