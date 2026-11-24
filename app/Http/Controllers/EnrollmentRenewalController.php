<?php

namespace App\Http\Controllers;

use App\Academics\EnrollmentRenewal;
use App\Audit\AuditTrail;
use App\Auth\SchoolAccess;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EnrollmentRenewalController extends Controller
{
    public function index(Request $request, string $municipio, string $escola, string $turma, EnrollmentRenewal $renewal): Response
    {
        [$municipality, $school, $class] = $this->authorizedClass($request, $escola, $turma);
        $data = $renewal->preview($class, app(SchoolAccess::class)->manageableSchoolIds($request->user(), $municipality));
        app(AuditTrail::class)->record('tenant', 'enrollment.renewal.viewed', SchoolClass::class, $class->id);

        return response()->view('academics.renewal', compact('municipality', 'school', 'class', 'data'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, string $municipio, string $escola, string $turma, EnrollmentRenewal $renewal): RedirectResponse
    {
        [$municipality, $school, $class] = $this->authorizedClass($request, $escola, $turma);
        $input = $request->validate([
            'ano_destino' => ['required', 'uuid'],
            'destinos' => ['nullable', 'array'],
            'destinos.*' => ['nullable', 'uuid'],
        ]);
        $selected = $input['destinos'] ?? [];
        foreach (array_keys($selected) as $id) {
            if (! Str::isUuid($id)) {
                throw ValidationException::withMessages(['destinos' => 'Seleção de matrículas inválida.']);
            }
        }

        $summary = $renewal->renew($class, $selected, $input['ano_destino'], app(SchoolAccess::class)->manageableSchoolIds($request->user(), $municipality));

        return redirect()->route('school.classes.renewal.index', ['municipio' => $municipality->slug, 'escola' => $school->id, 'turma' => $class->id])
            ->with('renewal_summary', $summary);
    }

    private function authorizedClass(Request $request, string $schoolId, string $classId): array
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($schoolId);
        Gate::authorize('manageStudents', [$school, $municipality]);
        $class = SchoolClass::query()->where('school_id', $school->id)->whereIn('status', ['active', 'completed'])
            ->whereHas('schoolYear', fn ($query) => $query->whereNull('deleted_at'))->with('schoolYear')->findOrFail($classId);

        return [$municipality, $school, $class];
    }
}
