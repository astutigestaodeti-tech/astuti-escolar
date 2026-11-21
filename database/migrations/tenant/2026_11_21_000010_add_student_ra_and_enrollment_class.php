<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('students', function (Blueprint $table): void {
            // RA pertence ao aluno no municipio. Ausente continua NULL, nunca inventado.
            $table->string('ra', 32)->nullable()->unique('students_ra_unique');
        });

        Schema::connection('tenant')->table('school_classes', function (Blueprint $table): void {
            // A FK composta impede matricula numa turma de outra escola/ano.
            $table->unique(['id', 'school_id', 'school_year_id'], 'school_classes_scope_unique');
        });

        Schema::connection('tenant')->table('student_enrollments', function (Blueprint $table): void {
            $table->uuid('school_class_id')->nullable();
            $table->foreign(['school_class_id', 'school_id', 'school_year_id'], 'enrollment_class_scope_fk')
                ->references(['id', 'school_id', 'school_year_id'])
                ->on('school_classes')
                ->restrictOnDelete();
            $table->index(['school_class_id', 'school_id', 'school_year_id'], 'enrollment_class_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('student_enrollments', function (Blueprint $table): void {
            $table->dropForeign('enrollment_class_scope_fk');
            $table->dropIndex('enrollment_class_scope_idx');
            $table->dropColumn('school_class_id');
        });

        Schema::connection('tenant')->table('school_classes', function (Blueprint $table): void {
            $table->dropUnique('school_classes_scope_unique');
        });

        Schema::connection('tenant')->table('students', function (Blueprint $table): void {
            $table->dropUnique('students_ra_unique');
            $table->dropColumn('ra');
        });
    }
};
