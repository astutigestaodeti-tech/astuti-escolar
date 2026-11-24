<?php

namespace App\Http\Controllers;

use App\Academics\GradeAccess;
use App\Attendance\ActiveSearchAlerts;
use App\Attendance\SchoolAbsenceAlerts;
use App\Auth\SchoolAccess;
use App\Auth\WaitlistAccess;
use App\Models\Platform\Membership;
use App\Models\Platform\Municipality;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Multiprofessional\TeamAccess;
use App\Schools\TodayAtSchool;
use App\Students\RegistrationPendencies;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class SchoolController extends Controller
{
    public function index(Request $request, SchoolAccess $access): Response
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
            ->select('id', 'name', 'inep_code', 'latitude', 'longitude')
            ->addSelect(['active_students_count' => $activeStudents, 'classes_count' => $classes]);

        if ($visibleSchoolIds !== null) {
            $query->whereIn('id', $visibleSchoolIds);
        }

        $schools = $query->orderBy('name')->get();
        $todayRows = [];
        // A tela inicial já apresenta o trabalho diário das unidades liberadas.
        // Para gestão municipal o resumo fica no painel da rede.
        if ($visibleSchoolIds !== null) {
            $schoolYear = app(ActiveSearchAlerts::class)->year();
            $offering = app(WaitlistAccess::class)->offeringSchoolIds();
            foreach ($schools as $unit) {
                $todayRows[$unit->id] = $this->todayItems($request, $municipality, $unit, $schoolYear, null, $offering);
            }
        }

        return response()->view('schools.index', [
            'municipality' => $municipality,
            'schools' => $schools,
            'todayRows' => $todayRows,
            'schoolYear' => $year,
            // Com uma unica Prefeitura (o caso do VPS), nao ha tela anterior
            // a esta: o botao voltar levaria de novo a selecao de rede.
            'ocultarVoltar' => $this->municipalityCount($request, $access) <= 1,
        ])->header('Cache-Control', 'private, no-store');
    }

    private function municipalityCount(Request $request, SchoolAccess $access): int
    {
        $user = $request->user();
        $ids = Membership::query()->where('user_id', $user->id)->where('status', 'active')->distinct()->pluck('municipality_id');

        return Municipality::query()->whereIn('id', $ids)->where('status', 'active')->get()
            ->filter(fn (Municipality $municipality): bool => $access->hasMunicipalAccess($user, $municipality))
            ->count();
    }

    public function show(Request $request, string $municipio, string $escola, SchoolAbsenceAlerts $alerts, RegistrationPendencies $pendencies): Response
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($escola);
        Gate::authorize('view', [$school, $municipality]);

        // Resumo do transporte: quantos alunos com matrícula ativa usam o transporte escolar.
        $transportUsers = StudentEnrollment::query()->where('school_id', $school->id)
            ->where('status', 'active')->where('transport_status', 'active')->distinct()->count('student_id');

        // O total da busca ativa só é calculado e exibido para quem pode gerir alunos.
        $absenceAlertCount = null;
        $pendencySummary = null;
        if (Gate::allows('manageStudents', [$school, $municipality])) {
            $absenceAlertCount = $alerts->alertCount($school, $alerts->currentYear(), today());
            $pendencySummary = $pendencies->summary($pendencies->forSchool($school));
        }

        $todayItems = $this->todayItems($request, $municipality, $school, app(ActiveSearchAlerts::class)->year(),
            $pendencySummary, app(WaitlistAccess::class)->offeringSchoolIds());

        return response()->view('schools.show', compact('municipality', 'school', 'transportUsers', 'absenceAlertCount', 'pendencySummary', 'todayItems'))
            ->header('Cache-Control', 'private, no-store');
    }

    private function todayItems(Request $request, Municipality $municipality, School $school, ?SchoolYear $year, ?array $pending, array $offering): array
    {
        $user = $request->user();
        $management = Gate::allows('manageStudents', [$school, $municipality]);
        $assigned = $management ? null : app(GradeAccess::class)->assignedClassIds($user, $municipality, $school);
        $network = app(SchoolAccess::class)->managesMunicipality($user, $municipality);
        $waitlist = $management && in_array($school->id, $offering, true);
        $items = app(TodayAtSchool::class)->forSchool($school, $year, today(), $assigned, $pending, $waitlist, $network);

        // A devolutiva individual continua exclusiva da direção (ou professor da
        // turma em sua ficha). Secretaria recebe só o indicador administrativo.
        if ($management && ! $network) {
            $director = app(TeamAccess::class)->isDirector($user, $municipality, $school);
            foreach (['guidance', 'waiting'] as $type) {
                if (isset($items[$type])) {
                    $items[$type]['links'][0]['params'] = $director ? ['equipe' => $type] : [];
                    $items[$type]['links'][0]['label'] = $director ? 'Consultar fichas' : 'Consultar com a direção';
                }
            }
        }

        return $items;
    }
}
