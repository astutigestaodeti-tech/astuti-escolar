<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('tenant');

        $db->statement('CREATE EXTENSION IF NOT EXISTS unaccent');
        $db->statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        // O dicionario unaccent padrao permanece fixo nesta instalacao.
        // Se suas regras mudarem, sera necessario recriar o indice GIN.
        $db->statement(<<<'SQL'
            CREATE FUNCTION public.f_unaccent(value text)
            RETURNS text
            LANGUAGE sql
            IMMUTABLE
            PARALLEL SAFE
            AS $$
                SELECT public.unaccent('public.unaccent'::regdictionary, value)
            $$
            SQL);

        $db->statement('CREATE INDEX students_full_name_unaccent_trgm_idx ON public.students USING GIN (public.f_unaccent(lower(full_name)) gin_trgm_ops)');
    }

    public function down(): void
    {
        $db = DB::connection('tenant');
        $db->statement('DROP INDEX IF EXISTS public.students_full_name_unaccent_trgm_idx');
        $db->statement('DROP FUNCTION IF EXISTS public.f_unaccent(text)');

        // Extensoes sao compartilhadas pelo banco e nao devem ser removidas.
    }
};
