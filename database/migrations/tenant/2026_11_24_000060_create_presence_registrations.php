<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Controle do operador do Sistema Presença: qual aluno já teve a frequência
 * do período lançada no sistema do MEC. Não guarda se o aluno é beneficiário
 * do Bolsa Família nem o motivo da baixa frequência, que ficam no Presença.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('presence_registrations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignUuid('school_year_id')->constrained('school_years')->restrictOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->restrictOnDelete();
            $table->unsignedSmallInteger('period');
            $table->uuid('registered_by');
            $table->timestampTz('registered_at');
            $table->timestampsTz();
            $table->unique(['school_id', 'school_year_id', 'student_id', 'period']);
        });

        DB::connection('tenant')->statement('ALTER TABLE presence_registrations ADD CONSTRAINT presence_registrations_period_chk CHECK (period BETWEEN 1 AND 5)');
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('presence_registrations');
    }
};
