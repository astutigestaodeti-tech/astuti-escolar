<?php

namespace App\Support;

/**
 * Limite real de envio de arquivo deste servidor (o menor entre
 * upload_max_filesize e post_max_size), para avisar o usuário com clareza.
 */
final class UploadLimit
{
    public static function bytes(): int
    {
        $limits = array_filter([
            self::iniBytes((string) ini_get('upload_max_filesize')),
            self::iniBytes((string) ini_get('post_max_size')),
        ], fn (int $bytes) => $bytes > 0);

        return $limits === [] ? PHP_INT_MAX : min($limits);
    }

    public static function label(?int $bytes = null): string
    {
        $bytes ??= self::bytes();
        if ($bytes >= 1048576) {
            $megabytes = rtrim(rtrim(number_format($bytes / 1048576, 1, ',', ''), '0'), ',');

            return $megabytes.' MB';
        }

        return max(1, (int) ceil($bytes / 1024)).' KB';
    }

    public static function iniBytes(string $value): int
    {
        $value = trim($value);
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1073741824,
            'm' => $number * 1048576,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
