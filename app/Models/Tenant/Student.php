<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends TenantModel
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'full_name', 'birth_date'];

    protected function casts(): array
    {
        return ['birth_date' => 'date'];
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }
}
