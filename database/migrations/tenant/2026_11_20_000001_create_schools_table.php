<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('schools', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('inep_code', 12)->nullable()->unique();
            $table->string('status', 24)->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('schools');
    }
};
