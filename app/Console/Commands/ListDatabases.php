<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Lista os nomes dos bancos desta instalação para o backup (sem senhas, usuários ou endereços).
 * Só bancos no próprio servidor: o backup roda pela conta local do PostgreSQL.
 */
class ListDatabases extends Command
{
    protected $signature = 'astuti:bancos';

    protected $description = 'Lista os bancos PostgreSQL desta instalação (plataforma e Prefeitura), um por linha, para o backup.';

    public function handle(): int
    {
        $lines = [];
        $platform = (array) config('database.connections.platform', []);
        if ($this->isLocal($platform)) {
            $lines[] = 'plataforma '.$platform['database'];
        }
        foreach ((array) config('tenancy.connections', []) as $key => $connection) {
            if ($this->isLocal((array) $connection) && preg_match('/\A[a-z0-9_]+\z/', (string) $key) === 1) {
                $lines[] = 'prefeitura-'.str_replace('_', '-', (string) $key).' '.$connection['database'];
            }
        }
        if ($lines === []) {
            $this->error('Nenhum banco local configurado.');

            return self::FAILURE;
        }
        foreach ($lines as $line) {
            $this->line($line);
        }

        return self::SUCCESS;
    }

    private function isLocal(array $connection): bool
    {
        return ($connection['driver'] ?? null) === 'pgsql'
            && in_array(strtolower((string) ($connection['host'] ?? '')), ['127.0.0.1', 'localhost', '::1'], true)
            && preg_match('/\A[A-Za-z0-9_]+\z/', (string) ($connection['database'] ?? '')) === 1;
    }
}
