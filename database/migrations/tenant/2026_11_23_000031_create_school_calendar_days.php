<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('school_calendar_days', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('school_year_id');
            $table->uuid('school_id')->nullable();
            $table->date('date');
            $table->string('kind', 24);
            $table->string('description', 120);
            $table->timestampsTz();

            $table->foreign('school_year_id')->references('id')->on('school_years')->restrictOnDelete();
            $table->foreign('school_id')->references('id')->on('schools')->restrictOnDelete();
            $table->index(['school_year_id', 'date'], 'school_calendar_year_date_idx');
        });

        DB::connection('tenant')->statement("ALTER TABLE school_calendar_days ADD CONSTRAINT school_calendar_kind_chk CHECK (kind IN ('holiday', 'recess', 'non_school_day', 'extra_school_day', 'event'))");
        DB::connection('tenant')->statement('CREATE UNIQUE INDEX school_calendar_network_unique ON school_calendar_days (school_year_id, date) WHERE school_id IS NULL');
        DB::connection('tenant')->statement('CREATE UNIQUE INDEX school_calendar_school_unique ON school_calendar_days (school_year_id, school_id, date) WHERE school_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('school_calendar_days');
    }
};
