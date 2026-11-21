<?php

namespace App\Support\DatabaseMigration;

use InvalidArgumentException;

final class MigrationVerifier
{
    // Ordem topologica: toda chave estrangeira encontra seu pai antes de inserir.
    public const PLATFORM_TABLES = [
        'municipalities',
        'users',
        'municipality_provision_attempts',
        'memberships',
        'school_scopes',
        'platform_audit_logs',
    ];

    public const TENANT_TABLES = [
        'schools',
        'school_years',
        'students',
        'school_classes',
        'student_enrollments',
        'audit_logs',
    ];

    public static function safeContext(string $environment, array $platform, array $tenant, array $source): bool
    {
        return $environment === 'local'
            && self::isLocalPostgres($platform, 'astuti_platform_dev')
            && self::isLocalPostgres($tenant, 'astuti_monte_sereno_dev')
            && ($source['driver'] ?? null) === 'mysql'
            && self::isLoopback($source['host'] ?? null)
            && ($source['database'] ?? null) === 'astuti_platform_dev'
            && is_string($source['username'] ?? null) && trim($source['username']) !== '';
    }

    public static function assertSameIdentity(array $sourceIds, array $targetIds, string $table): void
    {
        $from = array_map('strval', $sourceIds);
        $to = array_map('strval', $targetIds);
        sort($from, SORT_STRING);
        sort($to, SORT_STRING);

        if ($from !== $to) {
            throw new InvalidArgumentException("Integridade da tabela {$table} nao confirmada.");
        }
    }

    public static function preservePasswordHash(string $original, string $copied): string
    {
        if ($original !== $copied) {
            throw new InvalidArgumentException('O hash de uma senha foi alterado durante a copia.');
        }

        return $copied;
    }

    private static function isLocalPostgres(array $settings, string $name): bool
    {
        return ($settings['driver'] ?? null) === 'pgsql'
            && empty($settings['url'])
            && self::isLoopback($settings['host'] ?? null)
            && ($settings['database'] ?? null) === $name
            && is_numeric($settings['port'] ?? null);
    }

    private static function isLoopback(mixed $host): bool
    {
        return is_string($host)
            && in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true);
    }
}
