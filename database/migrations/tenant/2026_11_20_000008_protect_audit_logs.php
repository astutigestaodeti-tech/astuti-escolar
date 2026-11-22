<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('tenant');

        // A mesma funcao impede UPDATE, DELETE e TRUNCATE, inclusive via SQL bruto.
        $db->unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION astuti_block_tenant_audit_log_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    RAISE EXCEPTION 'Historico de auditoria imutavel' USING ERRCODE = 'P0001';
    RETURN NULL;
END;
$$
SQL);

        $db->unprepared('DROP TRIGGER IF EXISTS audit_logs_block_mutation ON audit_logs');
        $db->unprepared('CREATE TRIGGER audit_logs_block_mutation BEFORE UPDATE OR DELETE ON audit_logs FOR EACH ROW EXECUTE FUNCTION astuti_block_tenant_audit_log_mutation()');

        $db->unprepared('DROP TRIGGER IF EXISTS audit_logs_block_truncate ON audit_logs');
        $db->unprepared('CREATE TRIGGER audit_logs_block_truncate BEFORE TRUNCATE ON audit_logs FOR EACH STATEMENT EXECUTE FUNCTION astuti_block_tenant_audit_log_mutation()');
    }

    public function down(): void
    {
        $db = DB::connection('tenant');

        $db->unprepared('DROP TRIGGER IF EXISTS audit_logs_block_truncate ON audit_logs');
        $db->unprepared('DROP TRIGGER IF EXISTS audit_logs_block_mutation ON audit_logs');
        $db->unprepared('DROP FUNCTION IF EXISTS astuti_block_tenant_audit_log_mutation()');
    }
};
