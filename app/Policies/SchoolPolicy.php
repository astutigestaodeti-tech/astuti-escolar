<?php

namespace App\Policies;

use App\Auth\SchoolAccess;
use App\Models\Platform\Municipality;
use App\Models\Tenant\School;
use App\Models\User;

class SchoolPolicy
{
    public function __construct(private readonly SchoolAccess $access) {}

    public function viewAny(User $user, Municipality $municipality): bool
    {
        return $this->access->hasMunicipalAccess($user, $municipality);
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
