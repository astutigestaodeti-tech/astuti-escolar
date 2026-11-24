<?php

namespace App\Documents;

use App\Models\Tenant\AttendanceMark;
use App\Models\Tenant\ClassYearClosure;
use App\Models\Tenant\InstitutionProfile;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\StudentFinalResult;
use Illuminate\Validation\ValidationException;

class SchoolDeclarationSnapshot
{
    public const TYPES = [
        'attendance_declaration' => 'Declaração de frequência',
        'transfer_declaration' => 'Declaração de transferência',
        'completion_declaration' => 'Declaração de conclusão',
    ];

    public function build(string $kind, StudentEnrollment $entry, School $school, array $input): array
    {
        $year = $entry->schoolYear;
        $class = $entry->schoolClass;
        if ($year === null || $year->deleted_at !== null || $class === null || $class->deleted_at !== null
            || $class->school_id !== $school->id || $class->school_year_id !== $entry->school_year_id) {
            throw ValidationException::withMessages(['matricula' => 'A matrícula precisa de ano letivo e turma válidos para este documento.']);
        }

        $from = max($year->start_date->toDateString(), $entry->started_at?->toDateString() ?? $year->start_date->toDateString());
        $until = min($year->end_date->toDateString(), today()->toDateString(), $entry->ended_at?->toDateString() ?? $year->end_date->toDateString());

        $content = match ($kind) {
            'attendance_declaration' => $this->attendance($entry, $input, $from, $until),
            'transfer_declaration' => $this->transfer($entry, $from, $until),
            'completion_declaration' => $this->completion($entry),
            default => throw ValidationException::withMessages(['kind' => 'Tipo de declaração inválido.']),
        };

        // Somente os campos institucionais usados no papel. Sem dados de saúde,
        // raça, deficiência, endereço do aluno ou conteúdo livre da matrícula.
        $profile = InstitutionProfile::current();
        $institution = $profile->only([
            'department_name', 'cnpj', 'phone', 'email', 'website', 'postal_code',
            'address_street', 'address_number', 'address_complement', 'address_district',
            'address_city', 'address_state',
        ]);

        return [
            ...$content,
            'institution' => $institution,
            'school' => $school->only(['name', 'inep_code', ...School::ADDRESS_FIELDS]),
        ];
    }

    private function attendance(StudentEnrollment $entry, array $input, string $from, string $until): array
    {
        $start = $input['inicio'] ?? null;
        $end = $input['fim'] ?? null;
        if (! is_string($start) || ! is_string($end) || $start < $from || $end > $until || $start > $end) {
            throw ValidationException::withMessages(['inicio' => 'Escolha um período dentro da matrícula e do ano letivo, sem datas futuras.']);
        }

        $classes = SchoolClass::query()->where('school_id', $entry->school_id)
            ->where('school_year_id', $entry->school_year_id)->pluck('id');
        $marks = AttendanceMark::query()->join('attendance_sessions as session', 'session.id', '=', 'attendance_marks.attendance_session_id')
            ->where('attendance_marks.student_id', $entry->student_id)
            ->whereIn('session.school_class_id', $classes)
            ->whereBetween('session.attendance_date', [$start, $end])
            ->select('session.attendance_date', 'attendance_marks.status')->get();

        // Uma declaração não deve presumir frequência se houver chamadas
        // conflitantes em duas turmas no mesmo dia.
        if ($marks->groupBy('attendance_date')->contains(fn ($day): bool => $day->count() > 1)) {
            throw ValidationException::withMessages(['inicio' => 'Há mais de uma chamada do aluno no mesmo dia. Confira os registros antes de emitir.']);
        }
        $total = $marks->count();
        if ($total === 0) {
            throw ValidationException::withMessages(['inicio' => 'Não há chamadas registradas para o aluno no período escolhido.']);
        }
        $present = $marks->where('status', 'present')->count();

        return [
            'start' => $start, 'end' => $end, 'total' => $total, 'present' => $present,
            'absent' => $marks->where('status', 'absent')->count(),
            'justified' => $marks->where('status', 'justified')->count(),
            'percentage' => round(100 * $present / $total, 1),
        ];
    }

    private function transfer(StudentEnrollment $entry, string $from, string $until): array
    {
        if (! in_array($entry->status, ['transferred', 'left_network'], true) || $entry->ended_at === null
            || $entry->ended_at->toDateString() < $from || $entry->ended_at->toDateString() > $until) {
            throw ValidationException::withMessages(['matricula' => 'Registre a transferência e sua data antes de emitir a declaração.']);
        }

        return [
            'ended_on' => $entry->ended_at->toDateString(),
            'status' => $entry->status,
        ];
    }

    private function completion(StudentEnrollment $entry): array
    {
        $closure = ClassYearClosure::query()->whereKey($entry->school_class_id)->first();
        $result = StudentFinalResult::query()->where('school_class_id', $entry->school_class_id)
            ->where('student_enrollment_id', $entry->id)->whereNotNull('confirmed_at')->first();
        if (! in_array($entry->status, ['active', 'completed'], true) || $closure?->closed_at === null
            || $closure->closed_at->isFuture() || ! in_array($result?->result, ['aprovado', 'acompanhado'], true)) {
            throw ValidationException::withMessages(['matricula' => 'A conclusão exige ano da turma fechado e resultado de aprovação confirmado.']);
        }

        return [
            'closed_on' => $closure->closed_at->toDateString(),
            'result' => $result->result,
        ];
    }
}
