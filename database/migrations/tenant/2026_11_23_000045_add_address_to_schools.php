<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Endereço da escola: usado no mapa, nos documentos e no Censo (Educacenso). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('schools', function (Blueprint $table): void {
            $table->string('postal_code', 9)->nullable();
            $table->string('address_street', 180)->nullable();
            $table->string('address_number', 20)->nullable();
            $table->string('address_complement', 180)->nullable();
            $table->string('address_district', 120)->nullable();
            $table->string('address_city', 120)->nullable();
            $table->char('address_state', 2)->nullable();
        });
        DB::connection('tenant')->statement("ALTER TABLE schools ADD CONSTRAINT schools_address_state_check CHECK (address_state IS NULL OR address_state ~ '^[A-Z]{2}$')");
    }

    public function down(): void
    {
        DB::connection('tenant')->statement('ALTER TABLE schools DROP CONSTRAINT IF EXISTS schools_address_state_check');
        Schema::connection('tenant')->table('schools', function (Blueprint $table): void {
            $table->dropColumn(['postal_code', 'address_street', 'address_number', 'address_complement', 'address_district', 'address_city', 'address_state']);
        });
    }
};
