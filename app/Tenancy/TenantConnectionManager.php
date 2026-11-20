<?php

namespace App\Tenancy;

use App\Models\Platform\Municipality;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class TenantConnectionManager
{
    public function activate(Municipality $municipality): void
    {
        $key = $municipality->db_key;

        if (! is_string($key) || ! preg_match('/\A[a-z][a-z0-9_]{0,63}\z/D', $key)) {
            throw new InvalidArgumentException('Identificador de banco municipal invalido.');
        }

        $connections = config('tenancy.connections', []);
        $settings = is_array($connections) ? ($connections[$key] ?? null) : null;

        if (! is_array($settings)
            || ($settings['driver'] ?? null) !== 'mariadb'
            || ! is_string($settings['database'] ?? null)
            || trim($settings['database']) === ''
            || ! array_key_exists('username', $settings)
            || ! array_key_exists('password', $settings)) {
            throw new InvalidArgumentException('Conexao municipal nao configurada.');
        }

        // Uma configuracao duplicada comprometeria a fronteira entre municipios.
        foreach ($connections as $otherKey => $otherSettings) {
            if ($otherKey === $key || ! is_array($otherSettings) || ($otherSettings['driver'] ?? null) !== 'mariadb') {
                continue;
            }

            if ($this->physicalDatabaseIdentity($otherSettings) === $this->physicalDatabaseIdentity($settings)) {
                throw new InvalidArgumentException('Mais de um municipio aponta para a mesma base.');
            }
        }

        $this->reset();

        try {
            config()->set('database.connections.tenant', $settings);
            DB::connection('tenant')->getPdo();
        } catch (Throwable $exception) {
            $this->reset();

            throw $exception;
        }
    }

    public function reset(): void
    {
        DB::purge('tenant');
        config()->set('database.connections.tenant', ['driver' => 'astuti_tenant_disabled']);
    }

    private function physicalDatabaseIdentity(array $settings): array
    {
        $host = strtolower(trim((string) ($settings['host'] ?? '')));

        if ($host === 'localhost') {
            $host = '127.0.0.1';
        }

        return [
            $host,
            (string) ($settings['port'] ?? '3306'),
            (string) ($settings['unix_socket'] ?? ''),
            strtolower(trim((string) ($settings['database'] ?? ''))),
        ];
    }
}
