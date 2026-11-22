<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentExit extends TenantModel
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'student_id', 'school_id', 'person_type', 'person_id', 'person_name', 'person_relationship', 'recorded_by', 'exited_at'];

    protected function casts(): array
    {
        return ['exited_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
