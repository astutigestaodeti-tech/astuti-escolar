<?php

namespace Tests\Feature\Address;

use App\Address\PostalCodeBook;
use App\Models\Platform\PlatformAuditLog;
use App\Models\Tenant\PostalCode;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Tests\Support\TwoMunicipalityTestCase;

class PostalCodeLookupTest extends TwoMunicipalityTestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private function entrar(string $email, $municipio, string $role = 'school_secretary', array $schools = [self::ESCOLA_A1]): void
    {
        $user = $this->criarUsuario($email);
        $this->vincular($user, $municipio, $role, $schools);
        $user->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', [
            'code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp()),
        ])->assertRedirect();
    }

    public function test_base_ficticia_carrega_so_na_prefeitura_escolhida_e_e_auditada(): void
    {
        $this->artisan('astuti:ceps-importar', ['municipio' => 'municipio-a', '--ficticio' => true])
            ->expectsOutputToContain('97 CEP(s) carregados')
            ->assertSuccessful();
        // Rodar de novo só atualiza.
        $this->artisan('astuti:ceps-importar', ['municipio' => 'municipio-a', '--ficticio' => true])->assertSuccessful();

        $this->assertSame(97, app(TenantContext::class)->runFor($this->municipioA, fn (): int => PostalCode::query()->count()));
        $this->assertSame(0, app(TenantContext::class)->runFor($this->municipioB, fn (): int => PostalCode::query()->count()));
        $this->assertSame(2, PlatformAuditLog::query()->where('action', 'postal_codes.imported')->count());

        $geral = app(TenantContext::class)->runFor($this->municipioA, fn (): ?array => app(PostalCodeBook::class)->find('00990010'));
        $this->assertSame(['cep' => '00990-010', 'logradouro' => null, 'bairro' => null, 'cidade' => 'Monte Sereno', 'uf' => 'SP', 'zona' => 'urban'], $geral);
    }

    public function test_consulta_devolve_o_endereco_e_nao_atravessa_prefeituras(): void
    {
        $this->artisan('astuti:ceps-importar', ['municipio' => 'municipio-a', '--ficticio' => true])->assertSuccessful();
        $this->entrar('cep-a@example.invalid', $this->municipioA);

        $this->getJson('/m/municipio-a/cep/00990-101')->assertOk()->assertExactJson([
            'encontrado' => true, 'cep' => '00990-101', 'logradouro' => 'Rua das Palmeiras', 'bairro' => 'Vila Esperança',
            'cidade' => 'Monte Sereno', 'uf' => 'SP', 'zona' => 'urban',
        ]);
        $this->getJson('/m/municipio-a/cep/07500000')->assertOk()->assertJson(['encontrado' => false]);
        $this->getJson('/m/municipio-a/cep/0750-000')->assertStatus(422);

        $this->entrar('cep-b@example.invalid', $this->municipioB, 'school_secretary', [self::ESCOLA_B1]);
        $this->getJson('/m/municipio-b/cep/00990-101')->assertOk()->assertJson(['encontrado' => false]);
        $this->getJson('/m/municipio-a/cep/00990-101')->assertForbidden();
    }

    public function test_arquivo_invalido_e_recusado_sem_gravar(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ceps-');
        file_put_contents($path, "cep;logradouro;bairro;cidade;uf\n12345;Rua X;Centro;Cidade;SP\n");

        $this->artisan('astuti:ceps-importar', ['municipio' => 'municipio-a', 'arquivo' => $path, '--fonte' => 'Teste'])
            ->expectsOutputToContain('Linha 2 do arquivo de CEPs inválida')
            ->assertFailed();
        $this->artisan('astuti:ceps-importar', ['municipio' => 'municipio-a', 'arquivo' => $path])->assertFailed();
        $this->assertSame(0, app(TenantContext::class)->runFor($this->municipioA, fn (): int => PostalCode::query()->count()));
        unlink($path);
    }
}
