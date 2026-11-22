<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('platform')->create('platform_audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->timestamp('occurred_at', 6);
            $table->uuid('user_id')->nullable();
            $table->string('role', 255)->nullable();
            $table->string('action', 80);
            $table->string('auditable_type', 180)->nullable();
            $table->string('auditable_id', 36)->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->ipAddress('ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->uuid('request_id');

            $table->index(['auditable_type', 'auditable_id', 'occurred_at'], 'platform_audit_entity_idx');
            $table->index(['user_id', 'occurred_at'], 'platform_audit_user_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('platform')->dropIfExists('platform_audit_logs');
    }
};
