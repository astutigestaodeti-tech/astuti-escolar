<?php

namespace App\Http\Controllers;

use App\Auth\SchoolAccess;
use App\Models\Tenant\School;
use App\Models\Tenant\Student;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SchoolController extends Controller
{
    public function index(Request $request, SchoolAccess $access): View
    {
        $municipality = $request->attributes->get('astuti.municipality');
        Gate::authorize('viewAny', [School::class, $municipality]);

        $visibleSchoolIds = $access->visibleSchoolIds($request->user(), $municipality);
        $activeStudents = Student::query()
            ->selectRaw('COUNT(*)')
            ->whereHas('enrollments', function (Builder $enrollments): void {
                $enrollments->whereColumn('student_enrollments.school_id', 'schools.id')
                    ->where('student_enrollments.status', 'active');
            });

        $query = School::query()->where('status', 'active')
            ->select('id', 'name')
            ->addSelect(['active_students_count' => $activeStudents]);

        if ($visibleSchoolIds !== null) {
            $query->whereIn('id', $visibleSchoolIds);
        }

        return view('schools.index', [
            'municipality' => $municipality,
            'schools' => $query->orderBy('name')->get(),
        ]);
    }

    public function show(Request $request, string $municipio, string $escola): View
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($escola);
        Gate::authorize('view', [$school, $municipality]);

        return view('schools.show', ['municipality' => $municipality, 'school' => $school]);
    }
}
