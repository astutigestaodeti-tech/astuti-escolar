<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['schools', 'school_years', 'school_classes', 'students', 'student_enrollments'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            foreach (['deleted_at', 'deleted_by', 'deletion_reason'] as $column) {
                // MariaDB confirma ALTER TABLE fora de transações. Uma migração
                // interrompida deve poder retomar sem refazer colunas criadas.
                if (Schema::connection('tenant')->hasColumn($name, $column)) {
                    continue;
                }

                Schema::connection('tenant')->table($name, function (Blueprint $table) use ($column): void {
                    match ($column) {
                        'deleted_at' => $table->softDeletes(),
                        'deleted_by' => $table->uuid('deleted_by')->nullable(),
                        'deletion_reason' => $table->text('deletion_reason')->nullable(),
                    };
                });
            }
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $name) {
            foreach (['deletion_reason', 'deleted_by', 'deleted_at'] as $column) {
                if (! Schema::connection('tenant')->hasColumn($name, $column)) {
                    continue;
                }

                Schema::connection('tenant')->table($name, function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
