<?php

namespace App\Services;

use App\Audit\AuditTrail;
use App\Models\Tenant\School;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Movimentação de vínculos sem exclusão física e com auditoria na mesma transação. */
class EnrollmentMovementService
{
    public function transfer(StudentEnrollment $enrollment, string $version, string $destinationId, string $date): void
    {
        $this->withLockedEnrollment($enrollment, $version, function (StudentEnrollment $locked) use ($destinationId, $date): void {
            $this->validateDate($locked, $date);

            if ($destinationId === $locked->school_id) {
                throw ValidationException::withMessages(['escola_destino_id' => 'Escolha uma escola de destino diferente da origem.']);
            }

            $destination = School::query()->where('status', 'active')->find($destinationId);
            if ($destination === null) {
                throw ValidationException::withMessages(['escola_destino_id' => 'Escolha outra escola ativa da rede municipal.']);
            }

            if (StudentEnrollment::query()->where('student_id', $locked->student_id)
                ->where('school_id', $destination->id)->where('status', 'active')->exists()) {
                throw ValidationException::withMessages(['escola_destino_id' => 'O aluno já possui matrícula ativa na escola de destino.']);
            }

            $locked->fill(['status' => 'transferred', 'ended_at' => $date]);
            $locked->save();

            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(),
                'student_id' => $locked->student_id,
                'school_id' => $destination->id,
                'school_year_id' => $locked->school_year_id,
                'status' => 'active',
                'started_at' => $date,
            ]);
        });
    }

    public function end(StudentEnrollment $enrollment, string $version, string $status, string $date, ?string $destinationNote): void
    {
        $this->withLockedEnrollment($enrollment, $version, function (StudentEnrollment $locked) use ($status, $date, $destinationNote): void {
            $this->validateDate($locked, $date);

            if (! in_array($status, ['left_network', 'abandoned', 'deceased'], true)) {
                throw ValidationException::withMessages(['situacao' => 'Escolha um motivo de encerramento permitido.']);
            }

            $locked->fill([
                'status' => $status,
                'ended_at' => $date,
            ]);
            $locked->save();

            // O texto livre pertence ao registro auxiliar, não à matrícula:
            // somente status e ended_at mudam no vínculo acadêmico.
            if ($status === 'left_network' && $destinationNote !== null && trim($destinationNote) !== '') {
                $destinationName = trim($destinationNote);
                DB::connection('tenant')->table('student_enrollment_destinations')->insert([
                    'enrollment_id' => $locked->id,
                    'destination_name' => $destinationName,
                    'created_at' => now(),
                ]);

                app(AuditTrail::class)->record(
                    connection: 'tenant',
                    action: 'enrollment.exit_destination_recorded',
                    auditableType: StudentEnrollment::class,
                    auditableId: $locked->id,
                    after: ['destination_note' => $destinationName],
                );
            }
        });
    }

    public function deleteRegistration(StudentEnrollment $enrollment, string $version, string $reason, string $actorId): void
    {
        $this->withLockedEnrollment($enrollment, $version, function (StudentEnrollment $locked, Student $student) use ($reason, $actorId): void {
            $locked->deleteWithReason($reason, $actorId);

            // Não arquivar a pessoa quando ainda existir qualquer outro vínculo,
            // inclusive concluído, abandonado ou transferido.
            if (! StudentEnrollment::query()->where('student_id', $student->id)->exists()) {
                $student->deleteWithReason($reason, $actorId);
            }
        });
    }

    /** Serializa operações da mesma criança e recusa formulários desatualizados. */
    private function withLockedEnrollment(StudentEnrollment $enrollment, string $version, callable $operation): void
    {
        DB::connection('tenant')->transaction(function () use ($enrollment, $version, $operation): void {
            $student = Student::query()->lockForUpdate()->findOrFail($enrollment->student_id);
            $locked = StudentEnrollment::query()->selectRaw('student_enrollments.*, xmin::text AS row_token')
                ->lockForUpdate()->findOrFail($enrollment->id);

            if ($locked->status !== 'active'
                || $locked->student_id !== $enrollment->student_id
                || $locked->school_id !== $enrollment->school_id
                || (string) $locked->row_token !== $version) {
                throw ValidationException::withMessages([
                    'versao' => 'A matrícula foi alterada por outra pessoa. Abra a ficha novamente antes de continuar.',
                ]);
            }

            $operation($locked, $student);
        }, 3);
    }

    private function validateDate(StudentEnrollment $enrollment, string $date): void
    {
        $year = $enrollment->schoolYear;

        if ($year === null || $date < $year->start_date->format('Y-m-d') || $date > $year->end_date->format('Y-m-d')
            || ($enrollment->started_at !== null && $date < $enrollment->started_at->format('Y-m-d'))) {
            throw ValidationException::withMessages([
                'data' => 'A data precisa estar no ano letivo e não pode ser anterior ao início da matrícula.',
            ]);
        }
    }
}
