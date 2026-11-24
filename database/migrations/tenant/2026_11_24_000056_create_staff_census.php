<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dados do servidor que só o Censo Escolar pede (registro 30 de gestores e
 * profissionais em sala): identificação, nacionalidade, deficiência,
 * escolaridade e formação. Códigos conferidos pela aplicação contra as
 * tabelas oficiais do ano (resources/educacenso). Deficiência é dado de
 * saúde: só aparece na tela de edição e a auditoria guarda nomes de campos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('staff_census', function (Blueprint $table): void {
            $table->foreignUuid('staff_member_id')->primary()->constrained('staff_members')->restrictOnDelete();
            $table->string('cpf', 11)->nullable();
            $table->date('birth_date')->nullable();
            $table->unsignedSmallInteger('filiation')->nullable();
            $table->string('parent1_name', 100)->nullable();
            $table->string('parent2_name', 100)->nullable();
            $table->string('sex', 10)->nullable();
            $table->string('race_color', 20)->nullable();
            $table->unsignedInteger('indigenous_people_code')->nullable();
            $table->string('nationality', 20)->nullable();
            $table->unsignedSmallInteger('nationality_country_code')->nullable();
            $table->unsignedInteger('birth_municipality_code')->nullable();
            // Campos 18 a 28: lista de chaves (mesmas da educação especial); vazia = sem deficiência.
            $table->jsonb('conditions')->nullable();
            $table->unsignedSmallInteger('education_level')->nullable();
            $table->unsignedSmallInteger('high_school_type')->nullable();
            // Até 3 cursos: [{curso, ano, ies}] (campos 58 a 66).
            $table->jsonb('higher_courses')->nullable();
            // Complementação pedagógica: até 3 códigos de área (campos 67 a 69).
            $table->jsonb('pedagogical_areas')->nullable();
            // Até 6 pós-graduações: [{tipo, area, ano}] (campos 70 a 87).
            $table->jsonb('postgraduates')->nullable();
            $table->boolean('no_postgraduate')->default(false);
            // Formação continuada (89 a 108): null = não informada; [] = nenhuma (109).
            $table->jsonb('continuing_education')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();
        });

        $db = DB::connection('tenant');
        $db->statement("ALTER TABLE staff_census ADD CONSTRAINT staff_census_cpf_chk CHECK (cpf IS NULL OR cpf ~ '^[0-9]{11}$')");
        $db->statement('ALTER TABLE staff_census ADD CONSTRAINT staff_census_filiation_chk CHECK (filiation IS NULL OR filiation IN (0, 1))');
        $db->statement('ALTER TABLE staff_census ADD CONSTRAINT staff_census_parents_chk CHECK (filiation = 1 OR (parent1_name IS NULL AND parent2_name IS NULL))');
        $db->statement("ALTER TABLE staff_census ADD CONSTRAINT staff_census_sex_chk CHECK (sex IS NULL OR sex IN ('male', 'female'))");
        $db->statement("ALTER TABLE staff_census ADD CONSTRAINT staff_census_race_chk CHECK (race_color IS NULL OR race_color IN ('branca', 'preta', 'parda', 'amarela', 'indigena', 'nao_declarada'))");
        $db->statement("ALTER TABLE staff_census ADD CONSTRAINT staff_census_nationality_chk CHECK (nationality IS NULL OR nationality IN ('brasileira', 'naturalizada', 'estrangeira'))");
        $db->statement('ALTER TABLE staff_census ADD CONSTRAINT staff_census_birth_municipality_chk CHECK (birth_municipality_code IS NULL OR birth_municipality_code BETWEEN 1100000 AND 5399999)');
        $db->statement('ALTER TABLE staff_census ADD CONSTRAINT staff_census_education_chk CHECK (education_level IS NULL OR education_level IN (1, 2, 6, 7))');
        $db->statement('ALTER TABLE staff_census ADD CONSTRAINT staff_census_high_school_chk CHECK (high_school_type IS NULL OR (high_school_type BETWEEN 1 AND 4 AND education_level IN (6, 7)))');
        $db->statement('ALTER TABLE staff_census ADD CONSTRAINT staff_census_courses_chk CHECK (higher_courses IS NULL OR education_level = 6)');
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('staff_census');
    }
};
