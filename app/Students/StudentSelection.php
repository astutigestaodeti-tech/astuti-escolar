<?php

namespace App\Students;

use App\Models\Tenant\School;
use App\Models\Tenant\Student;
use Illuminate\Database\Eloquent\Builder;

final class StudentSelection
{
    public const TRANSPORT_FILTERS = [
        'usa' => ['status' => 'active', 'label' => 'Usa transporte'],
        'nao_solicitado' => ['status' => 'not_requested', 'label' => 'Não solicitado'],
        'cancelado' => ['status' => 'cancelled', 'label' => 'Cancelado'],
        'sem_informacao' => ['status' => null, 'label' => 'Sem informação'],
        'adaptado' => ['status' => null, 'label' => 'Veículo adaptado'],
    ];

    public static function query(School $school, string $search = ''): Builder
    {
        $query = Student::query()->whereHas('enrollments', fn (Builder $q) => self::activeHere($q, $school));
        if ($search !== '') {
            StudentSearch::apply($query, $search);
        }

        return $query;
    }

    public static function activeHere(Builder $query, School $school): void
    {
        $query->where('school_id', $school->id)->where('status', 'active');
    }

    public static function filterTransport(Builder $query, School $school, string $transport): void
    {
        if ($transport === '') {
            return;
        }
        $status = self::TRANSPORT_FILTERS[$transport]['status'];
        $query->whereHas('enrollments', function (Builder $enrollments) use ($school, $status, $transport): void {
            self::activeHere($enrollments, $school);
            if ($transport === 'adaptado') {
                $enrollments->where('transport_adapted_vehicle', true);
            } elseif ($status === null) {
                $enrollments->whereNull('transport_status');
            } else {
                $enrollments->where('transport_status', $status);
            }
        });
    }

    public static function ordered(Builder $query, string $direction = 'asc'): Builder
    {
        return $query->orderBy('students.full_name', $direction)->orderBy('students.id', $direction);
    }
}
