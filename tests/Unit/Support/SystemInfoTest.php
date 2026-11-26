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
        mkdir($this->dir, 0700, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    public function test_le_identificacao_publicada_pelo_agente(): void
    {
        $path = $this->dir.'/version.json';
        $this->travelTo(now()->setDate(2026, 11, 26)->setTime(20, 0));
        file_put_contents($path, json_encode([
            'schema' => 1,
            'commit' => str_repeat('a1b2c3d4e5', 4),
            'deployed_at' => strtotime('2026-11-26 17:35:00 UTC'),
            'secret' => 'nao-publicar',
        ], JSON_THROW_ON_ERROR));

        $versao = SystemInfo::version($path);

        $this->assertNull($versao['branch']);
        $this->assertSame('a1b2c3d', $versao['commit']);
        $this->assertSame('26/11/2026 14:35', $versao['updated_at']->format('d/m/Y H:i'));
        $this->assertArrayNotHasKey('secret', $versao);
    }

    public function test_ausencia_dados_invalidos_e_git_nao_identificam_versao(): void
    {
        $path = $this->dir.'/version.json';
        $empty = ['branch' => null, 'commit' => null, 'updated_at' => null];
        mkdir($this->dir.'/.git');
        file_put_contents($this->dir.'/.git/HEAD', str_repeat('f', 40));

        $this->assertSame($empty, SystemInfo::version($path));

        foreach (['{', 'null', str_repeat('a', 4097), '{"schema":1,"commit":"invalido"}', '{"schema":2}'] as $raw) {
            file_put_contents($path, $raw);
            $this->assertSame($empty, SystemInfo::version($path));
        }
    }

    public function test_horario_desconhecido_nao_e_inventado(): void
    {
        $path = $this->dir.'/version.json';
        file_put_contents($path, json_encode(['schema' => 1, 'commit' => str_repeat('f', 40), 'deployed_at' => null], JSON_THROW_ON_ERROR));
        $this->assertSame('fffffff', SystemInfo::version($path)['commit']);
        $this->assertNull(SystemInfo::version($path)['updated_at']);
    }

    public function test_bancos_mostram_nome_e_endereco_sem_senha(): void
    {
        config()->set('database.connections.platform', ['driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => 5433, 'database' => 'astuti_platform', 'password' => 'segredo-ficticio']);
        config()->set('database.connections.tenant', ['driver' => 'astuti_tenant_disabled']);

        $this->assertSame(['Plataforma: astuti_platform em 127.0.0.1:5433'], SystemInfo::databases());
        $this->assertStringNotContainsString('segredo', implode(' ', SystemInfo::databases()));
    }
}
