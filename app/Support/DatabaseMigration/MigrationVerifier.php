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

    public static function assertSameRows(array $sourceRows, array $targetRows, string $table): void
    {
        if (self::canonicalRows($sourceRows) !== self::canonicalRows($targetRows)) {
            throw new RowContentMismatchException($table, self::differingColumns($sourceRows, $targetRows));
        }
    }

    /** @return list<string> */
    private static function differingColumns(array $sourceRows, array $targetRows): array
    {
        $indexByIdentity = static function (array $rows): array {
            $indexed = [];

            foreach ($rows as $position => $row) {
                $values = (array) $row;
                $identity = isset($values['id']) ? 'id:'.$values['id']
                    : (isset($values['membership_id'], $values['school_id'])
                        ? 'scope:'.$values['membership_id'].'|'.$values['school_id']
                        : 'position:'.$position);

                $indexed[$identity] = $values;
            }

            return $indexed;
        };

        $source = $indexByIdentity($sourceRows);
        $target = $indexByIdentity($targetRows);
        $different = [];

        foreach (array_unique([...array_keys($source), ...array_keys($target)]) as $identity) {
            if (! array_key_exists($identity, $source) || ! array_key_exists($identity, $target)) {
                // Registros ausentes/novos sao falhas de identidade, nao divergencia
                // de todos os demais atributos que estavam naquele registro.
                $row = $source[$identity] ?? $target[$identity];

                if (array_key_exists('id', $row)) {
                    $different['id'] = true;
                } elseif (isset($row['membership_id'], $row['school_id'])) {
                    $different['membership_id'] = true;
                    $different['school_id'] = true;
                }

                continue;
            }

            $from = $source[$identity];
            $to = $target[$identity];

            foreach (array_unique([...array_keys($from), ...array_keys($to)]) as $column) {
                if (! array_key_exists($column, $from)
                    || ! array_key_exists($column, $to)
                    || self::canonicalValue($column, $from[$column]) !== self::canonicalValue($column, $to[$column])) {
                    $different[$column] = true;
                }
            }
        }

        $columns = array_keys($different);
        sort($columns, SORT_STRING);

        return $columns;
    }

    private static function canonicalRows(array $rows): array
    {
        $canonical = [];

        foreach ($rows as $row) {
            $attributes = (array) $row;
            ksort($attributes);

            foreach ($attributes as $column => $value) {
                $attributes[$column] = self::normalizedValue($column, $value);
            }

            $canonical[] = json_encode($attributes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }

        sort($canonical, SORT_STRING);

        return $canonical;
    }

    private static function canonicalValue(string $column, mixed $value): string
    {
        return json_encode(
            self::normalizedValue($column, $value),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
    }

    private static function normalizedValue(string $column, mixed $value): mixed
    {
        if (in_array($column, ['before', 'after'], true) && is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $value = self::sortedJson($decoded);
            }
        }

        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        } elseif (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }

        if (is_string($value) && preg_match('/\\A\\d{4}-\\d{2}-\\d{2} /', $value) === 1) {
            $value = preg_replace('/\\.0+\\z/', '', $value);
        }

        return $value;
    }

    private static function sortedJson(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(self::sortedJson(...), $value);
        }

        ksort($value);

        return array_map(self::sortedJson(...), $value);
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
