<?php

namespace App\Students;

use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

/** Traduz somente os conflitos conhecidos de identidade no PostgreSQL. */
final class StudentIdentityConflict
{
    public static function rethrow(QueryException $exception): never
    {
        $detail = $exception->errorInfo[2] ?? null;
        if ((string) ($exception->errorInfo[0] ?? '') === '23505' && is_string($detail)) {
            if (str_contains($detail, '"students_ra_normalized_unique"')) {
                throw ValidationException::withMessages([
                    'ra' => 'Este RA já está cadastrado. Confira o registro existente antes de prosseguir.',
                ]);
            }
            if (str_contains($detail, '"students_cpf_active_unique"')) {
                throw ValidationException::withMessages([
                    'cpf' => 'Este CPF já está no cadastro de outro aluno da rede. Confira o documento.',
                ]);
            }
        }

        throw $exception;
    }
}
