<?php

use Illuminate\Database\Migrations\Migration;

// O cache compartilhado de login pertence exclusivamente ao banco platform.
// A migration original fica sem operacoes para evitar uma segunda criacao
// quando se executa migrate no diretorio padrao.
return new class extends Migration
{
    public function up(): void
    {
        // Nao criar cache fora da plataforma.
    }

    public function down(): void
    {
        // Nunca apagar o cache central por um rollback do banco padrao.
    }
};
