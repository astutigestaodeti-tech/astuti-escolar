<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Guardian extends TenantModel
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'full_name', 'cpf', 'phone', 'email'];

    public function getFormattedPhoneAttribute(): string
    {
        $digits = preg_replace('/\\D/', '', (string) $this->phone);
        $ddd = substr($digits, 0, 2);
        $local = substr($digits, 2);
        $prefix = strlen($digits) === 11 ? 5 : 4;

        return '('.$ddd.') '.substr($local, 0, $prefix).'-'.substr($local, $prefix);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_guardians')
            ->withPivot(['id', 'relationship', 'is_legal_guardian', 'is_primary_contact'])
            ->withTimestamps();
    }
}
