<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('students', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('full_name', 180);
            $table->date('birth_date')->nullable();
            $table->timestamps();

            $table->index('full_name');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('students');
    }
};
