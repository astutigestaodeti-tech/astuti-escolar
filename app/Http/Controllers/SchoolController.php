<?php

namespace App\Http\Controllers;

use App\Auth\SchoolAccess;
use App\Models\Platform\Membership;
use App\Models\Platform\Municipality;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
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

        // Turmas do ano letivo em curso: o mais recente que nao foi encerrado.
        $year = SchoolYear::query()->where('status', '!=', 'completed')->orderByDesc('year')->first();

        // Aluno com matricula ativa conta uma vez, mesmo com vinculos em dois anos.
        $activeStudents = Student::query()
            ->selectRaw('COUNT(*)')
            ->whereHas('enrollments', function (Builder $enrollments): void {
                $enrollments->whereColumn('student_enrollments.school_id', 'schools.id')
                    ->where('student_enrollments.status', 'active');
            });

        $classes = SchoolClass::query()
            ->selectRaw('COUNT(*)')
            ->whereColumn('school_classes.school_id', 'schools.id')
            ->where('school_classes.status', '!=', 'completed')
            ->when($year !== null, fn (Builder $query) => $query->where('school_classes.school_year_id', $year->id));

        $query = School::query()->where('status', 'active')
            ->select('id', 'name', 'inep_code')
            ->addSelect(['active_students_count' => $activeStudents, 'classes_count' => $classes]);

        if ($visibleSchoolIds !== null) {
            $query->whereIn('id', $visibleSchoolIds);
        }

        return view('schools.index', [
            'municipality' => $municipality,
            'schools' => $query->orderBy('name')->get(),
            'schoolYear' => $year,
            // Com uma unica Prefeitura (o caso do VPS), nao ha tela anterior
            // a esta: o botao voltar levaria de novo a selecao de rede.
            'ocultarVoltar' => $this->municipalityCount($request, $access) <= 1,
        ]);
    }

    private function municipalityCount(Request $request, SchoolAccess $access): int
    {
        $user = $request->user();
        $ids = Membership::query()->where('user_id', $user->id)->where('status', 'active')->distinct()->pluck('municipality_id');

        return Municipality::query()->whereIn('id', $ids)->where('status', 'active')->get()
            ->filter(fn (Municipality $municipality): bool => $access->hasMunicipalAccess($user, $municipality))
            ->count();
    }

    public function show(Request $request, string $municipio, string $escola): View
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($escola);
        Gate::authorize('view', [$school, $municipality]);

        return view('schools.show', ['municipality' => $municipality, 'school' => $school]);
    }
}
