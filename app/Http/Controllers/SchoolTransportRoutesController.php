<?php

namespace App\Http\Controllers;

use App\Audit\AuditTrail;
use App\Models\Tenant\School;
use App\Models\Tenant\StudentEnrollment;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/** Relação operacional para a equipe do transporte, sem dados escolares sensíveis. */
class SchoolTransportRoutesController extends Controller
{
    private const WITHOUT_ROUTE = '__sem_rota__';

    public function index(Request $request, string $municipio, string $escola): Response
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($escola);
        Gate::authorize('manageStudents', [$school, $municipality]);

        $filters = $request->validate([
            'rota' => ['nullable', 'string', 'max:120'],
            'turno' => ['nullable', 'string', 'max:120'],
        ]);

        // Carregamento em lote: um conjunto fixo de consultas, sem N+1 por aluno.
        // O campo de telefone só chega a esta tela após autorização de gestão.
        $enrollments = StudentEnrollment::query()
            ->where('school_id', $school->id)
            ->where('status', 'active')
            ->where('transport_status', 'active')
            ->whereHas('student', fn ($query) => $query->whereNull('deleted_at'))
            ->with([
                'student:id,full_name',
                'student.primaryGuardianLink.guardian:id,full_name,phone',
                'schoolClass:id,name,shift',
            ])
            ->get(['id', 'student_id', 'school_id', 'school_class_id', 'transport_route', 'transport_stop', 'transport_adapted_vehicle']);

        $routeName = fn (StudentEnrollment $enrollment): string => trim((string) ($enrollment->transport_route ?? ''));
        $shiftName = fn (StudentEnrollment $enrollment): string => trim((string) ($enrollment->schoolClass?->shift ?? ''));

        $availableRoutes = $enrollments->map($routeName)->unique()->sort(fn (string $a, string $b): int => strcmp(
            $a === '' ? 'zzzzzz' : Str::lower(Str::ascii($a)),
            $b === '' ? 'zzzzzz' : Str::lower(Str::ascii($b)),
        ))->values();

        $availableShifts = $enrollments->map($shiftName)->unique()->sort(fn (string $a, string $b): int => strcmp(
            Str::lower(Str::ascii($a)),
            Str::lower(Str::ascii($b)),
        ))->values();

        $selectedRoute = (string) ($filters['rota'] ?? '');
        $selectedShift = (string) ($filters['turno'] ?? '');

        $filtered = $enrollments
            ->filter(fn (StudentEnrollment $enrollment): bool => $selectedRoute === ''
                || ($selectedRoute === self::WITHOUT_ROUTE ? $routeName($enrollment) === '' : $routeName($enrollment) === $selectedRoute))
            ->filter(fn (StudentEnrollment $enrollment): bool => $selectedShift === '' || $shiftName($enrollment) === $selectedShift);

        $groups = $filtered->groupBy($routeName)
            ->sortBy(fn ($rows, string $name): string => $name === '' ? 'zzzzzz' : Str::lower(Str::ascii($name)))
            ->map(fn ($rows) => $rows->sortBy(function (StudentEnrollment $enrollment) use ($shiftName): array {
                $stop = trim((string) ($enrollment->transport_stop ?? ''));

                return [
                    $stop === '' ? 1 : 0,
                    Str::lower(Str::ascii($stop)),
                    Str::lower(Str::ascii((string) ($enrollment->student?->full_name ?? ''))),
                    $shiftName($enrollment),
                    $enrollment->id,
                ];
            })->values());

        // Só o evento de consulta é auditado, sem lista de alunos nem telefones.
        app(AuditTrail::class)->record('tenant', 'transport.routes.viewed', School::class, $school->id);

        return response()->view('schools.transport-routes', [
            'municipality' => $municipality,
            'school' => $school,
            'groups' => $groups,
            'total' => $filtered->count(),
            'availableRoutes' => $availableRoutes,
            'availableShifts' => $availableShifts,
            'selectedRoute' => $selectedRoute,
            'selectedShift' => $selectedShift,
            'withoutRoute' => self::WITHOUT_ROUTE,
            'printedOn' => today()->format('d/m/Y'),
        ])->header('Cache-Control', 'private, no-store');
    }
}
