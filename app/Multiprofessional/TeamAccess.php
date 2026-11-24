<?php

namespace App\Multiprofessional;

use App\Academics\GradeAccess;
use App\Models\Platform\Membership;
use App\Models\Platform\Municipality;
use App\Models\Tenant\MultiprofessionalRequest;
use App\Models\Tenant\School;
use App\Models\Tenant\StudentEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TeamAccess
{
    public const ROLES = [
        'psicologo' => 'psychology',
        'assistente_social' => 'social_work',
        'pedagogo_equipe' => 'pedagogy',
    ];

    public function areas(User $user, Municipality $municipality): array
    {
        if ($user->status !== 'active' || $municipality->status !== 'active') {
            return [];
        }

        $roles = Membership::query()->where('user_id', $user->id)->where('municipality_id', $municipality->id)
            ->where('status', 'active')->pluck('role');
        // Contas da equipe são separadas das contas escolares e administrativas.
        if ($roles->intersect(['municipal_manager', 'school_director', 'school_secretary', 'teacher'])->isNotEmpty()) {
            return [];
        }

        return $roles->filter(fn (string $role): bool => isset(self::ROLES[$role]))
            ->map(fn (string $role): string => self::ROLES[$role])->unique()->values()->all();
    }

    public function isTeam(User $user, Municipality $municipality): bool
    {
        return $this->areas($user, $municipality) !== [];
    }

    public function isDirector(User $user, Municipality $municipality, School $school): bool
    {
        if ($user->status !== 'active' || $municipality->status !== 'active') {
            return false;
        }

        return DB::connection('platform')->table('memberships as membership')
            ->join('school_scopes as scope', 'scope.membership_id', '=', 'membership.id')
            ->where('membership.user_id', $user->id)->where('membership.municipality_id', $municipality->id)
            ->where('membership.status', 'active')->where('membership.role', 'school_director')
            ->where('scope.school_id', $school->id)->exists();
    }

    public function schoolAccess(User $user, Municipality $municipality, School $school, StudentEnrollment $entry): bool
    {
        if ($entry->school_id !== $school->id || $entry->status !== 'active' || $entry->ended_at !== null) {
            return false;
        }
        if ($this->isDirector($user, $municipality, $school)) {
            return true;
        }
        $class = $entry->schoolClass;

        return $class !== null && $class->school_id === $school->id && $class->school_year_id === $entry->school_year_id && $class->deleted_at === null
            && $class->status === 'active'
            && app(GradeAccess::class)->isAssignedTeacher($user, $municipality, $school, $class);
    }

    public function mayOpen(User $user, Municipality $municipality, MultiprofessionalRequest $entry): bool
    {
        $areas = $this->areas($user, $municipality);

        return $areas !== [] && (
            ($entry->assigned_to === null && $entry->status === 'open'
                && ($entry->area === 'undetermined' || in_array($entry->area, $areas, true)))
            || ($entry->assigned_to === $user->id && in_array($entry->area, $areas, true))
        );
    }
}
