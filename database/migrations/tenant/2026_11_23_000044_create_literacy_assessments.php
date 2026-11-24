<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('literacy_cycle_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_year_id')->unique()->constrained('school_years')->restrictOnDelete();
            $table->boolean('retain_first');
            $table->boolean('retain_second');
            $table->uuid('updated_by');
            $table->timestampsTz();
        });
        Schema::connection('tenant')->create('literacy_assessments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_class_id')->constrained('school_classes')->restrictOnDelete();
            $table->foreignUuid('student_enrollment_id')->constrained('student_enrollments')->restrictOnDelete();
            $table->unsignedSmallInteger('term');
            $table->string('writing', 24);
            $table->string('reading', 24);
            $table->uuid('recorded_by');
            $table->uuid('updated_by');
            $table->timestampsTz();
            $table->unique(['school_class_id', 'student_enrollment_id', 'term'], 'literacy_assessments_class_enrollment_term_unique');
            $table->index(['school_class_id', 'term']);
        });
        $db = DB::connection('tenant');
        $db->statement('ALTER TABLE literacy_assessments ADD CONSTRAINT literacy_assessments_term_check CHECK (term BETWEEN 1 AND 4)');
        $db->statement("ALTER TABLE literacy_assessments ADD CONSTRAINT literacy_assessments_writing_check CHECK (writing IN ('pre_syllabic', 'syllabic_no_sound', 'syllabic_sound', 'syllabic_alphabetic', 'alphabetic'))");
        $db->statement("ALTER TABLE literacy_assessments ADD CONSTRAINT literacy_assessments_reading_check CHECK (reading IN ('none', 'words', 'sentences', 'fluent_texts'))");
        $db->statement('CREATE TRIGGER literacy_assessments_closed_class_guard BEFORE INSERT OR UPDATE OR DELETE ON literacy_assessments FOR EACH ROW EXECUTE FUNCTION guard_finalized_class()');
        $db->statement('ALTER TABLE student_final_results DROP CONSTRAINT student_final_results_choice_check');
        $db->statement("ALTER TABLE student_final_results ADD CONSTRAINT student_final_results_choice_check CHECK (result IN ('aprovado', 'retido', 'cursando', 'acompanhado') AND suggested_result IN ('aprovado', 'retido', 'cursando', 'acompanhado'))");
    }

    public function down(): void
    {
        // O CHECK anterior rejeita a reversão se existirem resultados novos.
        // Nunca converter decisões escolares confirmadas durante rollback.
        $db = DB::connection('tenant');
        $db->statement('ALTER TABLE student_final_results DROP CONSTRAINT student_final_results_choice_check');
        $db->statement("ALTER TABLE student_final_results ADD CONSTRAINT student_final_results_choice_check CHECK (result IN ('aprovado', 'retido', 'cursando') AND suggested_result IN ('aprovado', 'retido', 'cursando'))");
        Schema::connection('tenant')->dropIfExists('literacy_assessments');
        Schema::connection('tenant')->dropIfExists('literacy_cycle_settings');
    }
};
