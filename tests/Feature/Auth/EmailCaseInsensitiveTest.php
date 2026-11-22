<?php

namespace Tests\Feature\Auth;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Support\TwoMunicipalityTestCase;

class EmailCaseInsensitiveTest extends TwoMunicipalityTestCase
{
    public function test_login_normaliza_email_misto_na_gravacao_e_na_busca(): void
    {
        $user = $this->criarUsuario('Maria.Silva@Example.Invalid');

        $this->assertSame('maria.silva@example.invalid', $user->fresh()->email);

        $this->post('/login', [
            'email' => 'MARIA.SILVA@EXAMPLE.INVALID',
            'password' => 'SenhaForteDeTeste123!',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_indice_unico_impede_duplicidade_mudando_maiusculas_ate_sem_eloquent(): void
    {
        $user = $this->criarUsuario('Maria.Duplicada@astuti.invalid');
        $this->assertSame('maria.duplicada@astuti.invalid', $user->fresh()->email);

        $this->expectException(QueryException::class);

        DB::connection('platform')->table('users')->insert([
            'id' => (string) Str::uuid(),
            'name' => 'Outra pessoa fictícia',
            'email' => 'MARIA.DUPLICADA@ASTUTI.INVALID',
            'password' => 'hash-ficticio-inutilizavel',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_migracao_converte_emails_existentes_para_minusculas(): void
    {
        $this->withTemporaryUsersTable(function ($db): void {
            $db->table('users')->insert(['email' => ' Maria.Silva@Example.Invalid ']);

            $migration = require database_path('migrations/platform/2026_11_21_000011_normalize_user_emails.php');
            $migration->up();

            $this->assertSame('maria.silva@example.invalid', $db->table('users')->sole()->email);
            $index = $db->selectOne(
                "SELECT to_regclass('pg_temp.users_email_lower_unique') AS name"
            );
            $this->assertNotNull($index->name);
        });
    }

    public function test_migracao_para_antes_de_alterar_dados_se_existirem_duplicados(): void
    {
        $this->withTemporaryUsersTable(function ($db): void {
            $emails = ['Maria.Silva@Example.Invalid', 'maria.silva@example.invalid'];
            $db->table('users')->insert(array_map(
                static fn (string $email): array => ['email' => $email],
                $emails,
            ));

            $migration = require database_path('migrations/platform/2026_11_21_000011_normalize_user_emails.php');

            try {
                $migration->up();
                $this->fail('A migracao deveria bloquear os emails duplicados.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('duplicados', $exception->getMessage());
            }

            $this->assertEqualsCanonicalizing($emails, $db->table('users')->pluck('email')->all());
            $index = $db->selectOne("SELECT to_regclass('pg_temp.users_email_lower_unique') AS name");
            $this->assertNull($index->name);
        });
    }

    private function withTemporaryUsersTable(callable $callback): void
    {
        $db = DB::connection('platform');

        // Tabela TEMP da sessao PostgreSQL: nao toca users nem seus indices reais.
        $db->statement('CREATE TEMP TABLE users (email varchar(255) NOT NULL) ON COMMIT PRESERVE ROWS');

        try {
            $callback($db);
        } finally {
            $db->statement('DROP TABLE pg_temp.users');
        }
    }
}
