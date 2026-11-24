<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('class_councils', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_class_id')->constrained('school_classes')->restrictOnDelete();
            $table->foreignUuid('academic_period_id')->constrained('academic_periods')->restrictOnDelete();
            $table->string('status', 20)->default('in_progress');
            $table->date('meeting_date')->nullable();
            $table->string('participants', 500)->nullable();
            $table->string('class_actions', 1000)->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->uuid('closed_by')->nullable();
            $table->timestampTz('reopened_at')->nullable();
            $table->uuid('reopened_by')->nullable();
            $table->string('reopen_reason', 500)->nullable();
            $table->timestampsTz();
            $table->unique(['school_class_id', 'academic_period_id']);
        });
        Schema::connection('tenant')->create('class_council_students', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('class_council_id')->constrained('class_councils')->restrictOnDelete();
            $table->foreignUuid('student_enrollment_id')->constrained('student_enrollments')->restrictOnDelete();
            $table->string('opinion', 500)->nullable();
            $table->string('referral', 40)->nullable();
            $table->string('observation', 500)->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();
            $table->unique(['class_council_id', 'student_enrollment_id']);
        });
        $db = DB::connection('tenant');
        $db->statement("ALTER TABLE class_councils ADD CONSTRAINT class_councils_status_chk CHECK (status IN ('not_started', 'in_progress', 'completed'))");
        $db->statement("ALTER TABLE class_councils ADD CONSTRAINT class_councils_closed_chk CHECK ((status = 'completed') = (closed_at IS NOT NULL))");
        $db->statement("ALTER TABLE class_council_students ADD CONSTRAINT council_referral_chk CHECK (referral IN ('recovery', 'support', 'aee', 'team', 'active_search', 'family', 'none'))");
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('class_council_students');
        Schema::connection('tenant')->dropIfExists('class_councils');
    }
};
