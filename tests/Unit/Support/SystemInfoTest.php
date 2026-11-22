<?php

namespace Tests\Unit\Support;

use App\Support\SystemInfo;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SystemInfoTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/astuti-versao-'.bin2hex(random_bytes(4));
        mkdir($this->dir.'/.git/refs/heads/teste', 0777, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    public function test_le_ramo_commit_e_horario_da_atualizacao(): void
    {
        file_put_contents($this->dir.'/.git/HEAD', "ref: refs/heads/teste/notebook\n");
        file_put_contents($this->dir.'/.git/refs/heads/teste/notebook', str_repeat('a1b2c3d4e5', 4)."\n");
        touch($this->dir.'/.git/HEAD', strtotime('2026-11-22 13:00:00 UTC'));
        touch($this->dir.'/.git/refs/heads/teste/notebook', strtotime('2026-11-22 17:35:00 UTC'));

        $versao = SystemInfo::version($this->dir);

        $this->assertSame('teste/notebook', $versao['branch']);
        $this->assertSame('a1b2c3d', $versao['commit']);
        // Horário de Brasília, o mais recente entre a troca de ramo e a atualização.
        $this->assertSame('22/11/2026 14:35', $versao['updated_at']->format('d/m/Y H:i'));
    }

    public function test_ramo_compactado_e_sem_git(): void
    {
        file_put_contents($this->dir.'/.git/HEAD', "ref: refs/heads/main\n");
        file_put_contents($this->dir.'/.git/packed-refs', "# pack-refs\n".str_repeat('f', 40)." refs/heads/main\n");

        $versao = SystemInfo::version($this->dir);
        $this->assertSame(['main', 'fffffff'], [$versao['branch'], $versao['commit']]);

        $this->assertSame(['branch' => null, 'commit' => null, 'updated_at' => null], SystemInfo::version($this->dir.'/inexistente'));
    }

    public function test_bancos_mostram_nome_e_endereco_sem_senha(): void
    {
        config()->set('database.connections.platform', ['driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => 5433, 'database' => 'astuti_platform', 'password' => 'segredo-ficticio']);
        config()->set('database.connections.tenant', ['driver' => 'astuti_tenant_disabled']);

        $this->assertSame(['Plataforma: astuti_platform em 127.0.0.1:5433'], SystemInfo::databases());
        $this->assertStringNotContainsString('segredo', implode(' ', SystemInfo::databases()));
    }
}
