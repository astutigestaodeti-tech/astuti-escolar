<?php

namespace App\Support\DatabaseMigration;

use InvalidArgumentException;
use PDOException;
use RuntimeException;
use Throwable;

final class SafeMigrationDiagnostic
{
    /**
     * Informa apenas motivos previstos no codigo, nunca mensagens de driver,
     * SQL, valores de colunas, identificadores de alunos ou credenciais.
     */
    public static function describe(Throwable $exception): string
    {
        if ($exception instanceof PDOException) {
            $state = $exception->errorInfo[0] ?? null;

            if (! is_string($state) || preg_match('/\A[A-Z0-9]{5}\z/', $state) !== 1) {
                return 'Falha SQL (codigo indisponivel).';
            }

            return "Falha SQL (SQLSTATE {$state}).";
        }

        if ($exception instanceof RowContentMismatchException
            && in_array($exception->table(), ['platform_audit_logs', 'audit_logs'], true)) {
            $allowed = [
                'id', 'occurred_at', 'user_id', 'role', 'action', 'auditable_type',
                'auditable_id', 'before', 'after', 'ip', 'user_agent', 'request_id',
            ];
            $columns = array_values(array_intersect($exception->differingColumns(), $allowed));

            if ($columns !== []) {
                return $exception->getMessage().' Campos divergentes: '.implode(', ', $columns).'.';
            }
        }

        if ($exception instanceof InvalidArgumentException || $exception instanceof RuntimeException) {
            $reason = $exception->getMessage();
            if (in_array($reason, [
                'Tabela esperada nao encontrada.',
                'Estruturas de tabela incompatíveis.',
                'Destino contem registros. Migracao recusada.',
                'Origem nao corresponde ao cenario ficticio Monte Sereno v2.',
                'Contagens divergentes apos copia.',
                'O hash de uma senha foi alterado durante a copia.',
            ], true)) {
                return $reason;
            }

            foreach (array_merge(MigrationVerifier::PLATFORM_TABLES, MigrationVerifier::TENANT_TABLES) as $table) {
                if ($reason === "Conteudo da tabela {$table} divergiu apos copia."
                    || $reason === "Integridade da tabela {$table} nao confirmada.") {
                    return $reason;
                }
            }

            foreach ([
                'school_scopes.school_id',
                'platform_audit_logs.user_id',
                'audit_logs.user_id',
            ] as $reference) {
                if ($reason === "Referência inválida em {$reference}.") {
                    return $reason;
                }
            }
        }

        return sprintf('Falha interna (%s).', class_basename($exception));
    }

    /**
     * Recusa diagnostico explicito quando o destino nao e o cluster temporario
     * escolhido pelo roteiro, especialmente a porta 5432 dos bancos originais.
     */
    public static function enabledFor(bool $requested, array $platform, array $tenant): bool
    {
        if (! $requested) {
            return false;
        }

        $port = (string) ($platform['port'] ?? '');

        if (! ctype_digit($port) || (int) $port < 55432 || (int) $port > 55531) {
            return false;
        }

        foreach ([
            [$platform, 'astuti_platform_dev'],
            [$tenant, 'astuti_monte_sereno_dev'],
        ] as [$settings, $database]) {
            if (($settings['driver'] ?? null) !== 'pgsql'
                || ($settings['host'] ?? null) !== '127.0.0.1'
                || ($settings['database'] ?? null) !== $database
                || ($settings['username'] ?? null) !== 'astuti_ensaio'
                || ! empty($settings['url'])
                || (string) ($settings['port'] ?? '') !== $port) {
                return false;
            }
        }

        return true;
    }
}
