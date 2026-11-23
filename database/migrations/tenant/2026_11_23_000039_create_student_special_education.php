<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Educação especial no padrão do Censo Escolar (LGPD art. 11 e 14: dado
 * sensível de criança). Só categorias do Censo, recursos e o atendimento;
 * sem CID, diagnóstico, laudo anexado ou texto livre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('student_special_education', function (Blueprint $table): void {
            $table->foreignUuid('student_id')->primary()->constrained('students')->restrictOnDelete();
            $table->jsonb('conditions')->default('[]');
            $table->jsonb('exam_resources')->default('[]');
            $table->string('aee_status', 16)->nullable();
            $table->foreignUuid('aee_school_id')->nullable()->constrained('schools')->restrictOnDelete();
            $table->date('aee_started_on')->nullable();
            $table->boolean('support_professional')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();
        });

        $db = DB::connection('tenant');
        $db->statement("ALTER TABLE student_special_education ADD CONSTRAINT student_special_education_conditions_chk CHECK (jsonb_typeof(conditions) = 'array')");
        $db->statement("ALTER TABLE student_special_education ADD CONSTRAINT student_special_education_resources_chk CHECK (jsonb_typeof(exam_resources) = 'array')");
        $db->statement("ALTER TABLE student_special_education ADD CONSTRAINT student_special_education_aee_chk CHECK (aee_status IS NULL OR aee_status IN ('participa', 'aguardando', 'nao_participa'))");
        $db->statement("ALTER TABLE student_special_education ADD CONSTRAINT student_special_education_aee_school_chk CHECK (aee_status = 'participa' OR (aee_school_id IS NULL AND aee_started_on IS NULL))");
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('student_special_education');
    }
};
