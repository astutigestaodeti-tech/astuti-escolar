<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('students', function (Blueprint $table): void {
            $table->string('address_street', 180)->nullable();
            $table->string('address_number', 20)->nullable();
            $table->string('address_district', 120)->nullable();
            $table->string('postal_code', 9)->nullable();
            $table->string('residence_zone', 5)->nullable();
        });

        DB::connection('tenant')->statement(
            "ALTER TABLE students ADD CONSTRAINT students_residence_zone_check CHECK (residence_zone IN ('urban', 'rural'))"
        );
    }

    public function down(): void
    {
        DB::connection('tenant')->statement('ALTER TABLE students DROP CONSTRAINT IF EXISTS students_residence_zone_check');

        Schema::connection('tenant')->table('students', function (Blueprint $table): void {
            $table->dropColumn(['address_street', 'address_number', 'address_district', 'postal_code', 'residence_zone']);
        });
    }
};
