<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Identificação da escola no Educacenso (registro 00). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('school_census', function (Blueprint $table): void {
            $table->foreignUuid('school_id')->primary()->constrained('schools')->restrictOnDelete();
            $table->unsignedSmallInteger('operating_status')->nullable();
            $table->unsignedSmallInteger('location_zone')->nullable();
            $table->unsignedSmallInteger('differentiated_location')->nullable();
            $table->unsignedSmallInteger('administrative_dependency')->nullable();
            $table->unsignedSmallInteger('regulation')->nullable();
            $table->unsignedSmallInteger('regulation_sphere')->nullable();
            $table->unsignedSmallInteger('linked_unit')->nullable();
            $table->char('district_code', 2)->nullable();
            $table->char('phone_ddd', 2)->nullable();
            $table->string('phone', 9)->nullable();
            $table->string('phone_other', 9)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('regional_body_code', 5)->nullable();
            $table->char('host_school_code', 8)->nullable();
            $table->char('ies_code', 9)->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();
        });

        $db = DB::connection('tenant');
        $db->statement('ALTER TABLE school_census ADD CONSTRAINT school_census_operating_status_chk CHECK (operating_status IS NULL OR operating_status IN (1, 2, 3))');
        $db->statement('ALTER TABLE school_census ADD CONSTRAINT school_census_location_zone_chk CHECK (location_zone IS NULL OR location_zone IN (1, 2))');
        $db->statement('ALTER TABLE school_census ADD CONSTRAINT school_census_differentiated_location_chk CHECK (differentiated_location IS NULL OR differentiated_location IN (1, 2, 3, 7, 8))');
        $db->statement('ALTER TABLE school_census ADD CONSTRAINT school_census_administrative_dependency_chk CHECK (administrative_dependency IS NULL OR administrative_dependency IN (1, 2, 3, 4))');
        $db->statement('ALTER TABLE school_census ADD CONSTRAINT school_census_regulation_chk CHECK (regulation IS NULL OR regulation IN (0, 1, 2))');
        $db->statement('ALTER TABLE school_census ADD CONSTRAINT school_census_regulation_sphere_chk CHECK (regulation_sphere IS NULL OR regulation_sphere IN (1, 2, 3, 4, 6))');
        $db->statement('ALTER TABLE school_census ADD CONSTRAINT school_census_linked_unit_chk CHECK (linked_unit IS NULL OR linked_unit IN (0, 1, 2))');
        $db->statement("ALTER TABLE school_census ADD CONSTRAINT school_census_district_code_chk CHECK (district_code IS NULL OR district_code ~ '^[0-9]{2}$')");
        $db->statement("ALTER TABLE school_census ADD CONSTRAINT school_census_phone_ddd_chk CHECK (phone_ddd IS NULL OR phone_ddd ~ '^[0-9]{2}$')");
        $db->statement("ALTER TABLE school_census ADD CONSTRAINT school_census_phone_chk CHECK (phone IS NULL OR phone ~ '^[0-9]{8,9}$')");
        $db->statement("ALTER TABLE school_census ADD CONSTRAINT school_census_phone_other_chk CHECK (phone_other IS NULL OR phone_other ~ '^[0-9]{8,9}$')");
        $db->statement("ALTER TABLE school_census ADD CONSTRAINT school_census_regional_body_code_chk CHECK (regional_body_code IS NULL OR regional_body_code ~ '^[0-9]{1,5}$')");
        $db->statement("ALTER TABLE school_census ADD CONSTRAINT school_census_host_school_code_chk CHECK (host_school_code IS NULL OR host_school_code ~ '^[0-9]{8}$')");
        $db->statement("ALTER TABLE school_census ADD CONSTRAINT school_census_ies_code_chk CHECK (ies_code IS NULL OR ies_code ~ '^[0-9]{9}$')");
        $db->statement("ALTER TABLE school_census ADD CONSTRAINT school_census_email_chk CHECK (email IS NULL OR email ~ '^[A-Za-z0-9_.@]+$')");
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('school_census');
    }
};
