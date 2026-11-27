<?php

namespace Tests\Unit;

use App\Students\StudentIdentityConflict;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use PDOException;
use Tests\TestCase;

final class StudentIdentityConflictTest extends TestCase
{
    private function conflict(string $constraint, string $state = '23505', string $sql = 'insert into students values (?)'): QueryException
    {
        $detail = 'duplicate key value violates unique constraint "'.$constraint.'"';
        $previous = new PDOException($detail);
        $previous->errorInfo = [$state, 7, $detail];

        return new QueryException('tenant', $sql, ['DADO-FICTICIO'], $previous);
    }

    public function test_conflito_de_cpf_e_ra_na_gravacao_tem_mensagem_sem_dados_do_aluno(): void
    {
        foreach ([
            'students_cpf_active_unique' => ['cpf', 'Este CPF já está no cadastro de outro aluno da rede. Confira o documento.'],
            'students_ra_normalized_unique' => ['ra', 'Este RA já está cadastrado. Confira o registro existente antes de prosseguir.'],
        ] as $constraint => [$field, $message]) {
            try {
                StudentIdentityConflict::rethrow($this->conflict($constraint));
            } catch (ValidationException $exception) {
                $this->assertSame([$field => [$message]], $exception->errors());
            }
        }
    }

    public function test_nao_mascara_outros_erros_do_banco_nem_confia_no_texto_da_consulta(): void
    {
        foreach ([
            $this->conflict('other_unique', sql: 'insert into students values (?) /* "students_cpf_active_unique" */'),
            $this->conflict('students_cpf_active_unique', '23514'),
        ] as $original) {
            try {
                StudentIdentityConflict::rethrow($original);
            } catch (QueryException $exception) {
                $this->assertSame($original, $exception);
            }
        }
    }
}
