<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('student_enrollments', function (Blueprint $table): void {
            $table->smallInteger('inep_stage_code')->nullable();
        });

        DB::connection('tenant')->statement('ALTER TABLE student_enrollments ADD CONSTRAINT student_enrollments_inep_stage_code_chk CHECK (inep_stage_code IN (1, 2, 14, 15, 16, 17, 18, 19, 20, 21, 41))');
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('student_enrollments', function (Blueprint $table): void {
            $table->dropColumn('inep_stage_code');
        });
    }
};
