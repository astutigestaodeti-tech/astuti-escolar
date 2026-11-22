<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contatos extras do responsável: outro telefone (fixo ou recado) e o
 * trabalho só com nome e telefone, para emergências. Dados da empresa
 * (CNPJ, endereço, e-mail) ficam de fora pela necessidade da LGPD.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('guardians', function (Blueprint $table): void {
            $table->string('phone_secondary', 11)->nullable();
            $table->string('workplace', 120)->nullable();
            $table->string('work_phone', 11)->nullable();
        });

        DB::connection('tenant')->statement("ALTER TABLE guardians ADD CONSTRAINT guardians_phone_secondary_format CHECK (phone_secondary IS NULL OR phone_secondary ~ '^[0-9]{10,11}$')");
        DB::connection('tenant')->statement("ALTER TABLE guardians ADD CONSTRAINT guardians_work_phone_format CHECK (work_phone IS NULL OR work_phone ~ '^[0-9]{10,11}$')");
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('guardians', function (Blueprint $table): void {
            $table->dropColumn(['phone_secondary', 'workplace', 'work_phone']);
        });
    }
};
