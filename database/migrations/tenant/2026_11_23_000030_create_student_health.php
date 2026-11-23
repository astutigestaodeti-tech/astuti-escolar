<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ficha de saúde mínima (LGPD art. 11, dado sensível de criança):
 * só o que a escola precisa saber e fazer. Sem diagnóstico, CID ou laudo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('student_health_profiles', function (Blueprint $table): void {
            $table->foreignUuid('student_id')->primary()->constrained('students')->restrictOnDelete();
            $table->string('blood_type', 8)->nullable();
            $table->string('informed_by', 120)->nullable();
            $table->date('informed_on')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();
        });

        Schema::connection('tenant')->create('student_health_alerts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained('students')->restrictOnDelete();
            $table->string('kind', 20);
            $table->string('severity', 12);
            $table->string('alert_text', 160);
            $table->string('care_instructions', 400)->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();
            $table->index(['student_id', 'severity'], 'student_health_alerts_student_idx');
        });

        $db = DB::connection('tenant');
        $db->statement("ALTER TABLE student_health_profiles ADD CONSTRAINT student_health_profiles_blood_check CHECK (blood_type IS NULL OR blood_type IN ('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'unknown'))");
        $db->statement("ALTER TABLE student_health_alerts ADD CONSTRAINT student_health_alerts_kind_check CHECK (kind IN ('allergy', 'condition', 'care'))");
        $db->statement("ALTER TABLE student_health_alerts ADD CONSTRAINT student_health_alerts_severity_check CHECK (severity IN ('critical', 'attention'))");
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('student_health_alerts');
        Schema::connection('tenant')->dropIfExists('student_health_profiles');
    }
};
