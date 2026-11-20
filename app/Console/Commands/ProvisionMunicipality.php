<?php

namespace App\Console\Commands;

use App\Models\Platform\Municipality;
use App\Tenancy\TenantConnectionManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Throwable;

class ProvisionMunicipality extends Command
{
    protected $signature = 'astuti:municipio-provisionar {slug}';

    protected $description = 'Aplica as migracoes de uma base municipal previamente configurada.';

    public function handle(TenantConnectionManager $connections): int
    {
        $municipality = Municipality::query()
            ->where('slug', $this->argument('slug'))
            ->first();

        if ($municipality === null) {
            $this->error('Municipio nao cadastrado na base central.');

            return self::FAILURE;
        }

        $municipality->status = 'provisioning';
        $municipality->save();

        try {
            $connections->activate($municipality);

            $exitCode = Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => database_path('migrations/tenant'),
                '--realpath' => true,
                '--force' => true,
            ]);

            if ($exitCode !== self::SUCCESS) {
                throw new RuntimeException('Migracoes municipais nao concluidas.');
            }

            $municipality->status = 'active';
            $municipality->save();

            $this->info('Municipio provisionado com sucesso.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $municipality->status = 'failed';
            $municipality->save();

            // Nunca imprimir detalhes da conexao ou credenciais no terminal.
            $this->error('Falha na provisao municipal. Verifique a configuracao segura.');

            return self::FAILURE;
        } finally {
            $connections->reset();
        }
    }
}
