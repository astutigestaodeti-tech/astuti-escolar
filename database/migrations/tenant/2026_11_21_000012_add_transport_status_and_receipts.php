<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('student_enrollments', function (Blueprint $table): void {
            // Situação do transporte pertence à matrícula daquela escola/ano.
            $table->string('transport_status', 16)->nullable();
        });

        DB::connection('tenant')->statement("ALTER TABLE student_enrollments ADD CONSTRAINT student_enrollments_transport_status_check CHECK (transport_status IN ('active', 'not_requested', 'cancelled'))");

        Schema::connection('tenant')->create('transport_import_receipts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignUuid('school_year_id')->constrained('school_years')->restrictOnDelete();
            $table->uuid('created_by');
            $table->unsignedInteger('row_count');
            $table->unsignedInteger('changed_count');
            $table->timestampTz('created_at');
            $table->index(['school_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('transport_import_receipts');
        DB::connection('tenant')->statement('ALTER TABLE student_enrollments DROP CONSTRAINT IF EXISTS student_enrollments_transport_status_check');
        Schema::connection('tenant')->table('student_enrollments', function (Blueprint $table): void {
            $table->dropColumn('transport_status');
        });
    }
};
