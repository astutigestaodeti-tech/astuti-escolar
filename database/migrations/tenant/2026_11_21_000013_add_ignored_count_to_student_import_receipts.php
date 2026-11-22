<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('student_import_receipts', function (Blueprint $table): void {
            $table->unsignedInteger('ignored_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('student_import_receipts', function (Blueprint $table): void {
            $table->dropColumn('ignored_count');
        });
    }
};
