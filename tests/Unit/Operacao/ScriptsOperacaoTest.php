<?php

namespace Tests\Unit\Operacao;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Os scripts de operação rodam em servidores; um erro de sintaxe ou um
 * comando destrutivo esquecido custa caro. Estes testes não executam nada.
 */
class ScriptsOperacaoTest extends TestCase
{
    public static function scripts(): array
    {
        $root = dirname(__DIR__, 3).'/scripts/operacao';
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'sh') {
                $files[substr($file->getPathname(), strlen($root) + 1)] = [$file->getPathname()];
            }
        }
        ksort($files);

        return $files;
    }

    #[DataProvider('scripts')]
    public function test_script_tem_sintaxe_valida_de_bash(string $path): void
    {
        exec('bash -n '.escapeshellarg($path).' 2>&1', $output, $code);
        $this->assertSame(0, $code, implode("\n", $output));

        exec('command -v shellcheck', $found, $hasShellcheck);
        if ($hasShellcheck === 0) {
            exec('shellcheck -S error '.escapeshellarg($path).' 2>&1', $lint, $lintCode);
            $this->assertSame(0, $lintCode, implode("\n", $lint));
        }
    }

    public function test_atualizacao_da_vps_de_desenvolvimento_so_roda_em_local_e_nao_apaga_dados(): void
    {
        $script = file_get_contents(dirname(__DIR__, 3).'/scripts/operacao/vps/atualizar-desenvolvimento.sh');

        $this->assertStringContainsString('[[ "$ambiente" == local ]]', $script);
        $this->assertStringContainsString('git merge --ff-only origin/main', $script);
        $this->assertStringContainsString('--untracked-files=no', $script);
        foreach (['migrate:fresh', 'db:wipe', 'migrate:reset', 'DROP ', 'reset --hard', 'push', 'rm -rf'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $script, $forbidden);
        }
    }

    public function test_verificacao_do_servidor_so_le_e_nao_mostra_segredos(): void
    {
        $script = dirname(__DIR__, 3).'/scripts/operacao/vps/verificar-ambiente.sh';
        $projeto = sys_get_temp_dir().'/astuti-verificar-'.bin2hex(random_bytes(4));
        mkdir($projeto);
        file_put_contents($projeto.'/artisan', "<?php\n");
        file_put_contents($projeto.'/.env', implode("\n", [
            'APP_ENV=production',
            'APP_DEBUG=true',
            'APP_KEY=base64:chave-ficticia-que-nao-pode-aparecer',
            'APP_URL=https://escola.exemplo.invalid',
            'SESSION_SECURE_COOKIE=false',
            'DB_PASSWORD=senha-ficticia-que-nao-pode-aparecer',
            '',
        ]));
        chmod($projeto.'/.env', 0644);
        $antes = md5_file($projeto.'/.env').decoct(fileperms($projeto.'/.env'));

        try {
            exec('ASTUTI_VERIFICAR_SEM_SISTEMA=1 ASTUTI_VERIFICAR_SEM_REDE=1 bash '.escapeshellarg($script)
                .' --detalhes --projeto='.escapeshellarg($projeto).' 2>&1', $linhas, $codigo);
            $saida = implode("\n", $linhas);

            $this->assertSame(1, $codigo, $saida);
            $this->assertStringContainsString('FALHA    .env legivel por qualquer usuario', $saida);
            $this->assertStringContainsString('FALHA    APP_DEBUG=true em producao', $saida);
            $this->assertStringContainsString('FALHA    SESSION_SECURE_COOKIE nao esta true', $saida);
            $this->assertStringContainsString('OK       APP_KEY definida', $saida);
            foreach (['chave-ficticia', 'senha-ficticia', 'exemplo.invalid', 'DB_PASSWORD'] as $segredo) {
                $this->assertStringNotContainsString($segredo, $saida, $segredo);
            }
            clearstatcache();
            $this->assertSame($antes, md5_file($projeto.'/.env').decoct(fileperms($projeto.'/.env')));
            $this->assertSame(['.env', 'artisan'], array_values(array_diff(scandir($projeto), ['.', '..'])));
        } finally {
            @unlink($projeto.'/.env');
            @unlink($projeto.'/artisan');
            @rmdir($projeto);
        }

        $fonte = file_get_contents($script);
        foreach (['migrate', 'DROP ', 'rm -', 'sed -i', 'tee ', 'ufw allow', 'ufw enable', 'ufw delete', 'systemctl restart', 'systemctl reload', 'cat "$env"', 'cat .env'] as $proibido) {
            $this->assertStringNotContainsString($proibido, $fonte, $proibido);
        }
    }
}
