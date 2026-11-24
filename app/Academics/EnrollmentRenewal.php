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
        'no_destination' => 'Sem turma aberta da série de destino nesta escola',
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

    public function preview(SchoolClass $source): array
    {
        $year = $source->schoolYear;
        $nextYear = $this->nextYear($source);
        $closed = ClassYearClosure::query()->whereKey($source->id)->whereNotNull('closed_at')->exists();
        $classes = $nextYear === null ? collect() : $this->destinations($source, $nextYear);
        $counts = $classes->isEmpty() ? collect() : $this->occupancy($classes);
        $entries = $this->sourceEntries($source)->with('student:id,full_name,birth_date,deleted_at')
            ->orderBy('id')->get()->sortBy(fn (StudentEnrollment $entry): string => self::normalized($entry->student->full_name).'|'.$entry->id)->values();
        $results = StudentFinalResult::query()->where('school_class_id', $source->id)
            ->whereIn('student_enrollment_id', $entries->pluck('id'))->whereNotNull('confirmed_at')->get()->keyBy('student_enrollment_id');
        $existing = $nextYear === null ? collect() : StudentEnrollment::query()
            ->where('school_year_id', $nextYear->id)->where('status', 'active')
            ->whereIn('student_id', $entries->pluck('student_id'))->pluck('student_id')->unique();

        // Distribui as sugestões pela ocupação projetada, priorizando o turno.
        $projected = $counts->all();
        $rows = $entries->map(function (StudentEnrollment $entry) use ($source, $results, $existing, $classes, &$projected): array {
            $result = $results->get($entry->id)?->result;
            [$reason, $choices] = $this->eligibility($source, $entry, $result, $existing->contains($entry->student_id), $classes);
            $suggestion = null;
            if ($reason === null) {
                $suggestion = $choices->sortBy(fn (SchoolClass $class): array => [
                    self::normalized($source->shift) === self::normalized($class->shift) ? 0 : 1,
                    $class->capacity !== null && ($projected[$class->id] ?? 0) >= $class->capacity ? 1 : 0,
                    $projected[$class->id] ?? 0,
                    self::normalized($class->name),
                    $class->id,
                ])->first()?->id;
                if ($suggestion !== null) {
                    $projected[$suggestion] = ($projected[$suggestion] ?? 0) + 1;
                }
            }

            return compact('entry', 'result', 'reason', 'choices', 'suggestion');
        });

        return [
            'nextNumber' => $year->year + 1, 'nextYear' => $nextYear, 'closed' => $closed,
            'classes' => $classes, 'counts' => $counts, 'rows' => $rows,
            'ready' => $closed && $nextYear !== null && $classes->isNotEmpty(),
        ];
    }

    /** @param array<string, string|null> $selected Matrícula de origem => turma escolhida. */
    public function renew(SchoolClass $source, array $selected, string $expectedYear): array
    {
        return DB::connection('tenant')->transaction(function () use ($source, $selected, $expectedYear): array {
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
            School::query()->where('status', 'active')->lockForUpdate()->findOrFail($source->school_id);
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
            $classes = $this->destinations($source, $nextYear, true);
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
                    throw ValidationException::withMessages(['destinos' => 'Escolha somente turmas abertas desta escola e do ano seguinte.']);
                }
            }

            $created = [];
            $skipped = [];
            $usedClasses = [];
            foreach ($entries as $entry) {
                $student = $students->get($entry->student_id);
                [$reason, $choices] = $this->eligibility($source, $entry, $results->get($entry->id)?->result, isset($existing[$entry->student_id]), $classes);
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

                $new = StudentEnrollment::query()->create([
                    'id' => (string) Str::uuid(), 'student_id' => $entry->student_id,
                    'school_id' => $source->school_id, 'school_year_id' => $nextYear->id,
                    'school_class_id' => $destination, 'status' => 'active',
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

    private function destinations(SchoolClass $source, SchoolYear $year, bool $lock = false): Collection
    {
        $classes = SchoolClass::query()->where('school_id', $source->school_id)->where('school_year_id', $year->id)
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

    private function eligibility(SchoolClass $source, StudentEnrollment $entry, ?string $result, bool $existing, Collection $classes): array
    {
        $grade = self::grade($source);
        $reason = match (true) {
            $entry->status !== 'active' || $entry->ended_at !== null => 'exited',
            $existing => 'renewed',
            ! in_array($result, ['aprovado', 'acompanhado', 'retido'], true) => 'unconfirmed',
            $grade === null => 'unknown_grade',
            $grade === 9 && $result !== 'retido' => 'last_grade',
            default => null,
        };
        if ($reason !== null) {
            return [$reason, collect()];
        }

        $target = $grade + ($result === 'retido' ? 0 : 1);
        $choices = $classes->filter(fn (SchoolClass $class): bool => self::grade($class) === $target
            && self::normalized((string) $class->modality) === self::normalized((string) $source->modality));

        return [$choices->isEmpty() ? 'no_destination' : null, $choices];
    }

    private static function normalized(string $text): string
    {
        return Str::lower(Str::ascii(trim($text)));
    }
}
