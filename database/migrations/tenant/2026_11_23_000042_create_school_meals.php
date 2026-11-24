<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('school_meal_menus', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_year_id')->constrained('school_years')->restrictOnDelete();
            $table->date('week_start');
            $table->unsignedSmallInteger('weekday');
            $table->string('stage', 20);
            $table->string('meal_type', 20);
            $table->string('description', 500);
            $table->uuid('updated_by');
            $table->timestampsTz();
            $table->unique(['school_year_id', 'week_start', 'weekday', 'stage', 'meal_type'], 'school_meal_menus_slot_unique');
        });

        Schema::connection('tenant')->create('school_meal_servings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignUuid('school_year_id')->constrained('school_years')->restrictOnDelete();
            $table->date('meal_date');
            $table->string('stage', 20);
            $table->string('meal_type', 20);
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('suggested_present')->nullable();
            $table->uuid('confirmed_by');
            $table->timestampTz('confirmed_at');
            $table->timestampsTz();
            $table->unique(['school_id', 'school_year_id', 'meal_date', 'stage', 'meal_type'], 'school_meal_servings_slot_unique');
            $table->index(['school_year_id', 'meal_date']);
        });

        $db = DB::connection('tenant');
        foreach (['school_meal_menus', 'school_meal_servings'] as $table) {
            $db->statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_stage_check CHECK (stage IN ('creche', 'pre_escola', 'fundamental'))");
            $db->statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_meal_check CHECK (meal_type IN ('cafe', 'almoco', 'lanche', 'jantar'))");
        }
        $db->statement('ALTER TABLE school_meal_menus ADD CONSTRAINT school_meal_menus_weekday_check CHECK (weekday BETWEEN 1 AND 7)');
        $db->statement("ALTER TABLE school_meal_menus ADD CONSTRAINT school_meal_menus_week_monday_check CHECK (EXTRACT(ISODOW FROM week_start) = 1)");
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('school_meal_servings');
        Schema::connection('tenant')->dropIfExists('school_meal_menus');
    }
};
