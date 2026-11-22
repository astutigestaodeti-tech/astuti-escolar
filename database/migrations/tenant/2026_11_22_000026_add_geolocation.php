<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Coordenadas da escola e da residência do aluno, para distância,
 * transporte e zoneamento. A do aluno é dado delicado: só a secretaria vê.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['schools', 'students'] as $tableName) {
            Schema::connection('tenant')->table($tableName, function (Blueprint $table): void {
                $table->decimal('latitude', 9, 6)->nullable();
                $table->decimal('longitude', 9, 6)->nullable();
                $table->string('geo_source', 12)->nullable();
                $table->timestampTz('geo_updated_at')->nullable();
            });
            DB::connection('tenant')->statement("ALTER TABLE {$tableName} ADD CONSTRAINT {$tableName}_geo_pair CHECK ((latitude IS NULL) = (longitude IS NULL))");
            DB::connection('tenant')->statement("ALTER TABLE {$tableName} ADD CONSTRAINT {$tableName}_geo_range CHECK (latitude IS NULL OR (latitude BETWEEN -34 AND 6 AND longitude BETWEEN -74 AND -28))");
            DB::connection('tenant')->statement("ALTER TABLE {$tableName} ADD CONSTRAINT {$tableName}_geo_source_check CHECK (geo_source IS NULL OR geo_source IN ('endereco', 'mapa'))");
        }
    }

    public function down(): void
    {
        foreach (['schools', 'students'] as $tableName) {
            Schema::connection('tenant')->table($tableName, function (Blueprint $table): void {
                $table->dropColumn(['latitude', 'longitude', 'geo_source', 'geo_updated_at']);
            });
        }
    }
};
