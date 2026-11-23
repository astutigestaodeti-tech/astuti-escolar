<?php

namespace App\Policies;

use App\Auth\SchoolAccess;
use App\Auth\WaitlistAccess;
use App\Models\Platform\Municipality;
use App\Models\Tenant\School;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SchoolPolicy
{
    public function __construct(private readonly SchoolAccess $access) {}

    public function viewNetworkPanel(User $user, Municipality $municipality): bool
    {
        return $this->access->managesMunicipality($user, $municipality);
    }

    /** Fila de educação infantil: município, direção ou secretaria da unidade de preferência. */
    public function viewWaitlist(User $user, Municipality $municipality): bool
    {
        return app(WaitlistAccess::class)->canView($user, $municipality);
    }

    public function viewAny(User $user, Municipality $municipality): bool
    {
        return $this->access->hasMunicipalAccess($user, $municipality);
    }

    /** Equidade escolar: apenas diretor vinculado a esta unidade ou gestão municipal. */
    public function viewEquity(User $user, School $school, Municipality $municipality): bool
    {
        if ($this->access->managesMunicipality($user, $municipality)) {
            return true;
        }
        if ($user->status !== 'active' || $municipality->status !== 'active') {
            return false;
        }

        return DB::connection('platform')->table('memberships as membership')
            ->join('school_scopes as scope', 'scope.membership_id', '=', 'membership.id')
            ->where('membership.user_id', $user->id)
            ->where('membership.municipality_id', $municipality->id)
            ->where('membership.status', 'active')
            ->where('membership.role', 'school_director')
            ->where('scope.school_id', $school->id)->exists();
    }

    public function view(User $user, School $school, Municipality $municipality): bool
    {
        return $this->access->maySeeSchool($user, $municipality, $school->id);
    }

    public function manageStudents(User $user, School $school, Municipality $municipality): bool
    {
        return $this->access->mayManageSchool($user, $municipality, $school->id);
    }

    /**
     * Ficha de saúde completa (tipo sanguíneo e alertas de atenção) e edição.
     * Secretaria e direção da escola, e a gestão municipal: decisão de Jefferson
     * em 23/11/2026 (docs/arquitetura/lgpd-by-design.md, seção 7).
     * Quem só consulta a escola vê apenas os alertas graves.
     */
    public function manageHealth(User $user, School $school, Municipality $municipality): bool
    {
        return $this->access->mayManageSchool($user, $municipality, $school->id);
    }

    public function deleteEnrollment(User $user, School $school, Municipality $municipality): bool
    {
        return $this->access->mayDeleteEnrollment($user, $municipality, $school->id);
    }

    // A criacao so sera liberada apos 2FA e implementacao auditada da tarefa 4.
    public function create(User $user, Municipality $municipality): bool
    {
        return false;
    }
}
