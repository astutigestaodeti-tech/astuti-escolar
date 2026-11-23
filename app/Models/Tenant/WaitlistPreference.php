<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaitlistPreference extends TenantModel
{
    public $timestamps = false;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'waitlist_request_id', 'school_id', 'preference_order'];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class)->withTrashed();
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(WaitlistRequest::class, 'waitlist_request_id');
    }
}
