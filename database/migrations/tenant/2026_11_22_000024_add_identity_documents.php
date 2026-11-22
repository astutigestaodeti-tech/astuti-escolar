<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Documentos de identificação que os cadastros antigos e a ficha de
 * matrícula costumam ter: RG (número, órgão, UF e expedição) e certidão
 * de nascimento do aluno; RG do responsável. Todos opcionais.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('students', function (Blueprint $table): void {
            $table->string('rg_number', 20)->nullable();
            $table->string('rg_issuer', 20)->nullable();
            $table->char('rg_state', 2)->nullable();
            $table->date('rg_issued_at')->nullable();
            $table->string('birth_certificate', 80)->nullable();
        });
        Schema::connection('tenant')->table('guardians', function (Blueprint $table): void {
            $table->string('rg_number', 20)->nullable();
            $table->char('rg_state', 2)->nullable();
        });

        DB::connection('tenant')->statement("ALTER TABLE students ADD CONSTRAINT students_rg_number_format CHECK (rg_number IS NULL OR rg_number ~ '^[0-9]{4,14}[0-9X]?$')");
        DB::connection('tenant')->statement("ALTER TABLE guardians ADD CONSTRAINT guardians_rg_number_format CHECK (rg_number IS NULL OR rg_number ~ '^[0-9]{4,14}[0-9X]?$')");
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('students', function (Blueprint $table): void {
            $table->dropColumn(['rg_number', 'rg_issuer', 'rg_state', 'rg_issued_at', 'birth_certificate']);
        });
        Schema::connection('tenant')->table('guardians', function (Blueprint $table): void {
            $table->dropColumn(['rg_number', 'rg_state']);
        });
    }
};
