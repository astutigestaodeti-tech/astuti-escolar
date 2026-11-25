<?php

namespace App\Students;

use App\Models\Tenant\Student;
use Illuminate\Database\Eloquent\Builder;

final class StudentNavigation
{
    /**
     * Consulta apenas os identificadores dos destinos, sem materializar a lista.
     * O desempate e a collation são os mesmos da listagem, inclusive homônimos.
     */
    public function forSelection(Builder $selection, Student $student): array
    {
        $total = (clone $selection)->count();
        $empty = ['total' => $total, 'position' => null, 'first' => null, 'previous' => null, 'next' => null, 'last' => null];
        if (! (clone $selection)->whereKey($student->id)->exists()) {
            return $empty;
        }

        $before = $this->relative($selection, $student, '<');
        $after = $this->relative($selection, $student, '>');
        $position = (clone $before)->count() + 1;

        return [
            ...$empty,
            'position' => $position,
            'first' => $position > 1 ? StudentSelection::ordered(clone $selection)->value('students.id') : null,
            'previous' => StudentSelection::ordered($before, 'desc')->value('students.id'),
            'next' => StudentSelection::ordered($after)->value('students.id'),
            'last' => $position < $total ? StudentSelection::ordered(clone $selection, 'desc')->value('students.id') : null,
        ];
    }

    private function relative(Builder $selection, Student $student, string $operator): Builder
    {
        return (clone $selection)->where(function (Builder $q) use ($student, $operator): void {
            $q->where('students.full_name', $operator, $student->full_name)
                ->orWhere(function (Builder $same) use ($student, $operator): void {
                    $same->where('students.full_name', $student->full_name)->where('students.id', $operator, $student->id);
                });
        });
    }
}
