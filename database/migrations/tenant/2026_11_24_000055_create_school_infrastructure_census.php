<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('school_infrastructure_census', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->unsignedSmallInteger('census_year');
            $table->jsonb('answers');
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();
            $table->unique(['school_id', 'census_year']);
        });

        $db = DB::connection('tenant');
        $db->statement("ALTER TABLE school_infrastructure_census ADD CONSTRAINT school_infrastructure_census_answers_chk CHECK (jsonb_typeof(answers) = 'object')");
        $campos = implode(', ', array_map(fn (int $campo): string => "'{$campo}'", range(3, 187)));
        $db->statement("ALTER TABLE school_infrastructure_census ADD CONSTRAINT school_infrastructure_census_fields_chk CHECK (answers - ARRAY[{$campos}]::text[] = '{}'::jsonb)");
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('school_infrastructure_census');
    }
};
