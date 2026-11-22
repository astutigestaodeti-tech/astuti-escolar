<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Guardian extends TenantModel
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'full_name', 'cpf', 'phone', 'email', 'phone_secondary', 'workplace', 'work_phone', 'rg_number', 'rg_state'];

    protected function casts(): array
    {
        return ['photo_updated_at' => 'datetime'];
    }

    public function getFormattedPhoneAttribute(): string
    {
        return self::formatPhone($this->phone) ?? '';
    }

    /** "11987654321" vira "(11) 98765-4321"; "1133334444" vira "(11) 3333-4444". */
    public static function formatPhone(?string $phone): ?string
    {
        $digits = preg_replace('/\\D/', '', (string) $phone);
        if ($digits === '' || $digits === null) {
            return null;
        }
        $ddd = substr($digits, 0, 2);
        $local = substr($digits, 2);
        $prefix = strlen($digits) === 11 ? 5 : 4;

        return '('.$ddd.') '.substr($local, 0, $prefix).'-'.substr($local, $prefix);
    }

    /** Celular (DDD + 9 + 8 dígitos) abre o WhatsApp; fixo não tem esse atalho. */
    public static function whatsappLink(?string $phone): ?string
    {
        $digits = preg_replace('/\\D/', '', (string) $phone);

        return is_string($digits) && preg_match('/\\A\\d{2}9\\d{8}\\z/', $digits) === 1 ? 'https://wa.me/55'.$digits : null;
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_guardians')
            ->withPivot(['id', 'relationship', 'is_legal_guardian', 'is_primary_contact'])
            ->withTimestamps();
    }
}
