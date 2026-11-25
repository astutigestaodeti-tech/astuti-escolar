<?php

namespace App\Academics;

use App\Models\Tenant\AcademicPeriod;
use App\Models\Tenant\AttendanceSession;
use App\Models\Tenant\ClassCouncil;
use App\Models\Tenant\LessonRecord;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolCalendarDay;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolClassCensus;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\StudentEnrollment;

final class PedagogicalPanel
{
    public function __construct(private readonly AulasDoPeriodo $aulas, private readonly DistorcaoIdadeSerie $distorcao) {}

    /** Somente a consulta nominal explicitamente autorizada recebe nomes. */
    public function forYear(SchoolYear $year, ?School $school = null, bool $nominal = false): array
    {
        $schools = School::query()->where('status', 'active')->when($school, fn ($q) => $q->whereKey($school->id))->orderBy('name')->get(['id', 'name']);
        $classes = SchoolClass::query()->where('school_year_id', $year->id)->whereIn('school_id', $schools->pluck('id'))
            ->where('status', 'active')->orderBy('name')->get();
        $periods = AcademicPeriod::query()->where('school_year_id', $year->id)->orderBy('number')->get();
        $census = SchoolClassCensus::query()->whereIn('school_class_id', $classes->pluck('id'))->get()->keyBy('school_class_id');
        $calendar = SchoolCalendarDay::query()->where('school_year_id', $year->id)
            ->where(fn ($q) => $q->whereNull('school_id')->orWhereIn('school_id', $schools->pluck('id')))
            ->get()->groupBy(fn ($entry) => $entry->school_id ?? 'network');
        $sessions = AttendanceSession::query()->whereIn('school_class_id', $classes->pluck('id'))->get(['id', 'school_class_id', 'attendance_date'])->groupBy('school_class_id');
        // Só datas, sem carregar conteúdo ou atividade no painel da rede.
        $lessonDates = LessonRecord::query()->whereIn('school_class_id', $classes->pluck('id'))
            ->where('lesson_date', '<=', today()->toDateString())->distinct()->get(['school_class_id', 'lesson_date'])->groupBy('school_class_id');
        // Nenhum parecer, observação ou encaminhamento é selecionado.
        $councils = ClassCouncil::query()->whereIn('school_class_id', $classes->pluck('id'))->whereIn('academic_period_id', $periods->pluck('id'))
            ->get(['school_class_id', 'academic_period_id', 'status'])->groupBy('school_class_id');
        $enrollments = StudentEnrollment::query()->whereIn('school_class_id', $classes->pluck('id'))->where('school_year_id', $year->id)
            ->where('status', 'active')->whereHas('student', fn ($q) => $q->whereNull('deleted_at'))
            ->with($nominal ? 'student:id,birth_date,full_name' : 'student:id,birth_date')->get()->groupBy('school_class_id');
        $result = [];
        foreach ($schools as $unit) {
            $rows = [];
            $schoolCalendar = $calendar->get('network', collect())->concat($calendar->get($unit->id, collect()));
            foreach ($classes->where('school_id', $unit->id) as $class) {
                $c = $census->get($class->id);
                $stage = $this->distorcao->etapa($class, $c);
                $eligible = $distorted = $unclassified = 0;
                $names = [];
                foreach ($enrollments->get($class->id, collect()) as $e) {
                    if ($e->school_id !== $unit->id) {
                        continue;
                    }
                    $age = $this->distorcao->avaliar($e, $year->year, $stage);
                    if ($age === null) {
                        $unclassified++;

                        continue;
                    }
                    $eligible++;
                    if ($age['distorcao']) {
                        $distorted++;
                        if ($nominal) {
                            $names[] = ['id' => $e->student_id, 'nome' => $e->student->full_name, ...$age];
                        }
                    }
                }
                $byPeriod = $councils->get($class->id, collect())->keyBy('academic_period_id');
                $periodRows = $periods->map(fn ($p) => [
                    'period' => $p, 'council' => $byPeriod->get($p->id)?->status ?? 'not_started',
                    'com_conteudo' => $lessonDates->get($class->id, collect())->filter(fn ($lesson) => $lesson->lesson_date->betweenIncluded($p->starts_on, $p->ends_on))->count(),
                    ...$this->aulas->calcular($year, $p, $schoolCalendar, $sessions->get($class->id, collect()), $c, today()),
                ])->all();
                $rows[] = ['class' => $class, 'eligible' => $eligible, 'distorted' => $distorted, 'unclassified' => $unclassified,
                    'suggested' => $stage['sugerida'], 'names' => $names, 'periods' => $periodRows];
            }
            $summary = $periods->map(fn ($p) => [
                'period' => $p,
                'completed' => count(array_filter($rows, fn ($r) => collect($r['periods'])->contains(fn ($v) => $v['period']->id === $p->id && $v['council'] === 'completed'))),
                'total' => count($rows),
            ])->all();
            $result[] = ['school' => $unit, 'classes' => $rows, 'eligible' => array_sum(array_column($rows, 'eligible')),
                'distorted' => array_sum(array_column($rows, 'distorted')), 'unclassified' => array_sum(array_column($rows, 'unclassified')), 'summary' => $summary];
        }

        return ['schools' => $result, 'eligible' => array_sum(array_column($result, 'eligible')), 'distorted' => array_sum(array_column($result, 'distorted')),
            'unclassified' => array_sum(array_column($result, 'unclassified')), 'periods' => $periods];
    }
}
