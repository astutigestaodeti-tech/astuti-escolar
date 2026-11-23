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
}
