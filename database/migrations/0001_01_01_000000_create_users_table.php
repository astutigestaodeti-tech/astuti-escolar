<?php

use Illuminate\Database\Migrations\Migration;

// Tabelas de login, recuperacao e sessoes agora pertencem exclusivamente
// a database/migrations/platform. Preservar o nome desta migration antiga
// evita executar DROP de dados centrais num rollback do esquema padrao.
return new class extends Migration
{
    public function up(): void
    {
        // Nao criar tabelas de autenticacao no banco padrao.
    }

    public function down(): void
    {
        // Nunca apagar os dados da plataforma aqui.
    }
};
