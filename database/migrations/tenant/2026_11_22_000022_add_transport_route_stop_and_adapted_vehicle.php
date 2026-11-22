<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('student_enrollments', function (Blueprint $table): void {
            $table->string('transport_route', 60)->nullable();
            $table->string('transport_stop', 160)->nullable();
            $table->boolean('transport_adapted_vehicle')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('student_enrollments', function (Blueprint $table): void {
            $table->dropColumn(['transport_route', 'transport_stop', 'transport_adapted_vehicle']);
        });
    }
};
