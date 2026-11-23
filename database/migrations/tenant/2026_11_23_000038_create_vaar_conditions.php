<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('vaar_conditions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedSmallInteger('reference_year');
            $table->unsignedTinyInteger('condition_number');
            $table->string('status', 16)->default('pendente');
            $table->string('responsible', 120)->nullable();
            $table->string('evidence_path', 255)->nullable();
            $table->string('evidence_name', 180)->nullable();
            $table->string('evidence_mime', 80)->nullable();
            $table->timestampTz('evidence_uploaded_at')->nullable();
            $table->uuid('updated_by');
            $table->timestampsTz();

            $table->unique(['reference_year', 'condition_number'], 'vaar_conditions_year_number_unique');
        });

        $db = DB::connection('tenant');
        $db->statement('ALTER TABLE vaar_conditions ADD CONSTRAINT vaar_conditions_year_check CHECK (reference_year BETWEEN 2024 AND 2100)');
        $db->statement('ALTER TABLE vaar_conditions ADD CONSTRAINT vaar_conditions_number_check CHECK (condition_number BETWEEN 1 AND 5)');
        $db->statement("ALTER TABLE vaar_conditions ADD CONSTRAINT vaar_conditions_status_check CHECK (status IN ('cumprida', 'a_comprovar', 'pendente'))");
        $db->statement('ALTER TABLE vaar_conditions ADD CONSTRAINT vaar_conditions_evidence_check CHECK ((evidence_path IS NULL AND evidence_name IS NULL AND evidence_mime IS NULL AND evidence_uploaded_at IS NULL) OR (evidence_path IS NOT NULL AND evidence_name IS NOT NULL AND evidence_mime IS NOT NULL AND evidence_uploaded_at IS NOT NULL))');
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('vaar_conditions');
    }
};
