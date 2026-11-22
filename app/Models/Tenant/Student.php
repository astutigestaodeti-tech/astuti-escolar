<?php

namespace App\Models\Tenant;

use App\Models\Tenant\Concerns\HasSchoolHistory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Student extends TenantModel
{
    use HasSchoolHistory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id', 'full_name', 'birth_date', 'ra',
        'address_street', 'address_number', 'address_complement', 'address_district', 'address_city', 'address_state', 'postal_code', 'residence_zone',
        'social_name', 'sex', 'race_color', 'nationality', 'birthplace_city', 'birthplace_state', 'cpf',
    ];

    protected function casts(): array
    {
        return ['birth_date' => 'date'];
    }

    /** Anos completos numa data; null sem nascimento ou com data anterior a ele. */
    public function ageOn(DateTimeInterface $date): ?int
    {
        if ($this->birth_date === null || $this->birth_date->greaterThan($date)) {
            return null;
        }

        return (int) $this->birth_date->diffInYears($date);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    public function primaryGuardianLink(): HasOne
    {
        return $this->hasOne(StudentGuardian::class)->where('is_primary_contact', true);
    }
}
