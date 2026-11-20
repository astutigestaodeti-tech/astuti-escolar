<?php

namespace App\Auth;

use App\Models\Platform\Membership;
use App\Models\Platform\Municipality;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SchoolAccess
{
    /** @return list<string>|null Null significa todas as escolas deste municipio. */
    public function visibleSchoolIds(User $user, Municipality $municipality): ?array
    {
        if ($user->status !== 'active' || $municipality->status !== 'active') {
            return [];
        }

        $memberships = Membership::query()
            ->where('user_id', $user->id)
            ->where('municipality_id', $municipality->id)
            ->where('status', 'active')
            ->whereIn('role', ['municipal_manager', 'school_director', 'school_secretary', 'teacher'])
            ->get();

        if ($memberships->isEmpty()) {
            return [];
        }

        if ($memberships->contains('role', 'municipal_manager')) {
            return null;
        }

        return DB::connection('platform')->table('school_scopes')
            ->whereIn('membership_id', $memberships->pluck('id')->all())
            ->pluck('school_id')
            ->unique()
            ->values()
            ->all();
    }

    public function hasMunicipalAccess(User $user, Municipality $municipality): bool
    {
        return $this->visibleSchoolIds($user, $municipality) !== [];
    }

    public function maySeeSchool(User $user, Municipality $municipality, string $schoolId): bool
    {
        $schoolIds = $this->visibleSchoolIds($user, $municipality);

        return $schoolIds === null || in_array($schoolId, $schoolIds, true);
    }
}
