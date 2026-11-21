<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('student_import_receipts', function (Blueprint $table): void {
            // Somente metadados. O .xlsx não é armazenado no servidor nem no banco.
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignUuid('school_year_id')->constrained('school_years')->restrictOnDelete();
            $table->uuid('created_by');
            $table->unsignedInteger('student_count');
            $table->timestampTz('created_at');
            $table->index(['school_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('student_import_receipts');
    }
};
