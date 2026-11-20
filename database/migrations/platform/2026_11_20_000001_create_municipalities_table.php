<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('platform')->create('municipalities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug', 80)->unique();
            $table->string('legal_name');
            $table->string('db_key', 80)->unique();
            $table->string('status', 24)->default('provisioning');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('platform')->dropIfExists('municipalities');
    }
};
