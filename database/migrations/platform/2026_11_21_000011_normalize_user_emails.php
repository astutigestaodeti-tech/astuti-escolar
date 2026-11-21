<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('platform');

        $db->transaction(static function () use ($db): void {
            // Inspecionar antes de alterar qualquer conta, inclusive espacos laterais.
            // Nunca listar emails pessoais no erro da migracao.
            $duplicates = $db->selectOne(<<<'SQL'
                SELECT COUNT(*) AS total
                FROM (
                    SELECT lower(btrim(email))
                    FROM users
                    GROUP BY lower(btrim(email))
                    HAVING COUNT(*) > 1
                ) AS duplicated_emails
                SQL);

            if ((int) $duplicates->total > 0) {
                $message = sprintf(
                    'Migracao de emails interrompida: ha %d grupo(s) de emails duplicados sem diferenca de caixa. Revise os cadastros com seguranca antes de repetir.',
                    (int) $duplicates->total,
                );

                throw new \RuntimeException($message);
            }

            $db->statement('UPDATE users SET email = lower(btrim(email)) WHERE email IS DISTINCT FROM lower(btrim(email))');
            $db->statement('CREATE UNIQUE INDEX users_email_lower_unique ON users ((lower(email)))');
        });
    }

    public function down(): void
    {
        // Nao reverte a caixa dos emails: a transformacao nao e reversivel.
        DB::connection('platform')->statement('DROP INDEX IF EXISTS users_email_lower_unique');
    }
};
