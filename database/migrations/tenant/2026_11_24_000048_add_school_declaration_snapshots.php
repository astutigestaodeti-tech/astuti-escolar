<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('issued_documents', function (Blueprint $table): void {
            $table->jsonb('declaration_snapshot')->nullable();
        });

        $db = DB::connection('tenant');
        $db->statement('ALTER TABLE issued_documents DROP CONSTRAINT issued_documents_kind_chk');
        $db->statement("ALTER TABLE issued_documents ADD CONSTRAINT issued_documents_kind_chk CHECK (kind IN ('enrollment_declaration', 'school_transcript', 'active_search_referral', 'attendance_declaration', 'transfer_declaration', 'completion_declaration'))");
        $db->statement("ALTER TABLE issued_documents ADD CONSTRAINT issued_documents_declaration_chk CHECK ((kind IN ('attendance_declaration', 'transfer_declaration', 'completion_declaration') AND declaration_snapshot IS NOT NULL AND jsonb_typeof(declaration_snapshot) = 'object' AND purpose IS NULL) OR (kind NOT IN ('attendance_declaration', 'transfer_declaration', 'completion_declaration') AND declaration_snapshot IS NULL))");
        $db->unprepared(<<<'SQL'
CREATE FUNCTION protect_school_declaration_snapshot() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF (OLD.kind IN ('attendance_declaration', 'transfer_declaration', 'completion_declaration')
        OR NEW.kind IN ('attendance_declaration', 'transfer_declaration', 'completion_declaration'))
        AND (to_jsonb(NEW) - ARRAY['revoked_at', 'revoked_by', 'revoke_reason', 'updated_at'])
            IS DISTINCT FROM (to_jsonb(OLD) - ARRAY['revoked_at', 'revoked_by', 'revoke_reason', 'updated_at']) THEN
        RAISE EXCEPTION 'O retrato da declaração emitida não pode ser alterado.' USING ERRCODE = '23514';
    END IF;
    IF OLD.kind IN ('attendance_declaration', 'transfer_declaration', 'completion_declaration')
        AND OLD.revoked_at IS NOT NULL
        AND ROW(NEW.revoked_at, NEW.revoked_by, NEW.revoke_reason)
            IS DISTINCT FROM ROW(OLD.revoked_at, OLD.revoked_by, OLD.revoke_reason) THEN
        RAISE EXCEPTION 'A revogação da declaração é definitiva.' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END;
$$;
CREATE TRIGGER school_declaration_snapshot_immutable BEFORE UPDATE ON issued_documents
FOR EACH ROW EXECUTE FUNCTION protect_school_declaration_snapshot();
SQL);
    }

    public function down(): void
    {
        $db = DB::connection('tenant');
        // Não apagar documentos emitidos para permitir um rollback destrutivo.
        $db->statement('DROP TRIGGER IF EXISTS school_declaration_snapshot_immutable ON issued_documents');
        $db->statement('DROP FUNCTION IF EXISTS protect_school_declaration_snapshot()');
        $db->statement('ALTER TABLE issued_documents DROP CONSTRAINT issued_documents_declaration_chk');
        $db->statement('ALTER TABLE issued_documents DROP CONSTRAINT issued_documents_kind_chk');
        $db->statement("ALTER TABLE issued_documents ADD CONSTRAINT issued_documents_kind_chk CHECK (kind IN ('enrollment_declaration', 'school_transcript', 'active_search_referral'))");

        Schema::connection('tenant')->table('issued_documents', function (Blueprint $table): void {
            $table->dropColumn('declaration_snapshot');
        });
    }
};
