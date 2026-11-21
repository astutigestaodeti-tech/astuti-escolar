<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('platform');

        // A mesma funcao impede UPDATE, DELETE e TRUNCATE, inclusive via SQL bruto.
        $db->unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION astuti_block_platform_audit_log_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    RAISE EXCEPTION 'Historico de auditoria imutavel' USING ERRCODE = 'P0001';
    RETURN NULL;
END;
$$
SQL);

        $db->unprepared('DROP TRIGGER IF EXISTS platform_audit_logs_block_mutation ON platform_audit_logs');
        $db->unprepared('CREATE TRIGGER platform_audit_logs_block_mutation BEFORE UPDATE OR DELETE ON platform_audit_logs FOR EACH ROW EXECUTE FUNCTION astuti_block_platform_audit_log_mutation()');

        $db->unprepared('DROP TRIGGER IF EXISTS platform_audit_logs_block_truncate ON platform_audit_logs');
        $db->unprepared('CREATE TRIGGER platform_audit_logs_block_truncate BEFORE TRUNCATE ON platform_audit_logs FOR EACH STATEMENT EXECUTE FUNCTION astuti_block_platform_audit_log_mutation()');
    }

    public function down(): void
    {
        $db = DB::connection('platform');

        $db->unprepared('DROP TRIGGER IF EXISTS platform_audit_logs_block_truncate ON platform_audit_logs');
        $db->unprepared('DROP TRIGGER IF EXISTS platform_audit_logs_block_mutation ON platform_audit_logs');
        $db->unprepared('DROP FUNCTION IF EXISTS astuti_block_platform_audit_log_mutation()');
    }
};
