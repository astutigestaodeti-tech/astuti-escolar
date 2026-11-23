<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('staff_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->string('full_name', 160);
            $table->string('registration_number', 40)->nullable();
            $table->string('role', 20);
            $table->string('institutional_email', 160)->nullable();
            $table->string('phone', 11)->nullable();
            $table->string('status', 12)->default('active');
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->string('deletion_reason', 500)->nullable();
            $table->index(['school_id', 'status'], 'staff_members_school_status_idx');
        });

        Schema::connection('tenant')->create('class_teacher_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_class_id')->constrained('school_classes')->restrictOnDelete();
            $table->foreignUuid('staff_member_id')->constrained('staff_members')->restrictOnDelete();
            $table->string('subject', 80)->nullable();
            $table->boolean('is_main')->default(false);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->timestampsTz();
            $table->index(['school_class_id', 'starts_on'], 'class_teachers_class_start_idx');
            $table->index('staff_member_id', 'class_teachers_staff_idx');
        });

        $db = DB::connection('tenant');
        $db->statement("ALTER TABLE staff_members ADD CONSTRAINT staff_members_role_check CHECK (role IN ('teacher', 'assistant', 'coordinator', 'director', 'secretary', 'other'))");
        $db->statement("ALTER TABLE staff_members ADD CONSTRAINT staff_members_status_check CHECK (status IN ('active', 'inactive'))");
        $db->statement('ALTER TABLE class_teacher_assignments ADD CONSTRAINT class_teachers_dates_check CHECK (ends_on IS NULL OR ends_on >= starts_on)');
        $db->statement('CREATE UNIQUE INDEX class_teachers_one_open_main_idx ON class_teacher_assignments (school_class_id) WHERE is_main = true AND ends_on IS NULL');
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('class_teacher_assignments');
        Schema::connection('tenant')->dropIfExists('staff_members');
    }
};
