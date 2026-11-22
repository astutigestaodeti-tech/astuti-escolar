<?php

namespace App\Console\Commands;

use App\Audit\AuditTrail;
use App\Models\Platform\Municipality;
use App\Tenancy\TenantConnectionManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ProvisionMunicipality extends Command
{
    protected $signature = 'astuti:municipio-provisionar {slug}';

    protected $description = 'Aplica as migracoes de uma base municipal previamente configurada.';

    public function handle(TenantConnectionManager $connections, AuditTrail $audit): int
    {
        $municipality = Municipality::query()
            ->where('slug', $this->argument('slug'))
            ->first();

        if ($municipality === null) {
            $audit->record('platform', 'municipality.provision.not_found', Municipality::class);
            $this->error('Municipio nao cadastrado na base central.');

            return self::FAILURE;
        }

        $attemptId = DB::connection('platform')->transaction(function () use ($municipality): int {
            $municipality->status = 'provisioning';
            $municipality->save();

            $now = now();

            return DB::connection('platform')->table('municipality_provision_attempts')->insertGetId([
                'municipality_id' => $municipality->id,
                'status' => 'started',
                'started_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

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
            $this->finishAttempt($attemptId, 'completed');
            $audit->record('platform', 'municipality.provision.completed', Municipality::class, $municipality->id,
                ['status' => 'provisioning'], ['status' => 'active']);

            $this->info('Municipio provisionado com sucesso.');

            return self::SUCCESS;
        } catch (Throwable) {
            $municipality->status = 'failed';
            $municipality->save();
            $this->finishAttempt($attemptId, 'failed');
            $audit->record('platform', 'municipality.provision.failed', Municipality::class, $municipality->id,
                ['status' => 'provisioning'], ['status' => 'failed']);

            // Nunca imprimir detalhes da conexao ou credenciais no terminal.
            $this->error('Falha na provisao municipal. Verifique a configuracao segura.');

            return self::FAILURE;
        } finally {
            $connections->reset();
        }
    }

    private function finishAttempt(int $id, string $status): void
    {
        DB::connection('platform')->table('municipality_provision_attempts')
            ->where('id', $id)
            ->update([
                'status' => $status,
                'finished_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
