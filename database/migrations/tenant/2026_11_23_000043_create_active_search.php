<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('active_search_parameters', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_year_id')->unique()->constrained('school_years')->restrictOnDelete();
            $table->unsignedSmallInteger('consecutive_days');
            $table->unsignedSmallInteger('monthly_percentage');
            $table->uuid('updated_by');
            $table->timestampsTz();
        });
        Schema::connection('tenant')->create('active_search_cases', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('enrollment_id')->unique()->constrained('student_enrollments')->restrictOnDelete();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignUuid('school_year_id')->constrained('school_years')->restrictOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->restrictOnDelete();
            $table->string('status', 20)->default('open');
            $table->string('reason_category', 20)->nullable();
            $table->timestampTz('opened_at');
            $table->timestampTz('returned_at')->nullable();
            $table->uuid('updated_by');
            $table->timestampsTz();
            $table->index(['school_year_id', 'school_id', 'status']);
        });
        Schema::connection('tenant')->create('active_search_contacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('active_search_case_id')->constrained('active_search_cases')->restrictOnDelete();
            $table->date('contact_date');
            $table->string('channel', 20);
            $table->string('outcome', 24);
            $table->uuid('recorded_by');
            $table->timestampsTz();
        });
        Schema::connection('tenant')->table('issued_documents', function (Blueprint $table): void {
            $table->foreignUuid('active_search_case_id')->nullable()->constrained('active_search_cases')->restrictOnDelete();
            $table->jsonb('referral_snapshot')->nullable();
        });

        $db = DB::connection('tenant');
        $db->statement('ALTER TABLE active_search_parameters ADD CONSTRAINT active_search_parameters_range CHECK (consecutive_days BETWEEN 1 AND 30 AND monthly_percentage BETWEEN 1 AND 100)');
        $db->statement("ALTER TABLE active_search_cases ADD CONSTRAINT active_search_cases_status CHECK (status IN ('open', 'returned', 'referred', 'closed'))");
        $db->statement("ALTER TABLE active_search_cases ADD CONSTRAINT active_search_cases_reason CHECK (reason_category IS NULL OR reason_category IN ('health', 'transport', 'work', 'move', 'other'))");
        $db->statement("ALTER TABLE active_search_contacts ADD CONSTRAINT active_search_contacts_channel CHECK (channel IN ('phone', 'visit', 'message'))");
        $db->statement("ALTER TABLE active_search_contacts ADD CONSTRAINT active_search_contacts_outcome CHECK (outcome IN ('no_answer', 'contacted', 'return_agreed', 'declined', 'other'))");
        $db->statement('ALTER TABLE issued_documents DROP CONSTRAINT issued_documents_kind_chk');
        $db->statement('ALTER TABLE issued_documents DROP CONSTRAINT issued_documents_transcript_chk');
        $db->statement("ALTER TABLE issued_documents ADD CONSTRAINT issued_documents_kind_chk CHECK (kind IN ('enrollment_declaration', 'school_transcript', 'active_search_referral'))");
        $db->statement("ALTER TABLE issued_documents ADD CONSTRAINT issued_documents_transcript_chk CHECK ((kind <> 'school_transcript' AND transcript_snapshot IS NULL AND transcript_sha256 IS NULL) OR (kind = 'school_transcript' AND transcript_snapshot IS NOT NULL AND transcript_sha256 ~ '^[0-9a-f]{64}$'))");
        $db->statement("ALTER TABLE issued_documents ADD CONSTRAINT issued_documents_referral_chk CHECK ((kind = 'active_search_referral' AND active_search_case_id IS NOT NULL AND referral_snapshot IS NOT NULL) OR (kind <> 'active_search_referral' AND active_search_case_id IS NULL AND referral_snapshot IS NULL))");
        $db->unprepared(<<<'SQL'
CREATE FUNCTION protect_active_search_referral() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF OLD.kind = 'active_search_referral' AND
       (NEW.kind IS DISTINCT FROM OLD.kind
        OR NEW.referral_snapshot IS DISTINCT FROM OLD.referral_snapshot
        OR NEW.active_search_case_id IS DISTINCT FROM OLD.active_search_case_id
        OR NEW.student_id IS DISTINCT FROM OLD.student_id
        OR NEW.school_id IS DISTINCT FROM OLD.school_id
        OR NEW.enrollment_id IS DISTINCT FROM OLD.enrollment_id
        OR NEW.verification_code IS DISTINCT FROM OLD.verification_code
        OR NEW.issued_at IS DISTINCT FROM OLD.issued_at
        OR NEW.snapshot_student_name IS DISTINCT FROM OLD.snapshot_student_name
        OR NEW.snapshot_school_name IS DISTINCT FROM OLD.snapshot_school_name
        OR NEW.snapshot_municipality_name IS DISTINCT FROM OLD.snapshot_municipality_name
        OR NEW.snapshot_school_year IS DISTINCT FROM OLD.snapshot_school_year) THEN
        RAISE EXCEPTION 'O encaminhamento emitido não pode ser alterado.' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END;
$$;
CREATE TRIGGER active_search_referral_immutable BEFORE UPDATE ON issued_documents
FOR EACH ROW EXECUTE FUNCTION protect_active_search_referral();
SQL);
    }

    public function down(): void
    {
        $db = DB::connection('tenant');
        $db->statement('DROP TRIGGER IF EXISTS active_search_referral_immutable ON issued_documents');
        $db->statement('DROP FUNCTION IF EXISTS protect_active_search_referral()');
        $db->statement('ALTER TABLE issued_documents DROP CONSTRAINT issued_documents_referral_chk');
        $db->statement('ALTER TABLE issued_documents DROP CONSTRAINT issued_documents_transcript_chk');
        $db->statement('ALTER TABLE issued_documents DROP CONSTRAINT issued_documents_kind_chk');
        $db->statement("ALTER TABLE issued_documents ADD CONSTRAINT issued_documents_kind_chk CHECK (kind IN ('enrollment_declaration', 'school_transcript'))");
        $db->statement("ALTER TABLE issued_documents ADD CONSTRAINT issued_documents_transcript_chk CHECK ((kind = 'enrollment_declaration' AND transcript_snapshot IS NULL AND transcript_sha256 IS NULL) OR (kind = 'school_transcript' AND transcript_snapshot IS NOT NULL AND transcript_sha256 ~ '^[0-9a-f]{64}$'))");
        Schema::connection('tenant')->table('issued_documents', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('active_search_case_id');
            $table->dropColumn('referral_snapshot');
        });
        Schema::connection('tenant')->dropIfExists('active_search_contacts');
        Schema::connection('tenant')->dropIfExists('active_search_cases');
        Schema::connection('tenant')->dropIfExists('active_search_parameters');
    }
};
