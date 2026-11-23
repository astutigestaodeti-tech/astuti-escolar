<?php

namespace App\Models\Tenant;

use App\Models\Tenant\Concerns\HasSchoolHistory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffMember extends TenantModel
{
    use HasSchoolHistory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id', 'school_id', 'full_name', 'registration_number', 'role',
        'institutional_email', 'phone', 'status',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class)->withTrashed();
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(ClassTeacherAssignment::class);
    }

    public function formattedPhone(): ?string
    {
        $digits = (string) $this->phone;

        return match (strlen($digits)) {
            11 => sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7)),
            10 => sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 4), substr($digits, 6)),
            default => $digits === '' ? null : $digits,
        };
    }
}
