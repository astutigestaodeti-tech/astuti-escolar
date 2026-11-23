<?php

namespace App\Auth;

use App\Models\Platform\Membership;
use App\Models\Platform\Municipality;
use App\Models\Tenant\SchoolClass;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class WaitlistAccess
{
    public function isMunicipalManager(User $user, Municipality $municipality): bool
    {
        return $this->eligibleAccount($user, $municipality)
            && Membership::query()
                ->where('user_id', $user->id)
                ->where('municipality_id', $municipality->id)
                ->where('status', 'active')
                ->where('role', 'municipal_manager')
                ->exists();
    }

    /**
     * Escolas ativas com turmas de educação infantil em atividade. A etapa é
     * verificada pelo cadastro de turmas, sem confiar nas opções do navegador.
     *
     * @return list<string>
     */
    public function offeringSchoolIds(): array
    {
        return SchoolClass::query()->join('schools', 'schools.id', '=', 'school_classes.school_id')
            ->where('schools.status', 'active')
            ->where('school_classes.status', 'active')
            ->where(function ($q): void {
                $q->where('school_classes.stage', 'ilike', '%infantil%')
                    ->orWhere('school_classes.stage', 'ilike', '%creche%')
                    ->orWhere('school_classes.stage', 'ilike', '%pré-escola%')
                    ->orWhere('school_classes.stage', 'ilike', '%pre-escola%')
                    ->orWhere('school_classes.name', 'ilike', 'berç%')
                    ->orWhere('school_classes.name', 'ilike', 'berc%')
                    ->orWhere('school_classes.name', 'ilike', 'maternal%')
                    ->orWhere('school_classes.name', 'ilike', 'pré%')
                    ->orWhere('school_classes.name', 'ilike', 'pre %');
            })
            ->distinct()->pluck('school_classes.school_id')->all();
    }

    /** @return list<string> */
    public function scopedSchoolIds(User $user, Municipality $municipality, array $roles): array
    {
        if (! $this->eligibleAccount($user, $municipality)) {
            return [];
        }

        $memberships = Membership::query()
            ->where('user_id', $user->id)
            ->where('municipality_id', $municipality->id)
            ->where('status', 'active')
            ->whereIn('role', $roles)
            ->pluck('id');

        if ($memberships->isEmpty()) {
            return [];
        }

        return DB::connection('platform')->table('school_scopes')
            ->whereIn('membership_id', $memberships)
            ->pluck('school_id')->unique()->values()->all();
    }

    /** @return list<string> */
    public function visibleSchoolIds(User $user, Municipality $municipality): array
    {
        $eligible = $this->offeringSchoolIds();

        if ($this->isMunicipalManager($user, $municipality)) {
            return $eligible;
        }

        return array_values(array_intersect(
            $eligible,
            $this->scopedSchoolIds($user, $municipality, ['school_director', 'school_secretary']),
        ));
    }

    /** @return list<string> */
    public function writableSchoolIds(User $user, Municipality $municipality): array
    {
        $eligible = $this->offeringSchoolIds();

        if ($this->isMunicipalManager($user, $municipality)) {
            return $eligible;
        }

        return array_values(array_intersect(
            $eligible,
            $this->scopedSchoolIds($user, $municipality, ['school_secretary']),
        ));
    }

    public function canView(User $user, Municipality $municipality): bool
    {
        return $this->isMunicipalManager($user, $municipality)
            || $this->visibleSchoolIds($user, $municipality) !== [];
    }

    public function canCreate(User $user, Municipality $municipality): bool
    {
        return $this->writableSchoolIds($user, $municipality) !== [];
    }

    private function eligibleAccount(User $user, Municipality $municipality): bool
    {
        return $user->status === 'active' && $municipality->status === 'active';
    }
}
