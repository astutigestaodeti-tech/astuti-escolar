<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dados do aluno exigidos pelo Censo Escolar (Educacenso): nome social, sexo,
 * cor/raça, nacionalidade, naturalidade e CPF. Todos opcionais.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('students', function (Blueprint $table): void {
            $table->string('social_name', 180)->nullable();
            $table->string('sex', 6)->nullable();
            $table->string('race_color', 14)->nullable();
            $table->string('nationality', 12)->nullable();
            $table->string('birthplace_city', 120)->nullable();
            $table->char('birthplace_state', 2)->nullable();
            $table->char('cpf', 14)->nullable();
        });

        $db = DB::connection('tenant');
        $db->statement("ALTER TABLE students ADD CONSTRAINT students_sex_check CHECK (sex IS NULL OR sex IN ('female', 'male'))");
        $db->statement("ALTER TABLE students ADD CONSTRAINT students_race_color_check CHECK (race_color IS NULL OR race_color IN ('nao_declarada', 'branca', 'preta', 'parda', 'amarela', 'indigena'))");
        $db->statement("ALTER TABLE students ADD CONSTRAINT students_nationality_check CHECK (nationality IS NULL OR nationality IN ('brasileira', 'naturalizada', 'estrangeira'))");
        $db->statement("ALTER TABLE students ADD CONSTRAINT students_birthplace_state_check CHECK (birthplace_state IS NULL OR birthplace_state ~ '^[A-Z]{2}$')");
        $db->statement("ALTER TABLE students ADD CONSTRAINT students_cpf_check CHECK (cpf IS NULL OR cpf ~ '^[0-9]{3}\\.[0-9]{3}\\.[0-9]{3}-[0-9]{2}$')");
        // O mesmo CPF não pode estar em dois cadastros ativos.
        $db->statement('CREATE UNIQUE INDEX students_cpf_active_unique ON students (cpf) WHERE cpf IS NOT NULL AND deleted_at IS NULL');
    }

    public function down(): void
    {
        $db = DB::connection('tenant');
        $db->statement('DROP INDEX IF EXISTS students_cpf_active_unique');
        foreach (['sex', 'race_color', 'nationality', 'birthplace_state', 'cpf'] as $column) {
            $db->statement('ALTER TABLE students DROP CONSTRAINT IF EXISTS students_'.$column.'_check');
        }
        Schema::connection('tenant')->table('students', function (Blueprint $table): void {
            $table->dropColumn(['social_name', 'sex', 'race_color', 'nationality', 'birthplace_city', 'birthplace_state', 'cpf']);
        });
    }
};
