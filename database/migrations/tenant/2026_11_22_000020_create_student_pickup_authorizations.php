<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('student_pickup_authorizations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained('students')->restrictOnDelete();
            $table->string('full_name', 180);
            $table->string('relationship', 80)->nullable();
            $table->string('phone', 11);
            $table->string('document', 40)->nullable();
            $table->date('expires_at')->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestampsTz();
            $table->index(['student_id', 'expires_at'], 'student_pickup_student_expiry_idx');
        });

        DB::connection('tenant')->statement("ALTER TABLE student_pickup_authorizations ADD CONSTRAINT student_pickup_phone_format CHECK (phone ~ '^[0-9]{10,11}$')");
        DB::connection('tenant')->statement('ALTER TABLE student_pickup_authorizations ADD CONSTRAINT student_pickup_name_not_blank CHECK (length(btrim(full_name)) > 0)');
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('student_pickup_authorizations');
    }
};
