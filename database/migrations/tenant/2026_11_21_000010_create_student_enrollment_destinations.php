<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Informação adicional da saída da rede, sem alterar o formato da
        // matrícula histórica que chega dos bancos MariaDB em uma cópia.
        Schema::connection('tenant')->create('student_enrollment_destinations', function (Blueprint $table): void {
            // Nenhum dado histórico pode desaparecer por exclusão em cascata.
            // Nas bases descartáveis, limpar primeiro este complemento.
            $table->foreignUuid('enrollment_id')->primary()->constrained('student_enrollments')->restrictOnDelete();
            $table->string('destination_name', 180);
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('student_enrollment_destinations');
    }
};
