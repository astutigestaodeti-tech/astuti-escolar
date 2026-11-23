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

    /** Acesso exclusivo a um vínculo ativo de gestão municipal, sem herdar perfis escolares. */
    public function managesMunicipality(User $user, Municipality $municipality): bool
    {
        return $user->status === 'active' && $municipality->status === 'active'
            && Membership::query()->where('user_id', $user->id)->where('municipality_id', $municipality->id)
                ->where('status', 'active')->where('role', 'municipal_manager')->exists();
    }

    public function hasMunicipalAccess(User $user, Municipality $municipality): bool
    {
        return $this->visibleSchoolIds($user, $municipality) !== [];
    }

    /**
     * Quem pode incluir e alterar alunos da escola: o gestor municipal, ou a
     * direção e a secretaria com escopo nesta escola. Professores não gravam.
     * O segundo fator confirmado é exigido à parte, na rota.
     */
    public function mayManageSchool(User $user, Municipality $municipality, string $schoolId): bool
    {
        if ($user->status !== 'active' || $municipality->status !== 'active') {
            return false;
        }

        $memberships = Membership::query()
            ->where('user_id', $user->id)
            ->where('municipality_id', $municipality->id)
            ->where('status', 'active')
            ->whereIn('role', ['municipal_manager', 'school_director', 'school_secretary'])
            ->get();

        if ($memberships->contains('role', 'municipal_manager')) {
            return true;
        }

        if ($memberships->isEmpty()) {
            return false;
        }

        return DB::connection('platform')->table('school_scopes')
            ->whereIn('membership_id', $memberships->pluck('id')->all())
            ->where('school_id', $schoolId)
            ->exists();
    }

    /** Exclusão por erro de cadastro cabe somente à direção da unidade ou gestão municipal. */
    public function mayDeleteEnrollment(User $user, Municipality $municipality, string $schoolId): bool
    {
        if ($user->status !== 'active' || $municipality->status !== 'active') {
            return false;
        }

        $memberships = Membership::query()
            ->where('user_id', $user->id)
            ->where('municipality_id', $municipality->id)
            ->where('status', 'active')
            ->whereIn('role', ['municipal_manager', 'school_director'])
            ->get();

        if ($memberships->contains('role', 'municipal_manager')) {
            return true;
        }

        return $memberships->isNotEmpty() && DB::connection('platform')->table('school_scopes')
            ->whereIn('membership_id', $memberships->pluck('id')->all())
            ->where('school_id', $schoolId)
            ->exists();
    }

    public function maySeeSchool(User $user, Municipality $municipality, string $schoolId): bool
    {
        $schoolIds = $this->visibleSchoolIds($user, $municipality);

        return $schoolIds === null || in_array($schoolId, $schoolIds, true);
    }
}
