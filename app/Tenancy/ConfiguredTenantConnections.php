<?php

namespace App\Tenancy;

final class ConfiguredTenantConnections
{
    /**
     * Um VPS de producao recebe as credenciais de exatamente um municipio.
     * A configuracao incompleta falha fechada: nenhuma conexao municipal.
     */
    public static function forOneMunicipality(array $values): array
    {
        $key = $values['db_key'] ?? null;
        $host = $values['host'] ?? null;
        $port = $values['port'] ?? null;
        $database = $values['database'] ?? null;
        $username = $values['username'] ?? null;
        $password = $values['password'] ?? null;

        if (! is_string($key) || preg_match('/\A[a-z][a-z0-9_]{0,63}\z/D', $key) !== 1
            || ! is_string($host) || ! in_array(strtolower($host), ['127.0.0.1', 'localhost', '::1'], true)
            || ! is_scalar($port) || filter_var((string) $port, FILTER_VALIDATE_INT) === false
            || (int) $port < 1 || (int) $port > 65535
            || ! is_string($database) || preg_match('/\A[a-z][a-z0-9_]{0,62}\z/D', $database) !== 1
            || ! is_string($username) || trim($username) === '' || str_contains($username, "\n")
            || ! is_string($password) || $password === '') {
            return [];
        }

        return [
            $key => [
                'driver' => 'pgsql',
                'url' => null,
                'host' => $host,
                'port' => (string) $port,
                'database' => $database,
                'username' => $username,
                'password' => $password,
                'charset' => 'utf8',
                'prefix' => '',
                'prefix_indexes' => true,
                'search_path' => 'public',
                'timezone' => (string) env('APP_TIMEZONE', 'America/Sao_Paulo'),
                'sslmode' => 'prefer',
            ],
        ];
    }
}
