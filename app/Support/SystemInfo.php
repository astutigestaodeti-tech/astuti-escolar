<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Versão em execução e banco em uso, para o rodapé. Lê o Git direto dos
 * arquivos (sem executar comandos) e nunca falha: sem Git, diz que não sabe.
 */
final class SystemInfo
{
    /** @return array{branch: ?string, commit: ?string, updated_at: ?Carbon} */
    public static function version(?string $basePath = null): array
    {
        $git = self::gitDir($basePath ?? base_path());
        $empty = ['branch' => null, 'commit' => null, 'updated_at' => null];
        if ($git === null || ! is_readable($git.'/HEAD')) {
            return $empty;
        }

        $head = trim((string) file_get_contents($git.'/HEAD'));
        $times = [filemtime($git.'/HEAD') ?: 0];
        $branch = null;
        $commit = null;

        if (str_starts_with($head, 'ref: ')) {
            $ref = trim(substr($head, 5));
            $branch = str_starts_with($ref, 'refs/heads/') ? substr($ref, 11) : $ref;
            if (is_readable($git.'/'.$ref)) {
                $commit = trim((string) file_get_contents($git.'/'.$ref));
                $times[] = filemtime($git.'/'.$ref) ?: 0;
            } elseif (is_readable($git.'/packed-refs')) {
                foreach (file($git.'/packed-refs', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
                    if (str_ends_with($line, ' '.$ref)) {
                        $commit = substr($line, 0, 40);
                        $times[] = filemtime($git.'/packed-refs') ?: 0;
                    }
                }
            }
        } elseif (preg_match('/\A[0-9a-f]{40}\z/', $head) === 1) {
            $commit = $head;
        }

        return [
            'branch' => $branch,
            'commit' => $commit !== null && preg_match('/\A[0-9a-f]{7,64}\z/', $commit) === 1 ? substr($commit, 0, 7) : null,
            'updated_at' => Carbon::createFromTimestamp(max($times))->setTimezone('America/Sao_Paulo'),
        ];
    }

    /**
     * Bancos em uso nesta tela: o da plataforma e, dentro de uma prefeitura,
     * o da prefeitura. Só para ambiente de desenvolvimento.
     *
     * @return list<string>
     */
    public static function databases(): array
    {
        $lines = [];
        foreach (['platform' => 'Plataforma', 'tenant' => 'Prefeitura'] as $connection => $label) {
            $settings = config('database.connections.'.$connection, []);
            if (! is_array($settings) || ! is_string($settings['database'] ?? null) || str_contains((string) ($settings['driver'] ?? ''), 'disabled')) {
                continue;
            }
            $where = is_string($settings['host'] ?? null) ? $settings['host'].(isset($settings['port']) ? ':'.$settings['port'] : '') : null;
            $lines[] = $label.': '.$settings['database'].($where !== null ? ' em '.$where : '');
        }

        return $lines;
    }

    private static function gitDir(string $basePath): ?string
    {
        $git = $basePath.'/.git';
        if (is_dir($git)) {
            return $git;
        }
        // Worktree: ".git" é um arquivo com "gitdir: caminho".
        if (is_file($git) && preg_match('/\Agitdir:\s*(.+)\z/', trim((string) file_get_contents($git)), $m) === 1) {
            $dir = str_starts_with($m[1], '/') ? $m[1] : $basePath.'/'.$m[1];

            return is_dir($dir) ? $dir : null;
        }

        return null;
    }
}
