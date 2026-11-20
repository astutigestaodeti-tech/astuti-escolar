<?php

namespace App\Models\Tenant;

use App\Models\Tenant\Concerns\HasSchoolHistory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolYear extends TenantModel
{
    use HasSchoolHistory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'year', 'start_date', 'end_date', 'status'];

    protected function casts(): array
    {
        return ['year' => 'integer', 'start_date' => 'date', 'end_date' => 'date'];
    }

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }
}
