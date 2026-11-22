<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // A validação em PHP dá uma mensagem útil; o índice fecha a janela
        // de concorrência e protege importações futuras e outras gravações.
        DB::connection('tenant')->statement(
            "CREATE UNIQUE INDEX school_classes_active_name_unique
             ON school_classes (school_id, school_year_id, lower(btrim(name)))
             WHERE status = 'active' AND deleted_at IS NULL"
        );
    }

    public function down(): void
    {
        DB::connection('tenant')->statement('DROP INDEX IF EXISTS school_classes_active_name_unique');
    }
};
