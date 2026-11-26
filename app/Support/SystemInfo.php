<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use JsonException;

/**
 * Lê somente a identificação da versão publicada pelo agente.
 * Nunca consulta o Git, executa comandos ou expõe configurações de banco.
 */
final class SystemInfo
{
    public const VERSION_PATH = '/var/lib/astuti-release-view/version.json';

    /** @return array{branch: null, commit: ?string, updated_at: ?Carbon} */
    public static function version(?string $manifestPath = null): array
    {
        $path = $manifestPath ?? self::VERSION_PATH;
        $empty = ['branch' => null, 'commit' => null, 'updated_at' => null];

        clearstatcache(true, $path);
        if (realpath($path) !== $path || ! is_file($path)) {
            return $empty;
        }

        $file = @fopen($path, 'rb');
        if ($file === false) {
            return $empty;
        }

        try {
            $raw = stream_get_contents($file, 4097);
        } finally {
            fclose($file);
        }

        if (! is_string($raw) || strlen($raw) > 4096) {
            return $empty;
        }

        try {
            $data = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $empty;
        }

        if (! is_array($data)
            || ($data['schema'] ?? null) !== 1
            || ! is_string($data['commit'] ?? null)
            || preg_match('/\A[0-9a-f]{40}\z/D', $data['commit']) !== 1) {
            return $empty;
        }

        $timestamp = $data['deployed_at'] ?? null;

        return [
            'branch' => null,
            'commit' => substr($data['commit'], 0, 7),
            'updated_at' => is_int($timestamp) && $timestamp > 0 && $timestamp <= now()->getTimestamp()
                ? Carbon::createFromTimestamp($timestamp)->setTimezone('America/Sao_Paulo')
                : null,
        ];
    }

    /**
     * Informações de conexão para uso técnico interno, nunca no rodapé.
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
}
