<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dados da turma para o Censo Escolar (registro 20 do leiaute do Educacenso).
 * Sem dados pessoais. Os códigos seguem as opções e tabelas oficiais do Inep;
 * as combinações entre campos são conferidas pela aplicação a partir do
 * leiaute do ano (resources/educacenso).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('school_class_census', function (Blueprint $table): void {
            $table->foreignUuid('school_class_id')->primary()->constrained('school_classes')->restrictOnDelete();
            // Código da turma no sistema próprio (campo 3): estável entre os anos,
            // porque o Inep o devolve no arquivo de exportação.
            $table->char('census_code', 20)->unique();
            $table->unsignedSmallInteger('inep_stage_code')->nullable();
            $table->unsignedSmallInteger('mediation')->nullable();
            $table->unsignedSmallInteger('class_type')->nullable();
            foreach (['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'] as $day) {
                $table->char('schedule_'.$day, 11)->nullable();
            }
            $table->jsonb('complementary_activities')->default('[]');
            $table->unsignedSmallInteger('differentiated_location')->nullable();
            $table->boolean('special_class')->nullable();
            $table->unsignedSmallInteger('organization')->nullable();
            $table->boolean('alternance')->nullable();
            $table->jsonb('curricular_areas')->default('[]');
            $table->boolean('bilingual_deaf')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();
        });

        $db = DB::connection('tenant');
        $db->statement("ALTER TABLE school_class_census ADD CONSTRAINT school_class_census_code_chk CHECK (census_code ~ '^[A-Z0-9]{20}$')");
        $db->statement('ALTER TABLE school_class_census ADD CONSTRAINT school_class_census_mediation_chk CHECK (mediation IS NULL OR mediation IN (1, 2, 3))');
        $db->statement('ALTER TABLE school_class_census ADD CONSTRAINT school_class_census_type_chk CHECK (class_type IS NULL OR class_type IN (4, 5, 6, 9))');
        $db->statement('ALTER TABLE school_class_census ADD CONSTRAINT school_class_census_location_chk CHECK (differentiated_location IS NULL OR differentiated_location IN (0, 1, 2, 3))');
        $db->statement('ALTER TABLE school_class_census ADD CONSTRAINT school_class_census_organization_chk CHECK (organization IS NULL OR organization BETWEEN 1 AND 5)');
        $db->statement("ALTER TABLE school_class_census ADD CONSTRAINT school_class_census_activities_chk CHECK (jsonb_typeof(complementary_activities) = 'array')");
        $db->statement("ALTER TABLE school_class_census ADD CONSTRAINT school_class_census_areas_chk CHECK (jsonb_typeof(curricular_areas) = 'array')");
        foreach (['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'] as $day) {
            $db->statement("ALTER TABLE school_class_census ADD CONSTRAINT school_class_census_{$day}_chk CHECK (schedule_{$day} IS NULL OR schedule_{$day} ~ '^([01][0-9]|2[0-3]):[0-5][05]-([01][0-9]|2[0-3]):[0-5][05]$')");
        }
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('school_class_census');
    }
};
