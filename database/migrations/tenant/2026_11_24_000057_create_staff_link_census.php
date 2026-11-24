<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('staff_manager_census', function (Blueprint $table): void {
            $table->foreignUuid('staff_member_id')->primary()->constrained('staff_members')->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->unsignedSmallInteger('access_criterion')->nullable();
            $table->unsignedSmallInteger('employment_status')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();
        });
        Schema::connection('tenant')->create('class_teacher_assignment_census', function (Blueprint $table): void {
            $table->foreignUuid('assignment_id')->primary()->constrained('class_teacher_assignments')->restrictOnDelete();
            $table->unsignedSmallInteger('class_function');
            $table->unsignedSmallInteger('employment_status')->nullable();
            $table->jsonb('components');
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();
        });

        $db = DB::connection('tenant');
        $db->statement('ALTER TABLE staff_manager_census ADD CONSTRAINT manager_position_chk CHECK (position IN (1, 2))');
        $db->statement('ALTER TABLE staff_manager_census ADD CONSTRAINT manager_criterion_chk CHECK (access_criterion BETWEEN 1 AND 7)');
        $db->statement('ALTER TABLE staff_manager_census ADD CONSTRAINT manager_employment_chk CHECK (employment_status BETWEEN 1 AND 4)');
        $db->statement('ALTER TABLE class_teacher_assignment_census ADD CONSTRAINT assignment_function_chk CHECK (class_function BETWEEN 1 AND 9)');
        $db->statement('ALTER TABLE class_teacher_assignment_census ADD CONSTRAINT assignment_employment_chk CHECK (employment_status BETWEEN 1 AND 4)');
        $db->statement("ALTER TABLE class_teacher_assignment_census ADD CONSTRAINT assignment_components_chk CHECK (jsonb_typeof(components) = 'array' AND jsonb_array_length(components) <= 25)");
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('class_teacher_assignment_census');
        Schema::connection('tenant')->dropIfExists('staff_manager_census');
    }
};
