<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('guardians', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('full_name', 180);
            $table->string('cpf', 14)->nullable()->unique();
            $table->string('phone', 11);
            $table->string('email', 255)->nullable();
            $table->timestampsTz();
        });

        DB::connection('tenant')->statement("ALTER TABLE guardians ADD CONSTRAINT guardians_cpf_format CHECK (cpf IS NULL OR cpf ~ '^[0-9]{3}\\.[0-9]{3}\\.[0-9]{3}-[0-9]{2}$')");
        DB::connection('tenant')->statement("ALTER TABLE guardians ADD CONSTRAINT guardians_phone_format CHECK (phone ~ '^[0-9]{10,11}$')");

        Schema::connection('tenant')->create('student_guardians', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignUuid('guardian_id')->constrained('guardians')->restrictOnDelete();
            $table->string('relationship', 20);
            $table->boolean('is_legal_guardian')->default(false);
            $table->boolean('is_primary_contact')->default(false);
            $table->timestampsTz();
            $table->unique(['student_id', 'guardian_id'], 'student_guardians_student_guardian_unique');
        });

        DB::connection('tenant')->statement("ALTER TABLE student_guardians ADD CONSTRAINT student_guardians_relationship_check CHECK (relationship IN ('mother', 'father', 'grandmother', 'grandfather', 'uncle', 'aunt', 'brother', 'sister', 'other'))");
        DB::connection('tenant')->statement('CREATE UNIQUE INDEX student_guardians_one_primary ON student_guardians (student_id) WHERE is_primary_contact = true');
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('student_guardians');
        Schema::connection('tenant')->dropIfExists('guardians');
    }
};
