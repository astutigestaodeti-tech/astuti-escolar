<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dados do aluno que só o Censo Escolar pede (registro 30 do Educacenso):
 * filiação, municípios e países em código, povo indígena e localização
 * diferenciada da residência. Códigos conferidos pela aplicação contra as
 * tabelas oficiais do ano (resources/educacenso).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('student_census', function (Blueprint $table): void {
            $table->foreignUuid('student_id')->primary()->constrained('students')->restrictOnDelete();
            // Campo 8: 0 = não declarado/ignorado; 1 = filiação 1 e/ou 2 informada.
            $table->unsignedSmallInteger('filiation')->nullable();
            $table->string('parent1_name', 100)->nullable();
            $table->string('parent2_name', 100)->nullable();
            $table->unsignedInteger('indigenous_people_code')->nullable();
            $table->unsignedSmallInteger('nationality_country_code')->nullable();
            $table->unsignedInteger('birth_municipality_code')->nullable();
            $table->unsignedSmallInteger('residence_country_code')->nullable();
            $table->unsignedInteger('residence_municipality_code')->nullable();
            $table->unsignedSmallInteger('differentiated_location')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();
        });

        $db = DB::connection('tenant');
        $db->statement('ALTER TABLE student_census ADD CONSTRAINT student_census_filiation_chk CHECK (filiation IS NULL OR filiation IN (0, 1))');
        $db->statement('ALTER TABLE student_census ADD CONSTRAINT student_census_parents_chk CHECK (filiation = 1 OR (parent1_name IS NULL AND parent2_name IS NULL))');
        $db->statement('ALTER TABLE student_census ADD CONSTRAINT student_census_parent_given_chk CHECK (filiation IS DISTINCT FROM 1 OR parent1_name IS NOT NULL OR parent2_name IS NOT NULL)');
        $db->statement('ALTER TABLE student_census ADD CONSTRAINT student_census_birth_municipality_chk CHECK (birth_municipality_code IS NULL OR birth_municipality_code BETWEEN 1100000 AND 5399999)');
        $db->statement('ALTER TABLE student_census ADD CONSTRAINT student_census_residence_municipality_chk CHECK (residence_municipality_code IS NULL OR residence_municipality_code BETWEEN 1100000 AND 5399999)');
        $db->statement('ALTER TABLE student_census ADD CONSTRAINT student_census_residence_country_chk CHECK (residence_country_code IS NULL OR residence_country_code IN (32, 68, 76, 170, 254, 328, 600, 604, 740, 858, 862))');
        $db->statement('ALTER TABLE student_census ADD CONSTRAINT student_census_location_chk CHECK (differentiated_location IS NULL OR differentiated_location IN (1, 2, 3, 7, 8))');
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('student_census');
    }
};
