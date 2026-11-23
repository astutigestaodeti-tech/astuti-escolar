<?php

namespace App\Students;

use App\Geo\Distance;
use App\Models\Tenant\School;
use App\Models\Tenant\StudentEnrollment;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Pendências do cadastro: o que falta completar em cada aluno com matrícula
 * ativa na escola. Uma consulta para a escola inteira; só nomes e o tipo da
 * pendência saem daqui, nunca o valor dos dados.
 */
class RegistrationPendencies
{
    /** Ordem = prioridade: contato com a família primeiro. */
    public const TYPES = [
        'responsavel' => 'Sem responsável',
        'turma' => 'Sem turma',
        'nascimento' => 'Sem data de nascimento',
        'endereco' => 'Endereço incompleto',
        'transporte' => 'Transporte a conferir',
        'foto' => 'Sem foto',
        'mapa' => 'Casa não marcada no mapa',
    ];

    /**
     * @return Collection<int, array{id: string, name: string, class: ?string, enrollment: string, pendencies: list<string>}>
     */
    public function forSchool(School $school): Collection
    {
        $rows = StudentEnrollment::query()
            ->join('students', 'students.id', '=', 'student_enrollments.student_id')
            ->leftJoin('school_classes', 'school_classes.id', '=', 'student_enrollments.school_class_id')
            ->where('student_enrollments.school_id', $school->id)
            ->where('student_enrollments.status', 'active')
            ->whereNull('students.deleted_at')
            ->select([
                'student_enrollments.id as enrollment_id', 'student_enrollments.school_class_id', 'student_enrollments.transport_status',
                'students.id', 'students.full_name', 'students.birth_date', 'students.address_street', 'students.address_number',
                'students.postal_code', 'students.photo_path', 'students.latitude', 'students.longitude', 'school_classes.name as class_name',
            ])
            ->selectRaw('EXISTS (SELECT 1 FROM student_guardians sg WHERE sg.student_id = students.id) AS has_guardian')
            ->get();

        $schoolMapped = $school->latitude !== null && $school->longitude !== null;
        $minKm = (float) config('geo.transport_min_km');

        return $rows->unique('id')->map(function (StudentEnrollment $row) use ($school, $schoolMapped, $minKm): array {
            $pendencies = [];
            // O banco já exige telefone em todo responsável; basta saber se há algum.
            if (! $row->has_guardian) {
                $pendencies[] = 'responsavel';
            }
            if ($row->school_class_id === null) {
                $pendencies[] = 'turma';
            }
            if ($row->birth_date === null) {
                $pendencies[] = 'nascimento';
            }
            if (trim((string) $row->address_street) === '' || trim((string) $row->postal_code) === '') {
                $pendencies[] = 'endereco';
            }
            if ($schoolMapped && $row->latitude !== null) {
                $km = Distance::km((float) $row->latitude, (float) $row->longitude, (float) $school->latitude, (float) $school->longitude);
                if (Distance::transportHint($km, $row->transport_status, $minKm) !== null) {
                    $pendencies[] = 'transporte';
                }
            }
            if ($row->photo_path === null) {
                $pendencies[] = 'foto';
            }
            if ($schoolMapped && $row->latitude === null) {
                $pendencies[] = 'mapa';
            }

            return [
                'id' => $row->id,
                'name' => $row->full_name,
                'class' => $row->class_name,
                'enrollment' => $row->enrollment_id,
                'pendencies' => $pendencies,
            ];
        })->values();
    }

    /**
     * Resumo para a tela da escola e para os filtros.
     *
     * @param  Collection<int, array{pendencies: list<string>}>  $students
     * @return array{total: int, complete: int, withPendencies: int, byType: array<string, int>}
     */
    public function summary(Collection $students): array
    {
        $byType = array_fill_keys(array_keys(self::TYPES), 0);
        foreach ($students as $student) {
            foreach ($student['pendencies'] as $type) {
                $byType[$type]++;
            }
        }
        $with = $students->filter(fn (array $student): bool => $student['pendencies'] !== [])->count();

        return ['total' => $students->count(), 'complete' => $students->count() - $with, 'withPendencies' => $with, 'byType' => $byType];
    }

    /**
     * Ordena por prioridade: pendências de contato primeiro, depois mais pendências, depois nome.
     *
     * @param  Collection<int, array{name: string, pendencies: list<string>}>  $students
     */
    public function prioritized(Collection $students): Collection
    {
        $weights = array_flip(array_keys(self::TYPES));

        return $students->sortBy(fn (array $student): string => sprintf('%02d|%02d|%s',
            $student['pendencies'] === [] ? 99 : min(array_map(fn (string $type): int => $weights[$type], $student['pendencies'])),
            99 - count($student['pendencies']),
            Str::lower(Str::ascii($student['name'])),
        ))->values();
    }
}
