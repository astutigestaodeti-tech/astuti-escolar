<?php

namespace App\Academics;

use App\Models\Platform\Municipality;
use App\Models\Tenant\School;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class PedagogicalAccess
{
    /** Secretaria municipal não herda permissão de escrita do conselho. */
    public function directs(User $user, Municipality $municipality, School $school): bool
    {
        if ($user->status !== 'active' || $municipality->status !== 'active') {
            return false;
        }

        return DB::connection('platform')->table('memberships as membership')
            ->join('school_scopes as scope', 'scope.membership_id', '=', 'membership.id')
            ->where('membership.user_id', $user->id)
            ->where('membership.municipality_id', $municipality->id)
            ->where('membership.status', 'active')
            ->whereIn('membership.role', ['school_director', 'school_coordinator'])
            ->where('scope.school_id', $school->id)->exists();
    }
}
