<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('issued_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('kind', 32);
            $table->foreignUuid('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignUuid('enrollment_id')->constrained('student_enrollments')->restrictOnDelete();
            $table->string('verification_code', 10)->unique();
            $table->uuid('issued_by');
            $table->timestampTz('issued_at');
            $table->string('purpose', 120)->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->uuid('revoked_by')->nullable();
            $table->string('revoke_reason', 500)->nullable();
            $table->string('snapshot_student_name', 160);
            $table->string('snapshot_school_name', 160);
            $table->string('snapshot_municipality_name', 160);
            $table->unsignedSmallInteger('snapshot_school_year');
            $table->string('snapshot_stage', 120)->nullable();
            $table->string('snapshot_class_name', 120)->nullable();
            $table->string('snapshot_shift', 80)->nullable();
            $table->timestampsTz();
            $table->index(['school_id', 'student_id', 'issued_at'], 'issued_documents_school_student_date_idx');
        });

        DB::connection('tenant')->statement("ALTER TABLE issued_documents ADD CONSTRAINT issued_documents_kind_chk CHECK (kind = 'enrollment_declaration')");
        DB::connection('tenant')->statement("ALTER TABLE issued_documents ADD CONSTRAINT issued_documents_code_chk CHECK (verification_code ~ '^[A-HJ-NP-Z2-9]{10}$')");
        DB::connection('tenant')->statement('ALTER TABLE issued_documents ADD CONSTRAINT issued_documents_revoke_chk CHECK ((revoked_at IS NULL AND revoked_by IS NULL AND revoke_reason IS NULL) OR (revoked_at IS NOT NULL AND revoked_by IS NOT NULL AND revoke_reason IS NOT NULL))');
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('issued_documents');
    }
};
