<?php

namespace Tests\Feature\Students;

use App\Models\User;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class PostalCodeLookupTest extends TwoMunicipalityTestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private function account(string $role = 'school_secretary', array $schoolIds = [self::ESCOLA_A1]): User
    {
        $user = $this->criarUsuario((string) Str::uuid().'@example.invalid');
        $this->vincular($user, $this->municipioA, $role, $schoolIds);
        $user->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()])->save();

        return $user;
    }

    private function verify(User $user): void
    {
        $this->actingAs($user)
            ->post('/seguranca/segundo-fator/desafio', ['code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp())])
            ->assertRedirect();
    }

    private function url(string $cep = '01001000', string $escola = self::ESCOLA_A1): string
    {
        return '/m/municipio-a/escolas/'.$escola.'/alunos/cep/'.$cep;
    }

    private function response(): array
    {
        return [
            'cep' => '01001-000',
            'logradouro' => 'Praça da Sé',
            'bairro' => 'Sé',
            'localidade' => 'São Paulo',
            'uf' => 'SP',
            'complemento' => 'lado ímpar',
            'ibge' => '3550308',
        ];
    }

    public function test_consulta_cep_retorna_endereco_publico_e_reutiliza_cache_municipal(): void
    {
        $this->verify($this->account());
        Http::fake(['viacep.com.br/*' => Http::response($this->response())]);

        $this->getJson($this->url('01001-000'))
            ->assertOk()
            ->assertJsonPath('postal_code', '01001-000')
            ->assertJsonPath('street', 'Praça da Sé')
            ->assertJsonPath('district', 'Sé')
            ->assertJsonPath('city', 'São Paulo')
            ->assertJsonPath('state', 'SP')
            ->assertJsonPath('source', 'online')
            ->assertJsonMissingPath('complement')
            ->assertJsonMissingPath('student_name');

        $this->getJson($this->url())->assertOk()->assertJsonPath('source', 'cache');
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://viacep.com.br/ws/01001000/json/');

        $this->assertSame(1, app(TenantContext::class)->runFor($this->municipioA, fn (): int => DB::connection('tenant')
            ->table('postal_code_cache')->count()));
    }

    public function test_cep_geral_sem_logradouro_deixa_campos_para_preenchimento_manual(): void
    {
        $this->verify($this->account());
        Http::fake(['viacep.com.br/*' => Http::response([
            'cep' => '07400-000', 'logradouro' => '', 'bairro' => '',
            'localidade' => 'Santa Isabel', 'uf' => 'SP',
        ])]);

        $this->getJson($this->url('07400000'))
            ->assertOk()
            ->assertJsonPath('street', null)
            ->assertJsonPath('district', null)
            ->assertJsonPath('city', 'Santa Isabel')
            ->assertJsonPath('state', 'SP');
    }

    public function test_cep_invalido_ou_inexistente_gera_mensagem_sem_solicitar_outros_dados(): void
    {
        $this->verify($this->account());
        Http::fake(['viacep.com.br/*' => Http::response(['erro' => true])]);

        $this->getJson($this->url('123'))->assertStatus(422)->assertJsonPath('message', 'Informe um CEP com oito números.');
        $this->getJson($this->url('99999999'))->assertNotFound()->assertJsonPath('message', 'CEP não encontrado. Preencha o endereço manualmente.');
        Http::assertSentCount(1);
    }

    public function test_resposta_com_cep_ou_uf_invalidos_nao_e_armazenada(): void
    {
        $this->verify($this->account());
        Http::fake(['viacep.com.br/*' => Http::sequence()
            ->push(array_replace($this->response(), ['cep' => '01002-000']))
            ->push(array_replace($this->response(), ['cep' => '01001-000X']))
            ->push(array_replace($this->response(), ['cep' => ['01001-000']]))
            ->push(array_replace($this->response(), ['uf' => 'SPX']))
            ->push(array_replace($this->response(), ['uf' => ['SP']]))
            ->push($this->response())]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->getJson($this->url())->assertStatus(503)
                ->assertJsonPath('message', 'Consulta de CEP indisponível. Preencha o endereço manualmente.');
            $this->assertSame(0, app(TenantContext::class)->runFor($this->municipioA, fn (): int => DB::connection('tenant')
                ->table('postal_code_cache')->count()));
        }

        $this->getJson($this->url())->assertOk()->assertJsonPath('postal_code', '01001-000')
            ->assertJsonPath('source', 'online');
        $this->getJson($this->url())->assertOk()->assertJsonPath('source', 'cache');
        Http::assertSentCount(6);
    }

    public function test_erro_do_servico_permite_preenchimento_manual_sem_cache(): void
    {
        $this->verify($this->account());
        Http::fake(['viacep.com.br/*' => Http::response('indisponível', 503)]);

        $this->getJson($this->url())->assertStatus(503)
            ->assertJsonPath('message', 'Consulta de CEP indisponível. Preencha o endereço manualmente.');
    }

    public function test_cache_antigo_e_usado_se_servico_estiver_fora_do_ar(): void
    {
        $this->verify($this->account());
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            DB::connection('tenant')->table('postal_code_cache')->insert([
                'postal_code' => '01001-000',
                'street' => 'Praça da Sé',
                'district' => 'Sé',
                'city' => 'São Paulo',
                'state' => 'SP',
                'fetched_at' => now()->subDays(60),
            ]);
        });
        Http::fake(['viacep.com.br/*' => Http::response('indisponível', 503)]);

        $this->getJson($this->url())->assertOk()
            ->assertJsonPath('source', 'stale')
            ->assertJsonPath('street', 'Praça da Sé');
        Http::assertSentCount(1);
    }

    public function test_professor_ou_escola_sem_permissao_nao_consulta_e_sem_2fa_nao_consulta(): void
    {
        $teacher = $this->account('teacher', [self::ESCOLA_A1]);
        $this->verify($teacher);
        $this->getJson($this->url())->assertForbidden();

        $otherSchool = $this->account('school_secretary', [self::ESCOLA_A2]);
        $this->verify($otherSchool);
        $this->getJson($this->url())->assertForbidden();

        $unverified = $this->account();
        $this->actingAs($unverified)->getJson($this->url())->assertForbidden();

        Http::assertNothingSent();
    }
}
