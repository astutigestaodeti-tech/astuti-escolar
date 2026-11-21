<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use RuntimeException;

return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('platform');

        $db->transaction(function () use ($db): void {
            // Inspecionar conflitos antes de alterar contas, sem expor emails.
            $conflicts = $db->selectOne('SELECT COUNT(*) AS total FROM (SELECT lower(btrim(email)) FROM users GROUP BY lower(btrim(email)) HAVING COUNT(*) > 1) AS duplicate_groups');

            if ((int) $conflicts->total > 0) {
                throw new RuntimeException('Migracao de emails interrompida: existem contas com emails duplicados quando comparados sem maiusculas ou espacos. Revise esses cadastros antes de repetir.');
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
