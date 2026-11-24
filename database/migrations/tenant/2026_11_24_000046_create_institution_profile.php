<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dados institucionais da Prefeitura e da Secretaria (linha única), usados no timbre
 * e no rodapé de todos os documentos emitidos. São dados públicos do órgão.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('institution_profile', function (Blueprint $table): void {
            $table->unsignedSmallInteger('id')->primary();
            $table->string('department_name', 120)->default('Secretaria Municipal de Educação');
            $table->char('cnpj', 14)->nullable();
            $table->string('phone', 11)->nullable();
            $table->string('email', 160)->nullable();
            $table->string('website', 160)->nullable();
            $table->string('postal_code', 9)->nullable();
            $table->string('address_street', 180)->nullable();
            $table->string('address_number', 20)->nullable();
            $table->string('address_complement', 120)->nullable();
            $table->string('address_district', 120)->nullable();
            $table->string('address_city', 120)->nullable();
            $table->char('address_state', 2)->nullable();
            $table->string('secretary_name', 160)->nullable();
            $table->string('secretary_title', 120)->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();
        });
        $db = DB::connection('tenant');
        $db->statement('ALTER TABLE institution_profile ADD CONSTRAINT institution_profile_single_chk CHECK (id = 1)');
        $db->statement("ALTER TABLE institution_profile ADD CONSTRAINT institution_profile_cnpj_chk CHECK (cnpj IS NULL OR cnpj ~ '^[0-9]{14}$')");
        $db->statement("ALTER TABLE institution_profile ADD CONSTRAINT institution_profile_phone_chk CHECK (phone IS NULL OR phone ~ '^[0-9]{10,11}$')");
        $db->statement("ALTER TABLE institution_profile ADD CONSTRAINT institution_profile_state_chk CHECK (address_state IS NULL OR address_state ~ '^[A-Z]{2}$')");
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('institution_profile');
    }
};
