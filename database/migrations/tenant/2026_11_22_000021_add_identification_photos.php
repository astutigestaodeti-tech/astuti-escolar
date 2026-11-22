<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foto de identificação (uso interno, conferência na entrada e na saída).
 * Guarda só o caminho no disco privado e quando a foto foi atualizada.
 */
return new class extends Migration
{
    private const TABLES = ['students', 'guardians', 'student_pickup_authorizations'];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::connection('tenant')->table($tableName, function (Blueprint $table): void {
                $table->string('photo_path', 200)->nullable();
                $table->timestampTz('photo_updated_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::connection('tenant')->table($tableName, function (Blueprint $table): void {
                $table->dropColumn(['photo_path', 'photo_updated_at']);
            });
        }
    }
};
