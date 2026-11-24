<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonRecord extends TenantModel
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id', 'school_class_id', 'lesson_date', 'curricular_component_id', 'content', 'activity',
        'bncc_codes', 'recorded_by', 'updated_by', 'correction_reason', 'version', 'operation_id',
    ];

    protected function casts(): array
    {
        return ['lesson_date' => 'date', 'bncc_codes' => 'array', 'version' => 'integer'];
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(CurricularComponent::class, 'curricular_component_id');
    }
}
