<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('platform')->create('memberships', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('municipality_id')->constrained('municipalities')->cascadeOnDelete();
            $table->string('role', 32);
            $table->string('status', 24)->default('active');
            $table->timestamps();

            $table->unique(['user_id', 'municipality_id', 'role']);
            $table->index(['municipality_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::connection('platform')->dropIfExists('memberships');
    }
};
