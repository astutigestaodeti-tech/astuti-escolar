<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('platform')->create('school_scopes', function (Blueprint $table): void {
            $table->foreignUuid('membership_id')->constrained('memberships')->cascadeOnDelete();
            // UUID da base municipal: sem chave estrangeira entre bancos distintos.
            $table->uuid('school_id');
            $table->primary(['membership_id', 'school_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('platform')->dropIfExists('school_scopes');
    }
};
