<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Parâmetros anuais do simulador de receita da Conferência do Censo,
 * mantidos pela gestão municipal (sem precisar mexer no .env).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('census_parameters', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedSmallInteger('reference_year')->unique();
            $table->decimal('vaaf_base', 12, 2);
            $table->string('source_note', 200);
            $table->uuid('updated_by');
            $table->timestampsTz();
        });
        $db = DB::connection('tenant');
        $db->statement('ALTER TABLE census_parameters ADD CONSTRAINT census_parameters_year_chk CHECK (reference_year BETWEEN 2024 AND 2100)');
        $db->statement('ALTER TABLE census_parameters ADD CONSTRAINT census_parameters_vaaf_chk CHECK (vaaf_base > 0 AND vaaf_base < 100000)');
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('census_parameters');
    }
};
