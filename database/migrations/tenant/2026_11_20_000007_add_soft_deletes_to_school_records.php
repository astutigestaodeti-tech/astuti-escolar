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
            Schema::connection('tenant')->table($name, function (Blueprint $table): void {
                $table->softDeletes();
                $table->uuid('deleted_by')->nullable();
                $table->text('deletion_reason')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $name) {
            Schema::connection('tenant')->table($name, function (Blueprint $table): void {
                $table->dropSoftDeletes();
                $table->dropColumn(['deleted_by', 'deletion_reason']);
            });
        }
    }
};
