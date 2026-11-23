<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentExit extends TenantModel
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'student_id', 'school_id', 'person_type', 'person_id', 'person_name', 'person_relationship', 'recorded_by', 'exited_at', 'cancelled_at', 'cancelled_by', 'cancel_reason'];

    /** Quem registrou pode desfazer sozinho por este tempo; depois, só a gestão. */
    public const UNDO_MINUTES = 10;

    protected function casts(): array
    {
        return ['exited_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /** Desfazer: o próprio registrador em até 10 minutos; a gestão no mesmo dia. */
    public function canBeUndoneBy(?string $userId, bool $canManage): bool
    {
        if ($this->isCancelled() || ! $this->exited_at->isToday()) {
            return false;
        }

        return $canManage || ($userId !== null && $this->recorded_by === $userId
            && $this->exited_at->greaterThanOrEqualTo(now()->subMinutes(self::UNDO_MINUTES)));
    }

    public function scopeValid($query)
    {
        return $query->whereNull('cancelled_at');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
