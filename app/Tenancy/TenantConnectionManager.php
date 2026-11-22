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

        // Um VPS de producao jamais pode servir duas prefeituras.
        if (app()->environment('production')) {
            $deployedKey = config('tenancy.deployed_db_key');

            if (! is_string($deployedKey) || $deployedKey !== $key
                || ! is_array($connections) || count($connections) !== 1) {
                throw new InvalidArgumentException('Municipio indisponivel nesta instalacao.');
            }
        }

        if (! is_array($settings)
            || ($settings['driver'] ?? null) !== 'pgsql'
            || ! is_string($settings['host'] ?? null)
            || trim($settings['host']) === ''
            || ! is_numeric($settings['port'] ?? null)
            || ! is_string($settings['database'] ?? null)
            || trim($settings['database']) === ''
            || ! empty($settings['url'])
            || ! array_key_exists('username', $settings)
            || ! array_key_exists('password', $settings)) {
            throw new InvalidArgumentException('Conexao municipal nao configurada.');
        }

        $platform = config('database.connections.platform', []);
        if (is_array($platform) && ($platform['driver'] ?? null) === 'pgsql') {
            if (! empty($platform['url'])) {
                throw new InvalidArgumentException('A conexao central deve ter destino explicito.');
            }

            if ($this->physicalDatabaseIdentity($platform) === $this->physicalDatabaseIdentity($settings)) {
                throw new InvalidArgumentException('O banco municipal nao pode ser o banco central.');
            }
        }

        // Uma configuracao duplicada comprometeria a fronteira entre municipios.
        foreach ($connections as $otherKey => $otherSettings) {
            if ($otherKey === $key || ! is_array($otherSettings) || ($otherSettings['driver'] ?? null) !== 'pgsql') {
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

        if (in_array($host, ['localhost', '::1'], true)) {
            $host = '127.0.0.1';
        }

        return [
            $host,
            (string) ($settings['port'] ?? '5432'),
            (string) ($settings['unix_socket'] ?? ''),
            strtolower(trim((string) ($settings['database'] ?? ''))),
        ];
    }
}
