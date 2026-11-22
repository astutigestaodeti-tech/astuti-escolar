<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de saída: a quem o aluno foi entregue, quando e por quem.
 * Guarda o nome e a relação no momento da entrega, para o registro não
 * mudar se o cadastro da pessoa for editado ou removido depois.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('student_exits', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->string('person_type', 20);
            $table->uuid('person_id')->nullable();
            $table->string('person_name', 180);
            $table->string('person_relationship', 80)->nullable();
            $table->uuid('recorded_by')->nullable();
            $table->timestampTz('exited_at');
            $table->timestampsTz();
            $table->index(['school_id', 'exited_at'], 'student_exits_school_day_idx');
            $table->index(['student_id', 'exited_at'], 'student_exits_student_idx');
        });

        DB::connection('tenant')->statement("ALTER TABLE student_exits ADD CONSTRAINT student_exits_person_type_check CHECK (person_type IN ('guardian', 'authorization'))");
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('student_exits');
    }
};
