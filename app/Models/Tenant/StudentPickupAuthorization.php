<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentPickupAuthorization extends TenantModel
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'student_pickup_authorizations';

    protected $fillable = ['id', 'student_id', 'full_name', 'relationship', 'phone', 'document', 'expires_at', 'notes'];

    protected function casts(): array
    {
        return ['expires_at' => 'date'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** A validade inclui o último dia informado. Sem data, não há vencimento. */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->lt(today());
    }

    public function getFormattedPhoneAttribute(): string
    {
        return Guardian::formatPhone($this->phone) ?? $this->phone;
    }
}
