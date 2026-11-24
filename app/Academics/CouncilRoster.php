<?php

namespace App\Academics;

use App\Models\Tenant\AcademicPeriod;
use App\Models\Tenant\AttendanceMark;
use App\Models\Tenant\AttendanceSession;
use App\Models\Tenant\ClassCouncil;
use App\Models\Tenant\ClassCouncilStudent;
use App\Models\Tenant\CurricularComponent;
use App\Models\Tenant\GradeEntry;
use App\Models\Tenant\LiteracyAssessment;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\StudentEnrollment;
use Illuminate\Database\Eloquent\Builder;

final class CouncilRoster
{
    public function enrollments(SchoolClass $class, AcademicPeriod $period): Builder
    {
        return StudentEnrollment::query()->where('school_class_id', $class->id)
            ->where('school_year_id', $class->school_year_id)->where('school_id', $class->school_id)
            ->whereHas('student', fn ($q) => $q->whereNull('deleted_at'))
            ->where(fn ($q) => $q->whereNull('started_at')->orWhere('started_at', '<=', $period->ends_on->toDateString()))
            ->where(fn ($q) => $q->where('status', 'active')->orWhere('ended_at', '>=', $period->starts_on->toDateString()));
    }

    public function forPeriod(SchoolClass $class, AcademicPeriod $period, ?ClassCouncil $council): array
    {
        $enrollments = $this->enrollments($class, $period)->with('student:id,full_name')->get()->sortBy('student.full_name')->values();
        $ids = $enrollments->pluck('id');
        $entries = GradeEntry::query()->where('school_class_id', $class->id)->where('academic_period_id', $period->id)
            ->whereIn('student_enrollment_id', $ids)->get();
        $components = CurricularComponent::query()->where(fn ($q) => $q->where('active', true)->orWhereIn('id', $entries->pluck('curricular_component_id')))
            ->orderBy('display_order')->orderBy('name')->get()->keyBy('id');
        $grades = $entries->groupBy('student_enrollment_id');
        $sessions = AttendanceSession::query()->where('school_class_id', $class->id)
            ->whereBetween('attendance_date', [$period->starts_on->toDateString(), $period->ends_on->toDateString()])
            ->where('attendance_date', '<=', today()->toDateString())->get(['id', 'attendance_date'])->keyBy('id');
        $marks = AttendanceMark::query()->whereIn('attendance_session_id', $sessions->keys())
            ->whereIn('student_id', $enrollments->pluck('student_id'))->whereIn('status', ['absent', 'justified'])
            ->get(['attendance_session_id', 'student_id'])->groupBy('student_id');
        $literacy = $period->kind !== 'bimestre' ? collect() : LiteracyAssessment::query()->where('school_class_id', $class->id)
            ->where('term', $period->number)->whereIn('student_enrollment_id', $ids)->get()->keyBy('student_enrollment_id');
        $records = $council === null ? collect() : ClassCouncilStudent::query()->where('class_council_id', $council->id)->get()->keyBy('student_enrollment_id');

        return $enrollments->map(function (StudentEnrollment $e) use ($grades, $components, $marks, $sessions, $literacy, $records): array {
            $absences = $marks->get($e->student_id, collect())->filter(function ($mark) use ($sessions, $e): bool {
                $date = $sessions[$mark->attendance_session_id]->attendance_date;

                return ($e->started_at === null || $date->gte($e->started_at))
                    && ($e->ended_at === null || $date->lte($e->ended_at));
            })->count();

            return ['enrollment' => $e, 'grades' => $grades->get($e->id, collect())->keyBy('curricular_component_id'),
                'components' => $components, 'absences' => $absences, 'literacy' => $literacy->get($e->id), 'record' => $records->get($e->id)];
        })->all();
    }
}
