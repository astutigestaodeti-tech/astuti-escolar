<?php

namespace App\Http\Controllers;

use App\Academics\GradeAccess;
use App\Academics\ReportCard;
use App\Models\Tenant\School;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class StudentReportCardController extends Controller
{
    public function show(
        Request $request,
        string $municipio,
        string $escola,
        string $aluno,
        ReportCard $report,
        GradeAccess $access,
    ): Response {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($escola);
        Gate::authorize('view', [$school, $municipality]);

        // Mesmo alcance da ficha, nunca uma matrícula de outra escola.
        $student = Student::query()->whereHas('enrollments', function ($query) use ($school): void {
            $query->where('school_id', $school->id)->where('status', 'active');
        })->findOrFail($aluno);

        $enrollment = StudentEnrollment::query()
            ->where('student_id', $student->id)
            ->where('school_id', $school->id)
            ->where('status', 'active')
            ->with(['schoolYear', 'schoolClass'])
            ->get()
            ->filter(fn (StudentEnrollment $item): bool => $item->schoolClass !== null && $access->isElementary($item->schoolClass))
            ->sortByDesc(fn (StudentEnrollment $item): int => (int) ($item->schoolYear?->year ?? 0))
            ->first();
        abort_if($enrollment === null, 404);

        $data = $report->build($school, $enrollment->schoolYear, $enrollment);

        return response()->view('academics.report-card', [
            'municipality' => $municipality,
            'school' => $school,
            'student' => $student,
            'enrollment' => $enrollment,
            'data' => $data,
        ])->header('Cache-Control', 'private, no-store');
    }
}
