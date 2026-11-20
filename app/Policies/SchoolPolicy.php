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

    // A criacao so sera liberada apos 2FA e implementacao auditada da tarefa 4.
    public function create(User $user, Municipality $municipality): bool
    {
        return false;
    }
}
