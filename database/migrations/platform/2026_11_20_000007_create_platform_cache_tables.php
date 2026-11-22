<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Instalacoes antigas podiam ter criado estas tabelas pela migration
        // raiz. Preservar o conteudo e concluir atualizacoes parciais.
        if (! Schema::connection('platform')->hasTable('cache')) {
            Schema::connection('platform')->create('cache', function (Blueprint $table): void {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->bigInteger('expiration')->index();
            });
        }

        if (! Schema::connection('platform')->hasTable('cache_locks')) {
            Schema::connection('platform')->create('cache_locks', function (Blueprint $table): void {
                $table->string('key')->primary();
                $table->string('owner');
                $table->bigInteger('expiration')->index();
            });
        }
    }

    public function down(): void
    {
        // A migration nao pode distinguir tabelas legadas de tabelas novas.
        // Um rollback nunca deve destruir cache preexistente sem autorizacao.
    }
};
