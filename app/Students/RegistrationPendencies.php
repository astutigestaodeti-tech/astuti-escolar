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
        'repetido' => 'Possível cadastro repetido',
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
        $today = today()->toDateString();
        $rows = StudentEnrollment::query()
            ->join('students', 'students.id', '=', 'student_enrollments.student_id')
            ->join('school_years', 'school_years.id', '=', 'student_enrollments.school_year_id')
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
            // A lista continua contando pessoas com matrícula ativa, inclusive as que só têm
            // matrícula futura. Turma/transporte vêm da matrícula preferencial de cada aluno.
            // Mesma precedência de vigência da frequência; ano concluído só como fallback.
            ->orderByRaw("CASE WHEN school_years.status = 'completed' OR school_years.deleted_at IS NOT NULL THEN 3 WHEN school_years.start_date <= ? AND school_years.end_date >= ? THEN 0 WHEN school_years.start_date <= ? THEN 1 ELSE 2 END", [$today, $today, $today])
            ->orderByRaw("CASE WHEN school_years.status != 'completed' AND school_years.deleted_at IS NULL AND school_years.start_date > ? THEN school_years.year END ASC", [$today])
            ->orderByDesc('school_years.year')
            ->orderBy('student_enrollments.id')
            ->get();

        $schoolMapped = $school->latitude !== null && $school->longitude !== null;
        $minKm = (float) config('geo.transport_min_km');

        // Mesmo nome (sem acento, caixa ou espaço extra) e nascimento igual ou não informado: pode ser o
        // mesmo aluno importado duas vezes. Só avisa; quem decide é a secretaria, na ficha.
        $rows = $rows->unique('id');
        $nameKey = fn (StudentEnrollment $row): string => preg_replace('/\s+/', ' ', Str::lower(Str::ascii(trim((string) $row->full_name))));
        $repeated = $rows->groupBy($nameKey)->filter(fn (Collection $group): bool => $group->count() > 1)
            ->flatMap(function (Collection $group): array {
                $ids = [];
                foreach ($group as $a) {
                    foreach ($group as $b) {
                        $sameBirth = $a->birth_date === null || $b->birth_date === null || (string) $a->birth_date === (string) $b->birth_date;
                        if ($a->id !== $b->id && $sameBirth) {
                            $ids[$a->id] = true;
                        }
                    }
                }

                return array_keys($ids);
            })->flip();

        return $rows->map(function (StudentEnrollment $row) use ($school, $schoolMapped, $minKm, $repeated): array {
            $pendencies = [];
            if ($repeated->has($row->id)) {
                $pendencies[] = 'repetido';
            }
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
