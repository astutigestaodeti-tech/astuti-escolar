<?php

namespace App\Students;

use Illuminate\Database\Eloquent\Builder;

/** Busca de alunos usada na lista da escola e na tela de saída. */
final class StudentSearch
{
    /**
     * Uma caixa só: nome do aluno ou do responsável (sem acento), RA ou CPF
     * (só pelos números, com ou sem pontuação).
     */
    public static function apply(Builder $query, string $search): void
    {
        // Evita que % e _ enviados pelo usuário funcionem como curingas SQL.
        $literal = '%'.addcslashes($search, '\\%_').'%';
        $digits = preg_replace('/\D+/', '', $search) ?? '';
        $pgsql = $query->getModel()->getConnection()->getDriverName() === 'pgsql';
        $nameMatch = $pgsql ? 'public.f_unaccent(lower(%s)) LIKE public.f_unaccent(lower(?))' : '%s LIKE ?';

        $query->where(function (Builder $where) use ($literal, $digits, $pgsql, $nameMatch): void {
            $where->whereRaw(sprintf($nameMatch, 'students.full_name'), [$literal])
                ->orWhereRaw(sprintf($nameMatch, 'students.social_name'), [$literal])
                ->orWhereExists(function ($guardians) use ($literal, $nameMatch): void {
                    $guardians->selectRaw('1')->from('student_guardians')
                        ->join('guardians', 'guardians.id', '=', 'student_guardians.guardian_id')
                        ->whereColumn('student_guardians.student_id', 'students.id')
                        ->whereRaw(sprintf($nameMatch, 'guardians.full_name'), [$literal]);
                });

            if ($pgsql && strlen($digits) >= 4) {
                $where->orWhereRaw("regexp_replace(coalesce(students.ra, ''), '[^0-9]', '', 'g') LIKE ?", ['%'.$digits.'%']);
            }
            if ($pgsql && strlen($digits) === 11) {
                $where->orWhereRaw("regexp_replace(coalesce(students.cpf, ''), '[^0-9]', '', 'g') = ?", [$digits]);
            }
        });
    }
}
