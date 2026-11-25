<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('lesson_records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_class_id')->constrained('school_classes')->restrictOnDelete();
            $table->date('lesson_date');
            $table->foreignUuid('curricular_component_id')->nullable()->constrained('curricular_components')->restrictOnDelete();
            $table->string('content', 1000);
            $table->string('activity', 500)->nullable();
            $table->jsonb('bncc_codes')->default('[]');
            $table->uuid('recorded_by');
            $table->uuid('updated_by');
            $table->string('correction_reason', 500)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->uuid('operation_id')->nullable()->unique();
            $table->timestampsTz();
        });

        $db = DB::connection('tenant');
        // PostgreSQL: NULL representa um único registro polivalente por data.
        $db->statement('CREATE UNIQUE INDEX lesson_records_class_date_component_unique ON lesson_records (school_class_id, lesson_date, curricular_component_id) NULLS NOT DISTINCT');
        $db->statement('ALTER TABLE lesson_records ADD CONSTRAINT lesson_records_version_check CHECK (version >= 1 AND length(btrim(content)) > 0)');
        $db->statement("ALTER TABLE lesson_records ADD CONSTRAINT lesson_records_bncc_check CHECK (jsonb_typeof(bncc_codes) = 'array' AND jsonb_array_length(bncc_codes) <= 10)");
        $db->statement('CREATE TRIGGER lesson_records_closed_class_guard BEFORE INSERT OR UPDATE OR DELETE ON lesson_records FOR EACH ROW EXECUTE FUNCTION guard_finalized_class()');
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('lesson_records');
    }
};
