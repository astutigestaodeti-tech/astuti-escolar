<?php

namespace App\Http\Controllers;

use App\Audit\AuditTrail;
use App\Geo\Distance;
use App\Geo\RouteDistance;
use App\Models\Tenant\IssuedDocument;
use App\Models\Tenant\MultiprofessionalRequest;
use App\Models\Tenant\School;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentCensus;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\StudentExit;
use App\Models\Tenant\StudentGuardian;
use App\Models\Tenant\StudentHealthAlert;
use App\Models\Tenant\StudentHealthProfile;
use App\Models\Tenant\StudentPickupAuthorization;
use App\Models\Tenant\StudentSpecialEducation;
use App\Multiprofessional\TeamAccess;
use App\Multiprofessional\TeamIndicators;
use App\Security\TwoFactorSession;
use App\Students\StudentNavigation;
use App\Students\StudentSelection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class SchoolStudentController extends Controller
{
    /** Filtro da lista => situação gravada pela importação do transporte (null = sem informação). */
    public const TRANSPORT_FILTERS = StudentSelection::TRANSPORT_FILTERS;

    public function index(Request $request, string $municipio, string $escola): Response|RedirectResponse
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($escola);
        Gate::authorize('view', [$school, $municipality]);

        $validated = $request->validate([
            'busca' => ['nullable', 'string', 'max:120'],
            'equipe' => ['nullable', 'in:guidance,waiting'],
            'transporte' => ['nullable', 'in:'.implode(',', array_keys(self::TRANSPORT_FILTERS))],
        ]);
        $search = trim((string) ($validated['busca'] ?? ''));
        $transport = (string) ($validated['transporte'] ?? '');

        $activeHere = function ($enrollments) use ($school): void {
            StudentSelection::activeHere($enrollments, $school);
        };
        $query = StudentSelection::query($school);
        $teamFilter = $validated['equipe'] ?? null;
        if ($teamFilter !== null) {
            abort_unless(app(TeamAccess::class)->isDirector($request->user(), $municipality, $school), 403);
            if (! app(TwoFactorSession::class)->isVerified($request, $request->user())) {
                return redirect()->guest(route('two-factor.challenge'));
            }
            $query->whereIn('students.id', app(TeamIndicators::class)->schoolActionQuery($school, today(), $teamFilter)->select('student_id'));
            app(AuditTrail::class)->record('tenant', 'multiprofessional.school_actions.viewed', School::class, $school->id);
            $students = $query->orderBy('full_name')->orderBy('students.id')
                ->paginate(25, ['students.id', 'students.full_name'])->withQueryString();

            return response()->view('students.team-actions', compact('municipality', 'school', 'students', 'teamFilter'))
                ->header('Cache-Control', 'private, no-store');
        }

        $query = StudentSelection::query($school, $search);

        // Contagem por situação do transporte, sempre sobre a busca atual.
        $transportCounts = (clone $query)->join('student_enrollments as transporte_matricula', function ($join) use ($school): void {
            $join->on('transporte_matricula.student_id', '=', 'students.id')
                ->where('transporte_matricula.school_id', $school->id)
                ->where('transporte_matricula.status', 'active');
        })->selectRaw("coalesce(transporte_matricula.transport_status, 'sem_informacao') as situacao, count(distinct students.id) as total")
            ->groupBy('situacao')->pluck('total', 'situacao')->map(fn ($total): int => (int) $total)->all();
        $transportCounts['adaptado'] = (clone $query)->whereHas('enrollments', function (Builder $enrollments) use ($activeHere): void {
            $activeHere($enrollments);
            $enrollments->where('transport_adapted_vehicle', true);
        })->count();

        StudentSelection::filterTransport($query, $school, $transport);
        $students = StudentSelection::ordered($query)->with(['enrollments' => $activeHere])->paginate(25)->withQueryString();

        $canManage = Gate::allows('manageStudents', [$school, $municipality]);
        $transportFilters = self::TRANSPORT_FILTERS;

        return response()->view('students.index', compact('municipality', 'school', 'students', 'search', 'canManage', 'transport', 'transportCounts', 'transportFilters'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, string $municipio, string $escola, string $aluno): Response
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($escola);
        Gate::authorize('view', [$school, $municipality]);

        $student = Student::query()->whereHas('enrollments', function (Builder $enrollments) use ($school): void {
            $enrollments->where('school_id', $school->id)->where('status', 'active');
        })->findOrFail($aluno);

        $filters = $request->validate([
            'busca' => ['nullable', 'string', 'max:120'],
            'transporte' => ['nullable', 'in:'.implode(',', array_keys(self::TRANSPORT_FILTERS))],
            'page' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
        ]);
        $search = trim((string) ($filters['busca'] ?? ''));
        $transport = (string) ($filters['transporte'] ?? '');
        $selection = StudentSelection::query($school, $search);
        StudentSelection::filterTransport($selection, $school, $transport);
        $navigation = app(StudentNavigation::class)->forSelection($selection, $student);
        $listFilters = array_filter(['busca' => $search, 'transporte' => $transport], fn ($value) => $value !== '');
        $listParams = ['municipio' => $municipality->slug, 'escola' => $school->id, ...$listFilters];
        $page = $navigation['position'] !== null ? intdiv($navigation['position'] - 1, 25) + 1 : (int) ($filters['page'] ?? 1);
        $studentListUrl = route('school.students.index', [...$listParams, ...($page > 1 ? ['page' => $page] : [])]);
        $studentSearchUrl = route('school.students.index', $listParams).'#busca-aluno';
        $studentNavigationLinks = [];
        foreach (['first', 'previous', 'next', 'last'] as $key) {
            $studentNavigationLinks[$key] = $navigation[$key] === null ? null : route('school.students.show', [...$listParams, 'aluno' => $navigation[$key]]);
        }
        $studentSelectionLabel = $listFilters === [] ? 'Alunos ativos desta escola' : 'Alunos ativos desta escola, com filtros da lista';

        $enrollments = $student->enrollments()
            ->where('school_id', $school->id)
            ->with(['schoolYear', 'schoolClass'])
            ->get()
            ->sortByDesc(fn ($enrollment): int => (int) ($enrollment->schoolYear?->year ?? 0));

        $guardianLinks = StudentGuardian::query()
            ->where('student_id', $student->id)
            ->with('guardian')
            ->orderByDesc('is_primary_contact')
            ->orderBy('created_at')
            ->get();

        $pickupAuthorizations = StudentPickupAuthorization::query()
            ->where('student_id', $student->id)
            ->orderBy('full_name')
            ->get()
            ->sortBy(fn (StudentPickupAuthorization $person): array => [$person->isExpired() ? 1 : 0, $person->full_name])
            ->values();

        // Saúde: a gestão vê tudo; quem só consulta vê apenas os alertas graves.
        // Saúde completa e deficiência exigem o segundo fator confirmado nesta sessão;
        // sem ele, a gestão vê só os alertas graves (segurança do aluno) e o convite para ativar.
        $managesHealth = Gate::allows('manageHealth', [$school, $municipality]);
        $canManageHealth = $managesHealth && app(TwoFactorSession::class)->isVerified($request, $request->user());
        $healthLocked = $managesHealth && ! $canManageHealth;
        $healthAlerts = StudentHealthAlert::query()->where('student_id', $student->id)
            ->when(! $canManageHealth, fn ($query) => $query->where('severity', 'critical'))
            ->orderByRaw("CASE WHEN severity = 'critical' THEN 0 ELSE 1 END")->orderBy('created_at')->get();
        $healthProfile = $canManageHealth ? StudentHealthProfile::query()->find($student->id) : null;
        $specialEducation = $canManageHealth ? StudentSpecialEducation::query()->find($student->id) : null;

        // Só direção ou professor com atribuição vigente recebe a devolutiva.
        // Os dados técnicos e a área/motivo do pedido nunca vão para esta view.
        $teamAccess = app(TeamAccess::class);
        $teamEnrollments = $enrollments->filter(fn (StudentEnrollment $entry): bool => $teamAccess->schoolAccess($request->user(), $municipality, $school, $entry));
        $teamVerified = $teamEnrollments->isNotEmpty() && app(TwoFactorSession::class)->isVerified($request, $request->user());
        $teamRows = ! $teamVerified ? collect() : MultiprofessionalRequest::query()
            ->where('school_id', $school->id)->where('student_id', $student->id)
            ->whereIn('enrollment_id', $teamEnrollments->pluck('id'))
            ->get(['enrollment_id', 'status', 'school_guidance'])->groupBy('enrollment_id');
        $teamPanels = $teamEnrollments->map(function (StudentEnrollment $entry) use ($teamRows): array {
            $rows = $teamRows->get($entry->id, collect());

            return [
                'enrollment' => $entry->id,
                'class' => $entry->schoolClass?->name ?? 'Sem turma',
                'year' => $entry->schoolYear?->year,
                'ongoing' => $rows->contains(fn (MultiprofessionalRequest $row): bool => $row->status !== 'closed'),
                'guidance' => $rows->pluck('school_guidance')->filter()->values()->all(),
            ];
        })->values()->all();

        // Última saída válida nesta escola, para quem atende a família por telefone.
        $lastExit = StudentExit::query()->valid()->where('student_id', $student->id)->where('school_id', $school->id)
            ->orderByDesc('exited_at')->first();

        app(AuditTrail::class)->record('tenant', 'student.viewed', Student::class, $student->id);

        $canManage = Gate::allows('manageStudents', [$school, $municipality]);
        $canDeleteEnrollment = Gate::allows('deleteEnrollment', [$school, $municipality]);
        $studentCensus = $canManage ? StudentCensus::query()->find($student->id) : null;
        $declarations = $canManage ? IssuedDocument::query()->whereIn('kind', array_keys(IssuedDocument::DECLARATIONS))->where('school_id', $school->id)
            ->where('student_id', $student->id)->orderByDesc('issued_at')->get() : collect();

        // Distância casa-escola em linha reta e pelas ruas, a pé, e aviso do
        // transporte pela regra municipal (pelas ruas quando o serviço responde).
        $distanciaEscola = $distanciaRuas = null;
        if ($student->latitude !== null && $school->latitude !== null) {
            $pontos = [(float) $student->latitude, (float) $student->longitude, (float) $school->latitude, (float) $school->longitude];
            $distanciaEscola = Distance::km(...$pontos);
            $distanciaRuas = app(RouteDistance::class)->km(...$pontos);
        }
        $avisoTransporte = $distanciaEscola === null ? null : Distance::transportHint(
            $distanciaRuas ?? $distanciaEscola,
            $enrollments->firstWhere('status', 'active')?->transport_status,
            (float) config('geo.transport_min_km'),
            $distanciaRuas !== null ? 'pelas ruas' : 'em linha reta',
        );

        return response()->view('students.show', compact('municipality', 'school', 'student', 'enrollments', 'canManage', 'canDeleteEnrollment', 'guardianLinks', 'pickupAuthorizations', 'distanciaEscola', 'distanciaRuas', 'avisoTransporte', 'lastExit', 'canManageHealth', 'healthLocked', 'healthAlerts', 'healthProfile', 'specialEducation', 'studentCensus', 'declarations', 'teamPanels', 'teamVerified', 'navigation', 'studentNavigationLinks', 'studentListUrl', 'studentSearchUrl', 'studentSelectionLabel'))->header('Cache-Control', 'private, no-store');
    }
}
