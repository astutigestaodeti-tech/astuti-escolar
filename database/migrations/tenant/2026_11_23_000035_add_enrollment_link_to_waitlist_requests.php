<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pedido de vaga atendido pela própria lista: guarda o aluno e a matrícula
 * criados, para que a fila mostre o resultado e nunca matricule duas vezes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('waitlist_requests', function (Blueprint $table): void {
            $table->foreignUuid('student_id')->nullable()->constrained('students')->restrictOnDelete();
            $table->foreignUuid('enrollment_id')->nullable()->constrained('student_enrollments')->restrictOnDelete();
            $table->unique('enrollment_id', 'waitlist_requests_enrollment_unique');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('waitlist_requests', function (Blueprint $table): void {
            $table->dropUnique('waitlist_requests_enrollment_unique');
            $table->dropConstrainedForeignId('enrollment_id');
            $table->dropConstrainedForeignId('student_id');
        });
    }
};
