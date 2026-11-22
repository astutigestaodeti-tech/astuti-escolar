<?php

namespace App\Models\Tenant;

use App\Models\Tenant\Concerns\HasSchoolHistory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends TenantModel
{
    use HasSchoolHistory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id', 'full_name', 'birth_date', 'ra',
        'address_street', 'address_number', 'address_district', 'postal_code', 'residence_zone',
    ];

    protected function casts(): array
    {
        return ['birth_date' => 'date'];
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }
}
