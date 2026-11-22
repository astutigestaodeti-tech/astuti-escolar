<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('students', function (Blueprint $table): void {
            $table->string('address_complement', 180)->nullable();
            $table->string('address_city', 120)->nullable();
            $table->string('address_state', 2)->nullable();
        });

        Schema::connection('tenant')->create('postal_code_cache', function (Blueprint $table): void {
            $table->string('postal_code', 9)->primary();
            $table->string('street', 180)->nullable();
            $table->string('district', 120)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state', 2)->nullable();
            $table->timestampTz('fetched_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('postal_code_cache');

        Schema::connection('tenant')->table('students', function (Blueprint $table): void {
            $table->dropColumn(['address_complement', 'address_city', 'address_state']);
        });
    }
};
