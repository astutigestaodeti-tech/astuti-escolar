<?php

namespace App\Console\Commands;

use App\Models\Platform\Membership;
use App\Models\Platform\Municipality;
use App\Models\User;
use App\Support\EmailNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Cria ou atualiza uma conta de gestão com nome de usuário curto (por exemplo, "admin").
 * A senha é digitada pela pessoa no terminal: não fica no repositório, em script nem em
 * variável de ambiente, e nenhuma atualização do sistema a altera.
 */
class CreateAdministratorAccount extends Command
{
    protected $signature = 'astuti:usuario-admin
        {usuario=admin : Nome de usuário para entrar no sistema}
        {--nome=Administrador do sistema : Nome exibido no topo das telas}
        {--trocar-senha : Troca a senha de uma conta que já existe}';

    protected $description = 'Cria (ou troca a senha de) uma conta de gestão municipal com usuário curto e senha escolhida por você.';

    public function handle(): int
    {
        $login = mb_strtolower(trim((string) $this->argument('usuario')));
        if (preg_match('/^[a-z0-9][a-z0-9._-]{2,40}$/', $login) !== 1) {
            $this->error('Use de 3 a 41 letras minúsculas, números, ponto, hífen ou sublinhado, sem espaços.');

            return self::INVALID;
        }

        $email = EmailNormalizer::fromLogin($login);
        $user = User::query()->where('email', $email)->first();
        if ($user !== null && ! $this->option('trocar-senha')) {
            $this->info("A conta \"{$login}\" já existe e a senha foi mantida. Para trocar: php artisan astuti:usuario-admin {$login} --trocar-senha");
            $this->linkMunicipalities($user);

            return self::SUCCESS;
        }

        $password = $this->askPassword();
        if ($password === null) {
            return self::FAILURE;
        }

        DB::connection('platform')->transaction(function () use (&$user, $email, $password): void {
            if ($user === null) {
                $user = User::query()->create([
                    'id' => (string) Str::uuid(),
                    'name' => (string) $this->option('nome'),
                    'email' => $email,
                    'status' => 'active',
                    'password' => Hash::make($password),
                ]);
            } else {
                $user->forceFill(['password' => Hash::make($password), 'status' => 'active'])->save();
            }
        });

        $linked = $this->linkMunicipalities($user);
        $this->info("Pronto. Entre com o usuário \"{$login}\" e a senha que você digitou.");
        if ($linked === 0) {
            $this->warn('Nenhuma rede ativa foi encontrada para vincular a conta.');
        }
        $this->line('Recomendado: ative o segundo fator em "Segurança da conta" logo no primeiro acesso.');

        return self::SUCCESS;
    }

    private function askPassword(): ?string
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $password = (string) $this->secret('Digite a senha (não aparece na tela; mínimo de 8 caracteres)');
            if (mb_strlen($password) < 8) {
                $this->error('A senha precisa ter pelo menos 8 caracteres.');

                continue;
            }
            if ($password !== (string) $this->secret('Digite a senha de novo')) {
                $this->error('As duas senhas não conferem.');

                continue;
            }

            return $password;
        }
        $this->error('Nada foi alterado.');

        return null;
    }

    /** Gestão municipal em todas as redes ativas desta instalação (cada instalação atende uma Prefeitura). */
    private function linkMunicipalities(User $user): int
    {
        $municipalities = Municipality::query()->where('status', 'active')->get();
        foreach ($municipalities as $municipality) {
            Membership::query()->firstOrCreate(
                ['user_id' => $user->id, 'municipality_id' => $municipality->id, 'role' => 'municipal_manager'],
                ['id' => (string) Str::uuid(), 'status' => 'active'],
            );
        }

        return $municipalities->count();
    }
}
