<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentGuardian extends TenantModel
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'student_guardians';

    protected $fillable = ['id', 'student_id', 'guardian_id', 'relationship', 'is_legal_guardian', 'is_primary_contact'];

    protected function casts(): array
    {
        return ['is_legal_guardian' => 'boolean', 'is_primary_contact' => 'boolean'];
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
