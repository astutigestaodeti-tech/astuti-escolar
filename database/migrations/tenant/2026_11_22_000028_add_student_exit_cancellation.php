<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Saída registrada por engano: o registro não é apagado, fica marcado como
 * desfeito, com quem desfez, quando e o motivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('student_exits', function (Blueprint $table): void {
            $table->timestampTz('cancelled_at')->nullable();
            $table->uuid('cancelled_by')->nullable();
            $table->string('cancel_reason', 300)->nullable();
        });

        DB::connection('tenant')->statement('ALTER TABLE student_exits ADD CONSTRAINT student_exits_cancel_check CHECK ((cancelled_at IS NULL) = (cancel_reason IS NULL))');
    }

    public function down(): void
    {
        DB::connection('tenant')->statement('ALTER TABLE student_exits DROP CONSTRAINT IF EXISTS student_exits_cancel_check');
        Schema::connection('tenant')->table('student_exits', function (Blueprint $table): void {
            $table->dropColumn(['cancelled_at', 'cancelled_by', 'cancel_reason']);
        });
    }
};
