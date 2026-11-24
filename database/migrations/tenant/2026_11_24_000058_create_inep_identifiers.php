<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Códigos que o Educacenso devolve no arquivo de exportação: ID Inep da
 * pessoa (aluno ou servidor), código Inep da turma e código da matrícula.
 * Só códigos: o arquivo recebido não é guardado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('inep_identifiers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // student, staff_member, school_class ou student_enrollment.
            $table->string('kind', 24);
            $table->uuid('entity_id');
            $table->string('inep_code', 20);
            $table->unsignedSmallInteger('census_year');
            $table->uuid('imported_by');
            $table->timestampsTz();
            $table->unique(['kind', 'entity_id']);
            $table->index(['kind', 'inep_code']);
        });

        $db = DB::connection('tenant');
        $db->statement("ALTER TABLE inep_identifiers ADD CONSTRAINT inep_identifiers_kind_chk CHECK (kind IN ('student', 'staff_member', 'school_class', 'student_enrollment'))");
        $db->statement("ALTER TABLE inep_identifiers ADD CONSTRAINT inep_identifiers_code_chk CHECK (inep_code ~ '^[0-9]{1,20}$' AND (kind NOT IN ('student', 'staff_member') OR inep_code ~ '^[0-9]{12}$'))");
        // A mesma pessoa pode ser servidor em duas escolas (dois cadastros); aluno, turma e matrícula não repetem código.
        $db->statement("CREATE UNIQUE INDEX inep_identifiers_code_unique ON inep_identifiers (kind, inep_code) WHERE kind <> 'staff_member'");
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('inep_identifiers');
    }
};
