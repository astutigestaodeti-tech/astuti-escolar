<?php

namespace App\Models\Tenant;

class StaffManagerCensus extends TenantModel
{
    protected $table = 'staff_manager_census';

    protected $primaryKey = 'staff_member_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['staff_member_id', 'position', 'access_criterion', 'employment_status', 'updated_by'];

    protected function casts(): array
    {
        return ['position' => 'integer', 'access_criterion' => 'integer', 'employment_status' => 'integer'];
    }

    public static function eligible(StaffMember $member): bool
    {
        return in_array($member->role, ['director', 'coordinator'], true);
    }
}
