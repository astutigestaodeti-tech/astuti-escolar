<?php

namespace App\Academics;

use App\Models\Platform\Municipality;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\User;

final class LessonAccess
{
    public function __construct(private readonly GradeAccess $grades, private readonly PedagogicalAccess $pedagogical) {}

    /** Secretaria municipal/escolar só consulta. Professor precisa de atribuição vigente. */
    public function forClass(User $user, Municipality $municipality, School $school, SchoolClass $class): array
    {
        $director = $this->pedagogical->directs($user, $municipality, $school);
        $teacher = ! $director && $this->grades->isAssignedTeacher($user, $municipality, $school, $class);

        return [
            'view' => $director || $teacher || $this->grades->isManagement($user, $municipality, $school),
            'write' => $director || $teacher,
            'director' => $director,
        ];
    }
}
