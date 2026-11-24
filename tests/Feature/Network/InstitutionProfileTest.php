<?php

namespace Tests\Feature\Network;

use App\Models\Tenant\AuditLog;
use App\Models\Tenant\InstitutionProfile;
use App\Models\Tenant\SchoolYear;
use App\Security\Totp;
use App\Support\Cnpj;
use App\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class InstitutionProfileTest extends TwoMunicipalityTestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private function gestao(bool $confirmar = true): void
    {
        $user = $this->criarUsuario('dados-prefeitura@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');
        $user->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user);
        if ($confirmar) {
            $this->post('/seguranca/segundo-fator/desafio', ['code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp())]);
        }
        config()->set('tenancy.deployed_db_key', 'municipio_a');
    }

    private function dados(array $extra = []): array
    {
        return [
            'department_name' => 'Secretaria Municipal de Educação', 'cnpj' => '99.999.999/0001-91',
            'phone' => '(11) 0000-0000', 'email' => ' Educacao@Exemplo.INVALID ', 'website' => 'www.exemplo.invalid',
            'postal_code' => '00000000', 'address_street' => 'Praça da Matriz', 'address_number' => '100',
            'address_district' => 'Centro', 'address_city' => 'Cidade Fictícia', 'address_state' => 'sp',
            'secretary_name' => 'Pessoa Fictícia', 'secretary_title' => 'Secretária Municipal de Educação', ...$extra,
        ];
    }

    public function test_cnpj_confere_os_digitos_verificadores(): void
    {
        $this->assertTrue(Cnpj::isValid('99.999.999/0001-91'));
        $this->assertFalse(Cnpj::isValid('99.999.999/0001-92'));
        $this->assertFalse(Cnpj::isValid('11.111.111/1111-11'));
        $this->assertSame('99.999.999/0001-91', Cnpj::format('99999999000191'));
    }

    public function test_gestao_salva_os_dados_normalizados_com_auditoria_e_o_timbre_muda(): void
    {
        $this->gestao();
        $this->get('/m/municipio-a/dados-da-prefeitura')->assertOk()
            ->assertSee('Prévia do papel')->assertSee('Ainda faltam: CNPJ, telefone, e-mail, site, endereço.');

        $this->post('/m/municipio-a/dados-da-prefeitura', $this->dados(['cnpj' => '99.999.999/0001-92']))
            ->assertSessionHasErrors(['cnpj' => 'Confira o CNPJ: os dígitos verificadores não conferem.']);
        $this->post('/m/municipio-a/dados-da-prefeitura', $this->dados(['phone' => '0000']))->assertSessionHasErrors('phone');

        $this->post('/m/municipio-a/dados-da-prefeitura', $this->dados())
            ->assertRedirect('/m/municipio-a/dados-da-prefeitura')
            ->assertSessionHas('status', 'Dados da Prefeitura salvos. Todos os documentos já usam o novo timbre.');
        $this->post('/m/municipio-a/dados-da-prefeitura', $this->dados())->assertSessionHas('status', 'Nada mudou nos dados da Prefeitura.');

        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $perfil = InstitutionProfile::current();
            $this->assertSame('99999999000191', $perfil->cnpj);
            $this->assertSame('1100000000', $perfil->phone);
            $this->assertSame('educacao@exemplo.invalid', $perfil->email);
            $this->assertSame('https://www.exemplo.invalid', $perfil->website);
            $this->assertSame('00000-000', $perfil->postal_code);
            $this->assertSame('SP', $perfil->address_state);
            $this->assertSame([], $perfil->missing());
            $this->assertSame(1, AuditLog::query()->where('action', 'institution.profile.updated')->count());
        });

        $this->get('/m/municipio-a/dados-da-prefeitura')->assertOk()
            ->assertSee('Rodapé completo.')->assertSee('CNPJ 99.999.999/0001-91')->assertDontSee('Ainda faltam');
        app(TenantContext::class)->runFor($this->municipioA, fn () => SchoolYear::query()->create([
            'id' => (string) Str::uuid(), 'year' => (int) now()->format('Y'),
            'start_date' => now()->startOfYear()->toDateString(), 'end_date' => now()->endOfYear()->toDateString(), 'status' => 'active',
        ]));
        $this->get('/m/municipio-a/relatorio-mensal-secretaria')->assertOk()
            ->assertSee('Pessoa Fictícia')
            ->assertSee('Praça da Matriz, 100, Centro - Cidade Fictícia/SP - CEP 00000-000')
            ->assertSee('Telefone (11) 0000-0000')
            ->assertSee('www.exemplo.invalid');
    }

    public function test_gravar_exige_segundo_fator_recente_e_escola_nao_acessa(): void
    {
        $this->gestao(false);
        $this->post('/m/municipio-a/dados-da-prefeitura', $this->dados())->assertRedirect(route('two-factor.challenge'));

        $user = $this->criarUsuario('diretor-dados@example.invalid');
        $this->vincular($user, $this->municipioA, 'school_director', [self::ESCOLA_A1]);
        $this->actingAs($user)->get('/m/municipio-a/dados-da-prefeitura')->assertForbidden();
    }
}
