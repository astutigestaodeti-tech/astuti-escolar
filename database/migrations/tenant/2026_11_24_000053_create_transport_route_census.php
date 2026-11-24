<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Transporte escolar no Censo (registro 60, campos 23 a 33): poder público
 * responsável e tipos de veículo, informados uma vez por rota da escola.
 * Também a escolarização fora da escola (campo 21), por matrícula.
 * Sem dados pessoais na tabela de rotas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('transport_route_census', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->string('route_name', 60);
            $table->unsignedSmallInteger('public_power')->nullable();
            $table->jsonb('vehicles')->default('[]');
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();
            $table->unique(['school_id', 'route_name']);
        });

        Schema::connection('tenant')->table('student_enrollments', function (Blueprint $table): void {
            $table->unsignedSmallInteger('other_schooling_space')->nullable();
        });

        $db = DB::connection('tenant');
        $db->statement('ALTER TABLE transport_route_census ADD CONSTRAINT transport_route_census_power_chk CHECK (public_power IS NULL OR public_power IN (1, 2))');
        $db->statement("ALTER TABLE transport_route_census ADD CONSTRAINT transport_route_census_vehicles_chk CHECK (jsonb_typeof(vehicles) = 'array')");
        $db->statement('ALTER TABLE student_enrollments ADD CONSTRAINT student_enrollments_other_schooling_space_chk CHECK (other_schooling_space IS NULL OR other_schooling_space IN (1, 2, 3))');
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('student_enrollments', function (Blueprint $table): void {
            $table->dropColumn('other_schooling_space');
        });
        Schema::connection('tenant')->dropIfExists('transport_route_census');
    }
};
