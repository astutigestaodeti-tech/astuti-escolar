<?php

namespace App\Academics;

use App\Auth\SchoolAccess;
use App\Models\Platform\Municipality;
use App\Models\Tenant\ClassTeacherAssignment;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GradeAccess
{
    public function __construct(private readonly SchoolAccess $access) {}

    public function isElementary(SchoolClass $class): bool
    {
        return Str::contains(Str::lower(Str::ascii((string) $class->stage)), 'fundamental')
            && ! Str::contains(Str::lower(Str::ascii((string) $class->stage)), 'infantil');
    }

    public function isMunicipalManager(User $user, Municipality $municipality): bool
    {
        return $this->access->managesMunicipality($user, $municipality);
    }

    public function isManagement(User $user, Municipality $municipality, School $school): bool
    {
        return $this->access->mayManageSchool($user, $municipality, $school->id);
    }

    public function isAssignedTeacher(
        User $user,
        Municipality $municipality,
        School $school,
        SchoolClass $class,
    ): bool {
        if ($user->status !== 'active' || $municipality->status !== 'active'
            || ! $this->access->maySeeSchool($user, $municipality, $school->id)) {
            return false;
        }

        $teacherMembership = DB::connection('platform')->table('memberships as vinculo')
            ->join('school_scopes as escopo', 'escopo.membership_id', '=', 'vinculo.id')
            ->where('vinculo.user_id', $user->id)
            ->where('vinculo.municipality_id', $municipality->id)
            ->where('vinculo.status', 'active')
            ->where('vinculo.role', 'teacher')
            ->where('escopo.school_id', $school->id)
            ->exists();

        if (! $teacherMembership || ! is_string($user->email) || trim($user->email) === '') {
            return false;
        }

        return ClassTeacherAssignment::query()
            ->join('staff_members as servidor', 'servidor.id', '=', 'class_teacher_assignments.staff_member_id')
            ->where('class_teacher_assignments.school_class_id', $class->id)
            ->where('servidor.school_id', $school->id)
            ->where('servidor.status', 'active')
            ->where('servidor.role', 'teacher')
            ->whereNull('servidor.deleted_at')
            ->whereRaw('lower(btrim(servidor.institutional_email)) = ?', [mb_strtolower(trim($user->email))])
            ->whereDate('class_teacher_assignments.starts_on', '<=', today()->toDateString())
            ->where(function ($query): void {
                $query->whereNull('class_teacher_assignments.ends_on')
                    ->orWhereDate('class_teacher_assignments.ends_on', '>=', today()->toDateString());
            })
            ->exists();
    }

    public function canWrite(User $user, Municipality $municipality, School $school, SchoolClass $class): bool
    {
        return $this->isElementary($class) && (
            $this->isManagement($user, $municipality, $school)
            || $this->isAssignedTeacher($user, $municipality, $school, $class)
        );
    }
}
