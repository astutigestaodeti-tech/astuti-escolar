<?php

namespace App\Models\Tenant;

class StudentHealthProfile extends TenantModel
{
    public const BLOOD_TYPES = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    public $incrementing = false;

    protected $primaryKey = 'student_id';

    protected $keyType = 'string';

    protected $fillable = ['student_id', 'blood_type', 'informed_by', 'informed_on', 'updated_by'];

    protected function casts(): array
    {
        return ['informed_on' => 'date'];
    }

    public function bloodTypeLabel(): string
    {
        return match ($this->blood_type) {
            null => 'Não informado',
            'unknown' => 'Família não sabe',
            default => $this->blood_type,
        };
    }
}
