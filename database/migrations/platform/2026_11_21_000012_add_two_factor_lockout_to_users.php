<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('platform')->table('users', function (Blueprint $table): void {
            // Contagem por conta, independente do IP: trocar de rede nao zera as tentativas.
            $table->unsignedSmallInteger('two_factor_failed_attempts')->default(0);
            $table->timestamp('two_factor_locked_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('platform')->table('users', function (Blueprint $table): void {
            $table->dropColumn(['two_factor_failed_attempts', 'two_factor_locked_until']);
        });
    }
};
