<?php

namespace App\Schools;

use App\Academics\GradeAccess;
use App\Attendance\ActiveSearchAlerts;
use App\Calendar\SchoolCalendar;
use App\Models\Tenant\AcademicPeriod;
use App\Models\Tenant\ActiveSearchCase;
use App\Models\Tenant\ActiveSearchContact;
use App\Models\Tenant\AttendanceSession;
use App\Models\Tenant\ClassYearClosure;
use App\Models\Tenant\CurricularComponent;
use App\Models\Tenant\GradeEntry;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\WaitlistRequest;
use App\Multiprofessional\TeamIndicators;
use App\Students\RegistrationPendencies;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TodayAtSchool
{
    public const LABELS = [
        'absence' => 'Alertas de faltas sem contato registrado',
        'repetido' => 'Possível cadastro repetido',
        'responsavel' => 'Aluno sem responsável',
        'turma' => 'Aluno sem turma',
        'guidance' => 'Devolutivas novas da equipe multiprofissional',
        'waiting' => 'Pedidos da equipe aguardando há mais de 15 dias',
        'attendance' => 'Turmas sem chamada no último dia letivo',
        'grades' => 'Notas faltando perto do fechamento do bimestre',
        'waitlist' => 'Convocados da lista de espera sem resposta',
    ];

    public function __construct(
        private readonly ActiveSearchAlerts $alerts,
        private readonly RegistrationPendencies $pendencies,
        private readonly TeamIndicators $team,
        private readonly SchoolCalendar $calendar,
        private readonly GradeAccess $grades,
    ) {}

    /**
     * $assignedClassIds = null para gestão; lista (inclusive vazia) para professor.
     * Não retorna alunos, motivos, notas, informações familiares ou texto técnico.
     * Consultas em lotes por escola; nenhum SELECT dentro de loops.
     *
     * @return array<string, array{count: ?int, links: list<array{route: string, params: array, label: string}>}>
     */
    public function forSchool(School $school, ?SchoolYear $year, Carbon $day, ?array $assignedClassIds = null, ?array $pending = null, bool $waitlist = false, bool $network = false): array
    {
        $items = [];
        $management = $assignedClassIds === null;
        if ($management) {
            if ($year !== null) {
                $enrollments = $this->alerts->enrollments($school, $year, summaryOnly: true);
                $summary = $this->alerts->summaries($school, $year, $enrollments, $day);
                $contacted = ActiveSearchCase::query()->where('school_id', $school->id)->where('school_year_id', $year->id)
                    ->whereIn('id', ActiveSearchContact::query()->select('active_search_case_id'))
                    ->pluck('enrollment_id')->flip();
                $count = $enrollments->filter(fn (StudentEnrollment $entry): bool => ($summary[$entry->id]['alert'] ?? false)
                    && ! $contacted->has($entry->id))->unique('student_id')->count();
                $this->add($items, 'absence', $count, [$this->link('school.active-search.index', ['ano' => $year->year, 'sem_contato' => 1])]);
            }

            $pending ??= $this->pendencies->summary($this->pendencies->forSchool($school));
            foreach (['repetido', 'responsavel', 'turma'] as $type) {
                $this->add($items, $type, $pending['byType'][$type], [$this->link('school.pendencies.index', ['tipo' => $type])]);
            }
            $team = $this->team->schoolActions($school, $day, $network);
            foreach ($team as $type => $count) {
                $this->add($items, $type, $count, [$this->link($network ? 'team.indicators' : 'school.students.index', [])]);
            }
        }

        if ($year !== null && $year->status !== 'completed' && $year->start_date->lte($day)) {
            $classes = SchoolClass::query()->where('school_id', $school->id)->where('school_year_id', $year->id)
                ->where('status', 'active')->when(! $management, fn ($query) => $query->whereIn('id', $assignedClassIds))
                ->whereNotIn('id', ClassYearClosure::query()->whereNotNull('closed_at')->select('school_class_id'))
                ->orderBy('name')->orderBy('id')->get(['id', 'name', 'stage']);
            $entries = StudentEnrollment::query()->where('school_id', $school->id)->where('school_year_id', $year->id)
                ->where('status', 'active')->whereNull('ended_at')->whereIn('school_class_id', $classes->pluck('id'))
                ->whereHas('student', fn ($query) => $query->whereNull('deleted_at'))
                ->where(fn ($query) => $query->whereNull('started_at')->orWhereDate('started_at', '<=', $day))
                ->get(['id', 'school_class_id', 'started_at'])->groupBy('school_class_id');

            $last = $this->calendar->lastSchoolDay($year, $this->calendar->entries($year->id, $school->id), $day);
            if ($last !== null) {
                $recorded = AttendanceSession::query()->whereIn('school_class_id', $classes->pluck('id'))
                    ->whereDate('attendance_date', $last)->pluck('school_class_id')->flip();
                $missing = $classes->filter(fn (SchoolClass $class): bool => ! $recorded->has($class->id)
                    && $entries->get($class->id, collect())->contains(fn (StudentEnrollment $entry): bool => $entry->started_at === null || $entry->started_at->lte($last)));
                $this->add($items, 'attendance', $missing->count(), $missing->map(fn (SchoolClass $class): array => $this->link(
                    'school.classes.attendance.index', ['turma' => $class->id, 'data' => $last->toDateString()], $class->name,
                ))->values()->all());
            }

            $this->missingGrades($items, $year, $day, $classes, $entries);
        }

        if ($management && $waitlist) {
            $counts = WaitlistRequest::query()->where('status', 'convocado')
                ->whereHas('preferences', fn ($query) => $query->where('school_id', $school->id))
                ->select('intake_year')->selectRaw('count(*) as total')->groupBy('intake_year')
                ->orderByDesc('intake_year')->pluck('total', 'intake_year');
            $this->add($items, 'waitlist', (int) $counts->sum(), $counts->map(fn ($count, $year): array => $this->link(
                'waitlist.index', ['escola' => $school->id, 'situacao' => 'convocado', 'ano' => (int) $year], 'Ano '.$year,
            ))->values()->all());
        }

        return $items;
    }

    private function missingGrades(array &$items, SchoolYear $year, Carbon $day, Collection $classes, Collection $entries): void
    {
        // Sete dias corridos até o fim do bimestre; inclui os vencidos ainda
        // abertos. Componentes opcionais não geram cobrança automática.
        $periods = AcademicPeriod::query()->where('school_year_id', $year->id)->where('kind', 'bimestre')
            ->whereNull('closed_at')->whereDate('starts_on', '<=', $day)
            ->whereDate('ends_on', '<=', $day->copy()->addDays(7))->orderBy('number')->get(['id', 'number', 'ends_on']);
        $components = CurricularComponent::query()->where('active', true)->where('is_optional', false)
            ->orderBy('display_order')->get(['id', 'name']);
        $eligible = $classes->filter(fn (SchoolClass $class): bool => $this->grades->isElementary($class));
        $grades = GradeEntry::query()->whereIn('school_class_id', $eligible->pluck('id'))
            ->whereIn('academic_period_id', $periods->pluck('id'))->whereIn('curricular_component_id', $components->pluck('id'))
            ->whereNotNull('grade')->get(['school_class_id', 'student_enrollment_id', 'academic_period_id', 'curricular_component_id'])
            ->groupBy(fn (GradeEntry $entry): string => $entry->school_class_id.'|'.$entry->academic_period_id.'|'.$entry->curricular_component_id);
        $count = 0;
        $links = [];
        foreach ($eligible as $class) {
            foreach ($periods as $period) {
                $ids = $entries->get($class->id, collect())->filter(fn (StudentEnrollment $entry): bool => $entry->started_at === null
                    || $entry->started_at->lte($period->ends_on))->pluck('id');
                foreach ($components as $component) {
                    $recorded = $grades->get($class->id.'|'.$period->id.'|'.$component->id, collect())->pluck('student_enrollment_id');
                    $missing = $ids->diff($recorded)->count();
                    if ($missing > 0) {
                        $count += $missing;
                        $links[] = $this->link('school.classes.grades.index', [
                            'turma' => $class->id, 'periodo' => $period->id, 'componente' => $component->id,
                        ], $class->name.' / '.$period->number.'º bimestre / '.$component->name);
                    }
                }
            }
        }

        $this->add($items, 'grades', $count, $links);
    }

    private function add(array &$items, string $type, ?int $count, array $links): void
    {
        if ($count !== 0) {
            $items[$type] = ['count' => $count, 'links' => $links];
        }
    }

    private function link(string $route, array $params, string $label = 'Consultar'): array
    {
        return compact('route', 'params', 'label');
    }
}
