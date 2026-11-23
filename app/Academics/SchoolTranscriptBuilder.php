<?php

namespace App\Academics;

use App\Models\Platform\Municipality;
use App\Models\Tenant\ClassYearClosure;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\StudentFinalResult;
use App\Models\Tenant\StudentPriorSchoolYear;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SchoolTranscriptBuilder
{
    private readonly ReportCard $reportCard;

    public function __construct(ReportCard $reportCard)
    {
        $this->reportCard = $reportCard;
    }

    /**
     * Anos fechados e confirmados vêm do banco escolar. Os digitados são usados
     * somente onde não houver ano oficial da rede. Não grava nem emite documentos.
     *
     * @return array{years: list<array>, previous: Collection, warnings: list<string>, inProgress: list<int>}
     */
    public function forStudent(Student $student, Municipality $municipality): array
    {
        $enrollments = StudentEnrollment::query()->where('student_id', $student->id)
            ->with(['school', 'schoolYear', 'schoolClass'])
            ->get()->filter(fn (StudentEnrollment $entry): bool => $entry->schoolClass !== null
                && Str::contains(Str::lower(Str::ascii($entry->schoolClass->stage)), 'fundamental'));

        $classIds = $enrollments->pluck('school_class_id')->filter()->unique()->values();
        $enrollmentIds = $enrollments->pluck('id');
        $closures = ClassYearClosure::query()->whereIn('school_class_id', $classIds)->get()->keyBy('school_class_id');
        $confirmed = StudentFinalResult::query()->whereIn('student_enrollment_id', $enrollmentIds)
            ->get()->keyBy('student_enrollment_id');

        $network = [];
        $inProgress = [];
        $warnings = [];

        foreach ($enrollments as $entry) {
            $year = (int) $entry->schoolYear->year;
            $class = $entry->schoolClass;
            $closed = $closures->get($class->id)?->closed_at;

            if ($closed === null || $confirmed->get($entry->id) === null) {
                $inProgress[$year] = $year;

                continue;
            }

            $card = $this->reportCard->build($entry->school, $entry->schoolYear, $entry);
            $grades = [];
            foreach ($card['rows'] as $row) {
                if ($row['mean'] !== null) {
                    $grades[$row['component']->name] = number_format($row['mean'], 1, ',', '');
                }
            }

            $network[$year][] = [
                'year' => $year,
                'stage' => $class->stage.' - '.$class->name,
                'school' => $entry->school->name,
                'city' => $municipality->legal_name,
                'state' => config('academics.municipality_state') ?: null,
                'result' => $confirmed->get($entry->id)->result,
                'frequency' => $card['frequency'],
                'grades' => $grades,
                'source' => 'rede',
                '_confirmed_at' => $confirmed->get($entry->id)->confirmed_at->getTimestamp(),
            ];
            unset($inProgress[$year]);
        }

        $years = [];
        foreach (array_keys($network) as $closedYear) {
            unset($inProgress[$closedYear]);
        }
        foreach ($network as $year => $entries) {
            if (count($entries) > 1) {
                $warnings[] = "Existem dois vínculos fechados em {$year}; a última confirmação foi selecionada. Confira a documentação da escola.";
            }
            usort($entries, fn (array $a, array $b): int => $b['_confirmed_at'] <=> $a['_confirmed_at']);
            $winner = $entries[0];
            unset($winner['_confirmed_at']);
            $years[(int) $year] = $winner;
        }

        $previous = StudentPriorSchoolYear::query()->where('student_id', $student->id)
            ->orderBy('school_year')->get();

        foreach ($previous as $item) {
            $year = (int) $item->school_year;
            if (isset($years[$year])) {
                $warnings[] = "O ano {$year} foi informado manualmente, mas já existe ano fechado pela rede. A emissão utiliza o registro oficial.";

                continue;
            }
            if (isset($inProgress[$year])) {
                $warnings[] = "O ano {$year} informado manualmente coincide com matrícula em curso na rede. Confira antes de emitir.";
            }
            $years[$year] = [
                'year' => $year,
                'stage' => $item->school_stage,
                'school' => $item->school_name,
                'city' => $item->city,
                'state' => $item->state,
                'result' => $item->result,
                'frequency' => $item->attendance_percent === null ? null : (float) $item->attendance_percent,
                'grades' => $item->component_grades ?? [],
                'source' => 'anterior',
            ];
        }

        ksort($years);
        ksort($inProgress);

        return [
            'years' => array_values($years),
            'previous' => $previous,
            'warnings' => $warnings,
            'inProgress' => array_values($inProgress),
        ];
    }
}
