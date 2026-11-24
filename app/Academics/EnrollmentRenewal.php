<?php

namespace App\Academics;

use App\Audit\AuditTrail;
use App\Models\Tenant\ClassYearClosure;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\StudentFinalResult;
use App\Models\Tenant\StudentPriorSchoolYear;
use App\Students\DuplicateStudents;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EnrollmentRenewal
{
    public const RESULTS = [
        'aprovado' => 'Aprovado',
        'acompanhado' => 'Aprovado com acompanhamento',
        'retido' => 'Retido',
        'cursando' => 'Cursando',
    ];

    public const REASONS = [
        'renewed' => 'Já renovado',
        'exited' => 'Matrícula não ativa (saída ou encerramento)',
        'unconfirmed' => 'Sem resultado confirmado para renovação',
        'last_grade' => 'Concluiu o 9º ano; não há série seguinte na rede neste fluxo',
        'unknown_grade' => 'Série ou modalidade sem identificação segura',
        'no_destination' => 'Sem turma aberta da série de destino nas escolas autorizadas',
        'age_cutoff' => 'Confira a data de nascimento e a idade na data de corte; etapa de destino não confirmada',
        'duplicate' => 'Possível cadastro repetido; confira antes de renovar',
        'unchecked' => 'Não selecionado para renovação',
    ];

    /** Série inequívoca do Fundamental regular; nunca presumir pela idade. */
    public static function grade(SchoolClass $class): ?int
    {
        $stage = self::normalized($class->stage);
        $name = self::normalized($class->name);
        $modality = self::normalized((string) $class->modality);

        if (! str_contains($stage, 'fundamental')
            || preg_match('/\beja\b|jovens e adultos|multisseriad|multiano|infantil/', $stage.' '.$name.' '.$modality)) {
            return null;
        }
        $stageGrade = preg_match('/(?:^|\s)([1-9])\s*(?:o|a|º|ª|°)?\s*(?:ano|serie)\b/u', $stage, $match) ? (int) $match[1] : null;
        $nameGrade = preg_match('/^([1-9])\s*(?:o|a|º|ª|°)?\s*(?:ano\b|serie\b|[a-z](?:\s|$)|$)/u', $name, $match) ? (int) $match[1] : null;
        if ($stageGrade !== null && $nameGrade !== null && $stageGrade !== $nameGrade) {
            return null;
        }

        return $stageGrade ?? $nameGrade;
    }

    public function preview(SchoolClass $source, ?array $schoolIds = []): array
    {
        $year = $source->schoolYear;
        $nextYear = $this->nextYear($source);
        $closed = ClassYearClosure::query()->whereKey($source->id)->whereNotNull('closed_at')->exists();
        $classes = $nextYear === null ? collect() : $this->destinations($source, $nextYear, $schoolIds);
        $counts = $classes->isEmpty() ? collect() : $this->occupancy($classes);
        $entries = $this->sourceEntries($source)->with('student:id,full_name,birth_date,deleted_at')
            ->orderBy('id')->get()->sortBy(fn (StudentEnrollment $entry): string => self::normalized($entry->student->full_name).'|'.$entry->id)->values();
        $results = StudentFinalResult::query()->where('school_class_id', $source->id)
            ->whereIn('student_enrollment_id', $entries->pluck('id'))->whereNotNull('confirmed_at')->get()->keyBy('student_enrollment_id');
        $existing = $nextYear === null ? collect() : StudentEnrollment::query()
            ->where('school_year_id', $nextYear->id)->where('status', 'active')
            ->whereIn('student_id', $entries->pluck('student_id'))->pluck('student_id')->unique();

        $history = $this->history($source, $entries);

        // Distribui as sugestões pela ocupação projetada, priorizando o turno.
        $projected = $counts->all();
        $rows = $entries->map(function (StudentEnrollment $entry) use ($source, $nextYear, $results, $existing, $classes, $history, &$projected): array {
            $result = $results->get($entry->id)?->result;
            [$reason, $choices, $target] = $this->eligibility($source, $entry, $entry->student, $result, $existing->contains($entry->student_id), $classes, $history, $nextYear);
            $suggestion = null;
            if ($reason === null) {
                $suggestion = $choices->sortBy(fn (SchoolClass $class): array => [
                    $class->school_id === $source->school_id ? 0 : 1,
                    self::normalized($source->shift) === self::normalized($class->shift) ? 0 : 1,
                    $class->capacity !== null && ($projected[$class->id] ?? 0) >= $class->capacity ? 1 : 0,
                    $projected[$class->id] ?? 0,
                    self::normalized($class->name),
                    $class->id,
                ])->first()?->id;
                if ($suggestion !== null && $classes->get($suggestion)->school_id === $source->school_id) {
                    $projected[$suggestion] = ($projected[$suggestion] ?? 0) + 1;
                }
            }

            $crossSchool = $suggestion !== null && $classes->get($suggestion)->school_id !== $source->school_id;

            return compact('entry', 'result', 'reason', 'choices', 'suggestion', 'target', 'crossSchool');
        });

        return [
            'nextNumber' => $year->year + 1, 'nextYear' => $nextYear, 'closed' => $closed,
            'classes' => $classes->filter(fn (SchoolClass $class): bool => $class->school_id === $source->school_id || $rows->contains(fn (array $row): bool => $row['choices']->has($class->id))),
            'counts' => $counts, 'rows' => $rows,
            'ready' => $closed && $nextYear !== null && $classes->isNotEmpty(),
        ];
    }

    /** @param array<string, string|null> $selected Matrícula de origem => turma escolhida. */
    public function renew(SchoolClass $source, array $selected, string $expectedYear, ?array $schoolIds = []): array
    {
        return DB::connection('tenant')->transaction(function () use ($source, $selected, $expectedYear, $schoolIds): array {
            // O mesmo lock usado no fechamento impede reabrir ou alterar decisões durante a renovação.
            $closure = ClassYearClosure::query()->whereKey($source->id)->lockForUpdate()->first();
            if ($closure?->closed_at === null) {
                throw ValidationException::withMessages(['destinos' => 'Feche o ano letivo da turma antes de renovar.']);
            }
            $source = SchoolClass::query()->where('school_id', $source->school_id)
                ->whereIn('status', ['active', 'completed'])->with('schoolYear')->findOrFail($source->id);
            $nextYear = $this->nextYear($source);
            if ($nextYear === null || $nextYear->id !== $expectedYear) {
                throw ValidationException::withMessages(['destinos' => 'O ano seguinte não está disponível. Consulte novamente.']);
            }
            // A ordem normalizada é a mesma da trava de duplicidade. Evita
            // disputar os mesmos nomes em ordem diferente em dois lotes.
            $sourceIds = $this->sourceEntries($source)->pluck('student_id');
            $names = Student::query()->whereIn('id', $sourceIds)
                ->orderByRaw(DuplicateStudents::NAME_KEY)->orderBy('id')->pluck('full_name', 'id');
            foreach ($names as $name) {
                DuplicateStudents::lock($name);
            }
            // Trava pais na ordem das gravações dos modelos e revalida o destino.
            $destinationSchools = SchoolClass::query()->where('school_year_id', $nextYear->id)
                ->whereIn('id', array_filter($selected))->pluck('school_id')->unique();
            $lockedSchools = School::query()->where('status', 'active')
                ->whereIn('id', $destinationSchools->push($source->school_id)->unique())
                ->when($schoolIds !== null, fn ($query) => $query->whereIn('id', [...$schoolIds, $source->school_id]))
                ->orderBy('id')->lockForUpdate()->pluck('id');
            if (! $lockedSchools->contains($source->school_id) || $destinationSchools->diff($lockedSchools)->isNotEmpty()) {
                throw ValidationException::withMessages(['destinos' => 'Escolha somente escolas ativas sob sua gestão.']);
            }
            $source = SchoolClass::query()->where('school_id', $source->school_id)->whereIn('status', ['active', 'completed'])
                ->with('schoolYear')->lockForUpdate()->findOrFail($source->id);
            if ($nextYear->year !== $source->schoolYear->year + 1) {
                throw ValidationException::withMessages(['destinos' => 'O ano da turma foi alterado. Consulte novamente.']);
            }
            $nextYear = SchoolYear::query()->whereKey($nextYear->id)->whereIn('status', ['active', 'planned'])
                ->lockForUpdate()->first();
            if ($nextYear === null) {
                throw ValidationException::withMessages(['destinos' => 'O ano de destino foi encerrado.']);
            }
            $students = Student::query()->whereIn('id', $sourceIds)->orderBy('id')->lockForUpdate()->get(['id', 'full_name', 'birth_date'])->keyBy('id');
            foreach ($students as $student) {
                if ($names->get($student->id) !== $student->full_name) {
                    throw ValidationException::withMessages(['destinos' => 'Um cadastro mudou durante a renovação. Consulte novamente.']);
                }
            }
            $classes = $this->destinations($source, $nextYear, $schoolIds, true);
            if ($classes->isEmpty()) {
                throw ValidationException::withMessages(['destinos' => 'Cadastre turmas abertas no ano seguinte antes de renovar.']);
            }

            $entries = $this->sourceEntries($source)->whereIn('student_id', $students->keys())
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $results = StudentFinalResult::query()->where('school_class_id', $source->id)
                ->whereIn('student_enrollment_id', $entries->keys())->whereNotNull('confirmed_at')->get()->keyBy('student_enrollment_id');
            $existing = StudentEnrollment::query()->where('school_year_id', $nextYear->id)->where('status', 'active')
                ->whereIn('student_id', $students->keys())->pluck('student_id')->unique()->flip()->all();

            foreach ($selected as $id => $destination) {
                if (! $entries->has($id)) {
                    throw ValidationException::withMessages(['destinos' => 'A lista da turma mudou. Consulte novamente antes de gravar.']);
                }
                if ($destination !== null && ! $classes->has($destination)) {
                    throw ValidationException::withMessages(['destinos' => 'Escolha somente turmas abertas das escolas autorizadas e do ano seguinte.']);
                }
            }

            $history = $this->history($source, $entries);
            $created = [];
            $skipped = [];
            $usedClasses = [];
            foreach ($entries as $entry) {
                $student = $students->get($entry->student_id);
                [$reason, $choices, $target] = $this->eligibility($source, $entry, $student, $results->get($entry->id)?->result, isset($existing[$entry->student_id]), $classes, $history, $nextYear);
                $destination = $selected[$entry->id] ?? null;
                if ($reason === null && $destination === null) {
                    $reason = 'unchecked';
                }
                if ($reason === null && ! $choices->has($destination)) {
                    throw ValidationException::withMessages(['destinos' => 'A turma escolhida não corresponde à série de destino confirmada.']);
                }
                if ($reason === null && DuplicateStudents::exists($student->full_name, $student->birth_date?->toDateString(), $student->id)) {
                    $reason = 'duplicate';
                }
                if ($reason !== null) {
                    $skipped[$reason] = ($skipped[$reason] ?? 0) + 1;

                    continue;
                }

                $targetClass = $classes->get($destination);
                if (! $lockedSchools->contains($targetClass->school_id)) {
                    throw ValidationException::withMessages(['destinos' => 'A escola de destino mudou. Consulte novamente.']);
                }
                $new = StudentEnrollment::query()->create([
                    'id' => (string) Str::uuid(), 'student_id' => $entry->student_id,
                    'school_id' => $targetClass->school_id, 'school_year_id' => $nextYear->id,
                    'school_class_id' => $destination, 'status' => 'active',
                    'inep_stage_code' => RenewalStage::isMulti($targetClass) ? RenewalStage::inep($target) : null,
                    'started_at' => $nextYear->start_date->toDateString(),
                ]);
                $created[] = $new->id;
                $existing[$entry->student_id] = true;
                $usedClasses[$destination] = true;
            }

            // Recontagem após a gravação, dentro da transação. Capacidade é
            // aviso administrativo, não motivo para recusar matrícula.
            $after = $this->occupancy($classes);
            $warnings = [];
            foreach (array_keys($usedClasses) as $id) {
                $class = $classes->get($id);
                $occupied = (int) $after->get($id, 0);
                if ($class->capacity !== null && $occupied > $class->capacity) {
                    $warnings[] = ['class_id' => $id, 'occupied' => $occupied, 'capacity' => (int) $class->capacity];
                }
            }
            app(AuditTrail::class)->record('tenant', 'enrollment.renewal.batch', 'enrollment_renewal', $source->id, null, [
                'source_class_id' => $source->id, 'destination_year_id' => $nextYear->id,
                'enrollment_ids' => implode(',', $created), 'renewed_count' => count($created), 'skipped_count' => array_sum($skipped),
            ]);

            return ['renewed' => count($created), 'skipped' => $skipped, 'warnings' => $warnings];
        }, 3);
    }

    private function nextYear(SchoolClass $source): ?SchoolYear
    {
        return SchoolYear::query()->where('year', $source->schoolYear->year + 1)
            ->whereIn('status', ['active', 'planned'])->first();
    }

    private function sourceEntries(SchoolClass $source): Builder
    {
        return StudentEnrollment::query()->where('school_id', $source->school_id)
            ->where('school_year_id', $source->school_year_id)->where('school_class_id', $source->id)
            ->whereHas('student', fn ($query) => $query->whereNull('deleted_at'));
    }

    private function destinations(SchoolClass $source, SchoolYear $year, ?array $schoolIds, bool $lock = false): Collection
    {
        $classes = SchoolClass::query()->where('school_year_id', $year->id)
            ->when($schoolIds !== null, fn ($query) => $query->whereIn('school_id', [...$schoolIds, $source->school_id]))
            ->whereHas('school', fn ($query) => $query->where('status', 'active')->whereNull('deleted_at'))
            ->with('school:id,name')
            ->whereIn('status', ['active', 'planned'])->orderBy('id')
            ->when($lock, fn ($query) => $query->lockForUpdate())->get();
        $closed = ClassYearClosure::query()->whereIn('school_class_id', $classes->pluck('id'))
            ->orderBy('school_class_id')->when($lock, fn ($query) => $query->lockForUpdate())
            ->get(['school_class_id', 'closed_at'])->whereNotNull('closed_at')->pluck('school_class_id');

        return $classes->reject(fn (SchoolClass $class): bool => $closed->contains($class->id))->keyBy('id');
    }

    private function occupancy(Collection $classes): Collection
    {
        return StudentEnrollment::query()->whereIn('school_class_id', $classes->keys())
            ->where('status', 'active')->select('school_class_id')->selectRaw('count(*) AS total')
            ->groupBy('school_class_id')->pluck('total', 'school_class_id');
    }

    /**
     * Só o ano imediatamente anterior, sem presumir promoção pela idade.
     * Histórico local e externo são confrontados; registros divergentes
     * deixam a série sem identificação segura.
     */
    private function history(SchoolClass $source, Collection $entries): array
    {
        if (! RenewalStage::isMulti($source)) {
            return [];
        }

        $previous = $source->schoolYear->year - 1;
        $ids = $entries->pluck('student_id');
        $internal = StudentEnrollment::query()->whereIn('student_id', $ids)
            ->whereHas('schoolYear', fn ($query) => $query->where('year', $previous)->whereNull('deleted_at'))
            ->whereIn('status', ['active', 'completed'])->with('schoolClass:id,stage,name,modality')->get(['id', 'student_id', 'school_class_id', 'inep_stage_code']);
        $results = StudentFinalResult::query()->whereIn('student_enrollment_id', $internal->pluck('id'))
            ->whereNotNull('confirmed_at')->get(['student_enrollment_id', 'result'])->keyBy('student_enrollment_id');
        $external = StudentPriorSchoolYear::query()->whereIn('student_id', $ids)->where('school_year', $previous)
            ->get(['student_id', 'school_stage', 'result'])->groupBy('student_id');
        $history = [];
        foreach ($internal as $entry) {
            $level = $entry->inep_stage_code === 41 ? 9 : ($entry->inep_stage_code >= 14 && $entry->inep_stage_code <= 21 ? $entry->inep_stage_code - 13 : null);
            if ($level === null && $entry->schoolClass !== null && ! RenewalStage::isMulti($entry->schoolClass)) {
                $level = RenewalStage::levels($entry->schoolClass)[0] ?? null;
            }
            $history[$entry->student_id][] = [$level, $results->get($entry->id)?->result];
        }
        foreach ($external as $studentId => $records) {
            foreach ($records as $record) {
                $history[$studentId][] = [RenewalStage::historicalLevel($record->school_stage), self::normalized($record->result)];
            }
        }

        return $history;
    }

    private function sourceLevel(SchoolClass $source, StudentEnrollment $entry, Student $student, array $history): ?int
    {
        if (! RenewalStage::supported($source)) {
            return null;
        }
        $levels = RenewalStage::levels($source);
        if (! RenewalStage::isMulti($source)) {
            return $levels[0] ?? null;
        }
        if ($entry->inep_stage_code !== null) {
            if (! RenewalStage::allowsCode($source, $entry->inep_stage_code)) {
                return null;
            }
            $level = RenewalStage::fromInep($entry->inep_stage_code, $student, $source->schoolYear->year);

            return $level !== null && ($levels === [] || in_array($level, $levels, true)) ? $level : null;
        }

        $inferred = [];
        foreach ($history[$entry->student_id] ?? [] as [$level, $result]) {
            if ($level === null || ! in_array($result, ['aprovado', 'aprovado com acompanhamento', 'acompanhado', 'promovido', 'continuidade confirmada', 'retido', 'reprovado'], true)) {
                continue;
            }
            $result = in_array($result, ['retido', 'reprovado'], true) ? 'retido' : 'aprovado';
            $next = RenewalStage::next($level, $result, $student, $source->schoolYear->year);
            if ($next !== null && $next <= 9) {
                $inferred[] = $next;
            }
        }
        $inferred = array_values(array_unique($inferred));

        return count($inferred) === 1 && RenewalStage::allowsCode($source, RenewalStage::inep($inferred[0]))
            && ($levels === [] || in_array($inferred[0], $levels, true)) ? $inferred[0] : null;
    }

    private function eligibility(SchoolClass $source, StudentEnrollment $entry, Student $student, ?string $result, bool $existing, Collection $classes, array $history, ?SchoolYear $nextYear): array
    {
        $grade = $this->sourceLevel($source, $entry, $student, $history);
        $reason = match (true) {
            $entry->status !== 'active' || $entry->ended_at !== null => 'exited',
            $existing => 'renewed',
            ! in_array($result, ['aprovado', 'acompanhado', 'retido'], true) => 'unconfirmed',
            $grade === null => 'unknown_grade',
            $grade === 9 && $result !== 'retido' => 'last_grade',
            $nextYear === null => 'no_destination',
            default => null,
        };
        if ($reason !== null) {
            return [$reason, collect(), null];
        }

        $target = RenewalStage::next($grade, $result, $student, $nextYear->year);
        if ($target === null) {
            return ['age_cutoff', collect(), null];
        }
        $choices = $classes->filter(fn (SchoolClass $class): bool => in_array($target, RenewalStage::levels($class), true)
            && self::normalized((string) $class->modality) === self::normalized((string) $source->modality));

        return [$choices->isEmpty() ? 'no_destination' : null, $choices, $target];
    }

    private static function normalized(string $text): string
    {
        return Str::lower(Str::ascii(trim($text)));
    }
}
