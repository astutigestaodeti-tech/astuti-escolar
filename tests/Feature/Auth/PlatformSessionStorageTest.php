<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\TwoMunicipalityTestCase;

class PlatformSessionStorageTest extends TwoMunicipalityTestCase
{
    public function test_sessoes_usam_banco_central_e_preservam_id_uuid(): void
    {
        $this->assertSame('platform', config('session.connection'));
        $this->assertTrue(Schema::connection('platform')->hasTable('sessions'));

        $user = $this->criarUsuario('sessao@example.invalid');

        DB::connection('platform')->table('sessions')->insert([
            'id' => 'sessao-sintetica-astuti',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'payload' => 'teste',
            'last_activity' => time(),
        ]);

        $this->assertSame(
            $user->id,
            DB::connection('platform')->table('sessions')->where('id', 'sessao-sintetica-astuti')->value('user_id'),
        );
    }

    public function test_migracoes_padrao_nao_duplicam_tabelas_de_autenticacao_central(): void
    {
        $this->assertSame(0, Artisan::call('migrate', [
            '--database' => 'platform',
            '--path' => database_path('migrations'),
            '--realpath' => true,
            '--force' => true,
        ]));
    }

    public function test_uma_sessao_criada_em_outro_teste_nao_permanece_na_base(): void
    {
        $this->assertSame(
            0,
            DB::connection('platform')->table('sessions')
                ->where('id', 'sessao-sintetica-astuti')->count(),
        );
    }
}
