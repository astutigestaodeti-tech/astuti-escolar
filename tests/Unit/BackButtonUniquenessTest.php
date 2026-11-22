<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * O botao "Voltar a tela anterior" vem do layout municipal (componente
 * voltar-anterior). Uma tela que desenha o proprio botao fica com dois.
 */
final class BackButtonUniquenessTest extends TestCase
{
    private const VIEWS = __DIR__.'/../../resources/views';

    /** Telas fora do layout municipal, que usam o componente uma vez. */
    private const OWN_LAYOUT = ['auth/two-factor-settings.blade.php', 'layouts/municipal.blade.php'];

    public function test_so_o_layout_desenha_o_botao_voltar(): void
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::VIEWS));
        $offenders = [];

        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen(self::VIEWS))), '/');
            if ($relative === 'components/voltar-anterior.blade.php') {
                continue;
            }

            $content = (string) file_get_contents($file->getPathname());
            $text = substr_count($content, 'Voltar à tela anterior');
            $component = substr_count($content, '<x-voltar-anterior');

            if ($text > 0 || ($component > 0 && ! in_array($relative, self::OWN_LAYOUT, true)) || $component > 1) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame([], $offenders, 'Estas telas desenham outro botao "Voltar à tela anterior"; use o do layout.');
    }
}
