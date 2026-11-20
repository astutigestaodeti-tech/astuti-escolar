<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('platform')->unprepared("CREATE TRIGGER IF NOT EXISTS platform_audit_logs_block_update
            BEFORE UPDATE ON platform_audit_logs FOR EACH ROW
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Historico de auditoria imutavel'");

        DB::connection('platform')->unprepared("CREATE TRIGGER IF NOT EXISTS platform_audit_logs_block_delete
            BEFORE DELETE ON platform_audit_logs FOR EACH ROW
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Historico de auditoria nao pode ser excluido'");
    }

    public function down(): void
    {
        DB::connection('platform')->unprepared('DROP TRIGGER IF EXISTS platform_audit_logs_block_delete');
        DB::connection('platform')->unprepared('DROP TRIGGER IF EXISTS platform_audit_logs_block_update');
    }
};
