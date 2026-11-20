<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MariaDB aplica a proteção inclusive a operações via Query Builder e SQL bruto.
        // IF NOT EXISTS permite retomar uma migração interrompida após o primeiro DDL.
        DB::connection('tenant')->unprepared("CREATE TRIGGER IF NOT EXISTS audit_logs_block_update
            BEFORE UPDATE ON audit_logs FOR EACH ROW
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Historico de auditoria imutavel'");

        DB::connection('tenant')->unprepared("CREATE TRIGGER IF NOT EXISTS audit_logs_block_delete
            BEFORE DELETE ON audit_logs FOR EACH ROW
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Historico de auditoria nao pode ser excluido'");
    }

    public function down(): void
    {
        DB::connection('tenant')->unprepared('DROP TRIGGER IF EXISTS audit_logs_block_delete');
        DB::connection('tenant')->unprepared('DROP TRIGGER IF EXISTS audit_logs_block_update');
    }
};
