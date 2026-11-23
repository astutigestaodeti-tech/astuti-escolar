<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('school_classes', function (Blueprint $table): void {
            $table->decimal('daily_hours', 3, 1)->nullable();
        });

        Schema::connection('tenant')->create('integral_pact_targets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_year_id')->constrained('school_years')->restrictOnDelete();
            $table->string('stage', 32);
            $table->unsignedInteger('target_enrollments');
            $table->uuid('updated_by');
            $table->timestampsTz();
            $table->unique(['school_year_id', 'stage'], 'integral_pact_year_stage_unique');
        });

        Schema::connection('tenant')->create('integral_fundeb_finances', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_year_id')->unique()->constrained('school_years')->restrictOnDelete();
            $table->unsignedBigInteger('revenue_cents');
            $table->uuid('updated_by');
            $table->timestampsTz();
        });

        Schema::connection('tenant')->create('integral_fundeb_expenses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_year_id')->constrained('school_years')->restrictOnDelete();
            $table->string('description', 240);
            $table->unsignedBigInteger('amount_cents');
            $table->string('evidence_path', 255)->nullable();
            $table->string('evidence_name', 180)->nullable();
            $table->string('evidence_mime', 80)->nullable();
            $table->timestampTz('evidence_uploaded_at')->nullable();
            $table->uuid('created_by');
            $table->timestampsTz();
            $table->index('school_year_id');
        });

        $db = DB::connection('tenant');
        $db->statement('ALTER TABLE school_classes ADD CONSTRAINT school_classes_daily_hours_check CHECK (daily_hours IS NULL OR (daily_hours >= 0.5 AND daily_hours <= 16))');
        $db->statement("ALTER TABLE integral_pact_targets ADD CONSTRAINT integral_pact_stage_check CHECK (stage IN ('creche', 'pre_escola', 'fundamental_iniciais', 'fundamental_finais'))");
        $db->statement('ALTER TABLE integral_fundeb_expenses ADD CONSTRAINT integral_fundeb_expense_positive_check CHECK (amount_cents > 0)');
        $db->statement('ALTER TABLE integral_fundeb_expenses ADD CONSTRAINT integral_fundeb_expense_evidence_check CHECK ((evidence_path IS NULL AND evidence_name IS NULL AND evidence_mime IS NULL AND evidence_uploaded_at IS NULL) OR (evidence_path IS NOT NULL AND evidence_name IS NOT NULL AND evidence_mime IS NOT NULL AND evidence_uploaded_at IS NOT NULL))');
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('integral_fundeb_expenses');
        Schema::connection('tenant')->dropIfExists('integral_fundeb_finances');
        Schema::connection('tenant')->dropIfExists('integral_pact_targets');
        DB::connection('tenant')->statement('ALTER TABLE school_classes DROP CONSTRAINT IF EXISTS school_classes_daily_hours_check');
        Schema::connection('tenant')->table('school_classes', function (Blueprint $table): void {
            $table->dropColumn('daily_hours');
        });
    }
};
