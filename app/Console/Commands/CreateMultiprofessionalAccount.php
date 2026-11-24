<?php

namespace App\Console\Commands;

use App\Audit\AuditTrail;
use App\Models\Platform\Membership;
use App\Models\Platform\Municipality;
use App\Models\User;
use App\Multiprofessional\TeamAccess;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateMultiprofessionalAccount extends Command
{
    protected $signature = 'astuti:equipe-usuario
        {municipio : Identificador da Prefeitura nesta instalação}
        {email : E-mail institucional da profissional}
        {perfil : psicologo, assistente_social ou pedagogo_equipe}
        {--nome= : Nome completo, obrigatório para conta nova}';

    protected $description = 'Cria ou vincula uma conta individual da equipe multiprofissional, sem permissões escolares ou administrativas.';

    public function handle(): int
    {
        $role = (string) $this->argument('perfil');
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $name = trim((string) $this->option('nome'));
        $municipality = Municipality::query()->where('slug', $this->argument('municipio'))->where('status', 'active')->first();
        $deployed = config('tenancy.deployed_db_key');
        if ($municipality === null || (filled($deployed) && $municipality->db_key !== $deployed)
            || ! isset(TeamAccess::ROLES[$role]) || ! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 160) {
            $this->error('Confira a Prefeitura, o e-mail institucional e o perfil da equipe.');

            return self::INVALID;
        }

        $user = User::query()->where('email', $email)->first();
        if ($user !== null && ! $this->eligible($user, $municipality)) {
            $this->error('Use uma conta individual ativa, sem vínculo escolar ou de gestão nesta Prefeitura.');

            return self::FAILURE;
        }

        $password = null;
        if ($user === null) {
            if ($name === '' || mb_strlen($name) > 160 || ! $this->input->isInteractive()) {
                $this->error('Conta nova exige nome e senha digitada no terminal interativo.');

                return self::INVALID;
            }
            $password = (string) $this->secret('Senha da conta (mínimo 12 caracteres)');
            if (mb_strlen($password) < 12 || $password !== (string) $this->secret('Confirme a senha')) {
                $this->error('Senha curta ou confirmação diferente. Nada foi alterado.');

                return self::FAILURE;
            }
        }

        $success = DB::connection('platform')->transaction(function () use ($email, $name, $password, $municipality, $role): bool {
            $user = User::query()->where('email', $email)->lockForUpdate()->first();
            if ($user === null) {
                if ($password === null) {
                    return false;
                }
                $user = User::query()->create([
                    'id' => (string) Str::uuid(), 'name' => $name, 'email' => $email,
                    'status' => 'active', 'password' => Hash::make($password),
                ]);
            }
            if (! $this->eligible($user, $municipality)) {
                return false;
            }
            $membership = Membership::query()->where('user_id', $user->id)->where('municipality_id', $municipality->id)
                ->where('role', $role)->lockForUpdate()->first();
            if ($membership !== null) {
                // Nunca reativar vínculo suspenso implicitamente.
                return $membership->status === 'active';
            }
            $membership = Membership::query()->create([
                'id' => (string) Str::uuid(), 'user_id' => $user->id,
                'municipality_id' => $municipality->id, 'role' => $role, 'status' => 'active',
            ]);
            app(AuditTrail::class)->record('platform', 'multiprofessional.membership.created', Membership::class, $membership->id);

            return true;
        });
        if (! $success) {
            $this->error('O vínculo não foi concedido. Confira a situação da conta e do perfil.');

            return self::FAILURE;
        }

        $this->info('Vínculo da equipe disponível. Entre com o e-mail institucional e ative o segundo fator.');

        return self::SUCCESS;
    }

    private function eligible(User $user, Municipality $municipality): bool
    {
        return $user->status === 'active' && ! Membership::query()
            ->where('user_id', $user->id)->where('municipality_id', $municipality->id)
            ->where('status', 'active')->whereNotIn('role', array_keys(TeamAccess::ROLES))->exists();
    }
}
