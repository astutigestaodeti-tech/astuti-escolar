<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('student_prior_school_years', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained('students')->restrictOnDelete();
            $table->unsignedSmallInteger('school_year');
            $table->string('school_stage', 120);
            $table->string('school_name', 180);
            $table->string('city', 120);
            $table->char('state', 2);
            $table->string('result', 100);
            $table->decimal('attendance_percent', 5, 1)->nullable();
            $table->jsonb('component_grades')->default('{}');
            $table->uuid('recorded_by');
            $table->uuid('updated_by');
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->string('deletion_reason', 500)->nullable();
            $table->timestampsTz();
            $table->index(['student_id', 'school_year'], 'prior_school_years_student_year_idx');
        });
        $db = DB::connection('tenant');
        $db->statement('CREATE UNIQUE INDEX prior_school_years_one_active_per_year ON student_prior_school_years (student_id, school_year) WHERE deleted_at IS NULL');
        $db->statement('ALTER TABLE student_prior_school_years ADD CONSTRAINT prior_school_years_year_chk CHECK (school_year BETWEEN 1900 AND 2200)');
        $db->statement('ALTER TABLE student_prior_school_years ADD CONSTRAINT prior_school_years_attendance_chk CHECK (attendance_percent IS NULL OR (attendance_percent BETWEEN 0 AND 100))');
        $db->statement("ALTER TABLE student_prior_school_years ADD CONSTRAINT prior_school_years_state_chk CHECK (state ~ '^[A-Z]{2}$')");
        $db->statement('ALTER TABLE student_prior_school_years ADD CONSTRAINT prior_school_years_archive_chk CHECK ((deleted_at IS NULL AND deleted_by IS NULL AND deletion_reason IS NULL) OR (deleted_at IS NOT NULL AND deleted_by IS NOT NULL AND length(btrim(deletion_reason)) >= 10))');

        // Sem alterar a migration histórica: ampliar o CHECK e adicionar o
        // snapshot do documento sem afetar declarações previamente emitidas.
        Schema::connection('tenant')->table('issued_documents', function (Blueprint $table): void {
            $table->jsonb('transcript_snapshot')->nullable();
            $table->char('transcript_sha256', 64)->nullable();
        });
        $db->statement('ALTER TABLE issued_documents DROP CONSTRAINT issued_documents_kind_chk');
        $db->statement("ALTER TABLE issued_documents ADD CONSTRAINT issued_documents_kind_chk CHECK (kind IN ('enrollment_declaration', 'school_transcript'))");
        $db->statement("ALTER TABLE issued_documents ADD CONSTRAINT issued_documents_transcript_chk CHECK ((kind = 'enrollment_declaration' AND transcript_snapshot IS NULL AND transcript_sha256 IS NULL) OR (kind = 'school_transcript' AND transcript_snapshot IS NOT NULL AND transcript_sha256 ~ '^[0-9a-f]{64}$'))");

        // O conteúdo original e o hash não podem ser alterados, nem em SQL.
        // A revogação continua possível e preserva o documento emitido.
        $db->unprepared(<<<'SQL'
CREATE FUNCTION protect_issued_transcript_snapshot() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF OLD.kind = 'school_transcript' AND
       (NEW.transcript_snapshot IS DISTINCT FROM OLD.transcript_snapshot
        OR NEW.transcript_sha256 IS DISTINCT FROM OLD.transcript_sha256
        OR NEW.kind IS DISTINCT FROM OLD.kind
        OR NEW.student_id IS DISTINCT FROM OLD.student_id
        OR NEW.school_id IS DISTINCT FROM OLD.school_id
        OR NEW.issued_at IS DISTINCT FROM OLD.issued_at) THEN
        RAISE EXCEPTION 'Snapshot do histórico escolar não pode ser alterado.' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END;
$$;
CREATE TRIGGER issued_transcript_snapshot_immutable
BEFORE UPDATE ON issued_documents FOR EACH ROW
EXECUTE FUNCTION protect_issued_transcript_snapshot();
SQL);
    }

    public function down(): void
    {
        $db = DB::connection('tenant');
        $db->statement('DROP TRIGGER IF EXISTS issued_transcript_snapshot_immutable ON issued_documents');
        $db->statement('DROP FUNCTION IF EXISTS protect_issued_transcript_snapshot()');
        $db->statement('ALTER TABLE issued_documents DROP CONSTRAINT IF EXISTS issued_documents_transcript_chk');
        $db->statement('ALTER TABLE issued_documents DROP CONSTRAINT IF EXISTS issued_documents_kind_chk');
        $db->statement("ALTER TABLE issued_documents ADD CONSTRAINT issued_documents_kind_chk CHECK (kind = 'enrollment_declaration')");
        Schema::connection('tenant')->table('issued_documents', function (Blueprint $table): void {
            $table->dropColumn(['transcript_snapshot', 'transcript_sha256']);
        });
        Schema::connection('tenant')->dropIfExists('student_prior_school_years');
    }
};
