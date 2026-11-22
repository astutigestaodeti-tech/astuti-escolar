<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chamada por turma/data, com marca por aluno. Observação e motivo ficam
 * apenas no banco municipal; a auditoria jamais recebe seu conteúdo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('attendance_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_class_id')->constrained('school_classes')->restrictOnDelete();
            $table->date('attendance_date');
            $table->uuid('recorded_by');
            $table->timestampTz('recorded_at');
            $table->uuid('last_corrected_by')->nullable();
            $table->timestampTz('last_corrected_at')->nullable();
            $table->string('correction_reason', 500)->nullable();
            $table->timestampsTz();

            $table->unique(['school_class_id', 'attendance_date'], 'attendance_sessions_class_date_unique');
        });

        Schema::connection('tenant')->create('attendance_marks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('attendance_session_id')->constrained('attendance_sessions')->restrictOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->restrictOnDelete();
            $table->string('status', 12);
            $table->string('observation', 160)->nullable();
            $table->timestampsTz();

            $table->unique(['attendance_session_id', 'student_id'], 'attendance_marks_session_student_unique');
            $table->index(['student_id', 'attendance_session_id'], 'attendance_marks_student_session_idx');
        });

        DB::connection('tenant')->statement("ALTER TABLE attendance_marks ADD CONSTRAINT attendance_marks_status_check CHECK (status IN ('present', 'absent', 'justified'))");
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('attendance_marks');
        Schema::connection('tenant')->dropIfExists('attendance_sessions');
    }
};
