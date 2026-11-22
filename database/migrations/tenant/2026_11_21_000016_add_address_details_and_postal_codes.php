<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Complemento, cidade e UF no endereço do aluno, e a base local de CEPs da
 * Prefeitura (consulta sem enviar nada para fora do servidor).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('students', function (Blueprint $table): void {
            $table->string('address_complement', 120)->nullable();
            $table->string('address_city', 120)->nullable();
            $table->char('address_state', 2)->nullable();
        });

        DB::connection('tenant')->statement(
            "ALTER TABLE students ADD CONSTRAINT students_address_state_check CHECK (address_state IS NULL OR address_state ~ '^[A-Z]{2}$')"
        );

        Schema::connection('tenant')->create('postal_codes', function (Blueprint $table): void {
            $table->char('code', 9)->primary();
            $table->string('street', 180)->nullable();
            $table->string('district', 120)->nullable();
            $table->string('city', 120);
            $table->char('state', 2);
            $table->string('residence_zone', 5)->nullable();
            $table->string('source', 60);
            $table->timestamp('updated_at')->nullable();
        });

        DB::connection('tenant')->statement(
            "ALTER TABLE postal_codes ADD CONSTRAINT postal_codes_code_check CHECK (code ~ '^[0-9]{5}-[0-9]{3}$')"
        );
        DB::connection('tenant')->statement(
            "ALTER TABLE postal_codes ADD CONSTRAINT postal_codes_zone_check CHECK (residence_zone IS NULL OR residence_zone IN ('urban', 'rural'))"
        );
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('postal_codes');
        DB::connection('tenant')->statement('ALTER TABLE students DROP CONSTRAINT IF EXISTS students_address_state_check');
        Schema::connection('tenant')->table('students', function (Blueprint $table): void {
            $table->dropColumn(['address_complement', 'address_city', 'address_state']);
        });
    }
};
