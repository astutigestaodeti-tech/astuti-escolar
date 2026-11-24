<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Transtornos que impactam a aprendizagem (registro 30, campos 30 a 35) e
 * atividades do AEE (registro 60, campos 10 a 20). Dado sensível de saúde:
 * mesmo acesso e mesma auditoria da educação especial (só nomes de campos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('student_special_education', function (Blueprint $table): void {
            $table->jsonb('learning_disorders')->default('[]');
            $table->jsonb('aee_activities')->default('[]');
        });

        $db = DB::connection('tenant');
        $db->statement("ALTER TABLE student_special_education ADD CONSTRAINT student_special_education_disorders_chk CHECK (jsonb_typeof(learning_disorders) = 'array')");
        $db->statement("ALTER TABLE student_special_education ADD CONSTRAINT student_special_education_aee_activities_chk CHECK (jsonb_typeof(aee_activities) = 'array')");
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('student_special_education', function (Blueprint $table): void {
            $table->dropColumn(['learning_disorders', 'aee_activities']);
        });
    }
};
