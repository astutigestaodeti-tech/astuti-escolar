<?php

namespace Tests\Feature\Students;

use App\Models\Tenant\AuditLog;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\User;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class GeoLocationTest extends TwoMunicipalityTestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private string $student;

    protected function setUp(): void
    {
        parent::setUp();
        config(['geo.geocoder_url' => 'https://geocoder.example.test/search', 'geo.transport_min_km' => 2.0]);

        $this->student = $this->inMunicipality(function (): string {
            $year = SchoolYear::query()->create(['id' => (string) Str::uuid(), 'year' => 2027, 'start_date' => '2027-02-01', 'end_date' => '2027-12-18', 'status' => 'active']);
            $student = Student::query()->create([
                'id' => (string) Str::uuid(), 'full_name' => 'Aluno Fictício do Mapa',
                'address_street' => 'Rua Fictícia', 'address_number' => '100', 'address_district' => 'Centro',
                'address_city' => 'Monte Sereno', 'address_state' => 'SP', 'postal_code' => '00000-000',
            ]);
            StudentEnrollment::query()->create(['id' => (string) Str::uuid(), 'student_id' => $student->id, 'school_id' => self::ESCOLA_A1, 'school_year_id' => $year->id, 'status' => 'active']);

            return $student->id;
        });
    }

    private function inMunicipality(callable $callback): mixed
    {
        return app(TenantContext::class)->runFor($this->municipioA, $callback);
    }

    private function user(string $role, array $schools): User
    {
        $user = $this->criarUsuario((string) Str::uuid().'@example.invalid');
        $this->vincular($user, $this->municipioA, $role, $schools);
        $user->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', ['code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp())])->assertRedirect();

        return $user;
    }

    private function base(): string
    {
        return '/m/municipio-a/escolas/'.self::ESCOLA_A1;
    }

    public function test_mapa_sugere_o_ponto_do_endereco_do_cadastro_sem_salvar(): void
    {
        Http::fake(['geocoder.example.test/*' => Http::response([['lat' => '-23.500000', 'lon' => '-46.600000']])]);
        $this->user('school_secretary', [self::ESCOLA_A1]);

        $this->get($this->base().'/alunos/'.$this->student.'/localizacao')
            ->assertOk()
            ->assertSee('data-lat="-23.5"', false)
            ->assertSee('value="endereco"', false)
            ->assertSee('vendor/leaflet/leaflet.js', false);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'countrycodes=br') && str_contains(urldecode($request->url()), 'street=100 Rua Fictícia'));
        $this->assertNull($this->inMunicipality(fn () => Student::query()->findOrFail($this->student)->latitude));
    }

    public function test_coordenadas_coladas_do_google_maps_vao_direto_para_o_ponto_sem_procurar_endereco(): void
    {
        Http::fake();
        $this->user('school_secretary', [self::ESCOLA_A1]);

        $this->get($this->base().'/localizacao?procurar='.rawurlencode('-23.312345, -46.123456'))
            ->assertOk()
            ->assertSee('data-lat="-23.312345"', false)
            ->assertSee('data-lon="-46.123456"', false)
            ->assertSee('value="mapa"', false)
            ->assertSee('Coordenadas reconhecidas');

        Http::assertNothingSent();
    }

    public function test_link_curto_do_google_maps_e_seguido_so_ate_o_google(): void
    {
        Http::fake([
            'maps.app.goo.gl/*' => Http::response('', 302, ['Location' => 'https://www.google.com/maps/place/Escola/@-23.40,-46.20,17z/data=!3d-23.312345!4d-46.123456']),
        ]);
        $this->user('school_secretary', [self::ESCOLA_A1]);

        $this->get($this->base().'/localizacao?procurar='.rawurlencode('https://maps.app.goo.gl/AbCdEf123'))
            ->assertOk()
            ->assertSee('data-lat="-23.312345"', false)
            ->assertSee('data-lon="-46.123456"', false);

        Http::assertSentCount(1);
    }

    public function test_link_que_nao_traz_o_ponto_explica_como_copiar_as_coordenadas(): void
    {
        Http::fake(['maps.app.goo.gl/*' => Http::response('', 404)]);
        $this->user('school_secretary', [self::ESCOLA_A1]);

        $this->get($this->base().'/localizacao?procurar='.rawurlencode('https://maps.app.goo.gl/Quebrado'))
            ->assertOk()
            ->assertSee('Não consegui ler o ponto deste link do Google Maps');
    }

    public function test_salvar_mostra_distancia_e_aviso_do_transporte_com_auditoria_mascarada(): void
    {
        $this->user('school_secretary', [self::ESCOLA_A1]);

        $this->post($this->base().'/localizacao', ['latitude' => '-23.500000', 'longitude' => '-46.600000', 'geo_source' => 'mapa'])
            ->assertRedirect($this->base())->assertSessionHas('status', 'Endereço e localização da escola salvos.');
        // Cerca de 3,3 km ao norte da escola.
        $this->post($this->base().'/alunos/'.$this->student.'/localizacao', ['latitude' => '-23.470000', 'longitude' => '-46.600000', 'geo_source' => 'mapa'])
            ->assertRedirect()->assertSessionHas('status', 'Localização da residência salva.');

        $this->get($this->base().'/alunos/'.$this->student)
            ->assertOk()
            ->assertSee('3,3 km')
            ->assertSee('em linha reta')
            ->assertSee('Mora a mais de 2,0 km em linha reta e não está no transporte');

        $after = $this->inMunicipality(fn (): array => AuditLog::query()->where('auditable_type', Student::class)->where('action', 'updated')->sole()->after);
        $this->assertSame('[REDACTED]', $after['latitude']);
        $this->assertSame('mapa', $after['geo_source']);
        $schoolAfter = $this->inMunicipality(fn (): array => AuditLog::query()->where('auditable_type', School::class)->where('action', 'updated')->latest('occurred_at')->first()->after);
        $this->assertEqualsWithDelta(-23.5, (float) $schoolAfter['latitude'], 0.000001);
    }

    public function test_ponto_fora_do_brasil_e_recusado_e_remover_limpa(): void
    {
        $this->user('school_secretary', [self::ESCOLA_A1]);
        $url = $this->base().'/alunos/'.$this->student.'/localizacao';

        $this->post($url, ['latitude' => '40.7', 'longitude' => '-74.0', 'geo_source' => 'mapa'])
            ->assertSessionHasErrors(['latitude' => 'O ponto marcado fica fora do Brasil. Marque de novo no mapa.']);
        $this->post($url, ['latitude' => '-23.47', 'longitude' => '-46.6', 'geo_source' => 'mapa'])->assertRedirect();
        $this->post($url, ['remover' => '1'])->assertSessionHas('status', 'Localização removida.');
        $this->assertNull($this->inMunicipality(fn () => Student::query()->findOrFail($this->student)->latitude));
    }

    public function test_professor_ve_so_a_distancia_e_nao_abre_o_mapa(): void
    {
        $this->inMunicipality(function (): void {
            $school = School::query()->findOrFail(self::ESCOLA_A1);
            $school->forceFill(['latitude' => -23.5, 'longitude' => -46.6, 'geo_source' => 'mapa'])->save();
            $student = Student::query()->findOrFail($this->student);
            $student->forceFill(['latitude' => -23.495, 'longitude' => -46.6, 'geo_source' => 'mapa'])->save();
        });
        $this->user('teacher', [self::ESCOLA_A1]);

        $this->get($this->base().'/alunos/'.$this->student)
            ->assertOk()->assertSee('560 m')->assertDontSee('-23.495')->assertDontSee('Marcar no mapa');
        $this->get($this->base().'/alunos/'.$this->student.'/localizacao')->assertForbidden();
        $this->post($this->base().'/alunos/'.$this->student.'/localizacao', ['latitude' => '-23.47', 'longitude' => '-46.6', 'geo_source' => 'mapa'])->assertForbidden();
    }

    public function test_endereco_copiado_do_google_e_escrito_por_extenso_e_cai_para_a_rua_sem_o_numero(): void
    {
        // O mapa não conhece o número 856: responde vazio com número e acha a rua sem ele.
        Http::fake(function ($request) {
            $url = urldecode($request->url());

            return str_contains($url, 'street=856 ')
                ? Http::response([])
                : Http::response([['lat' => '-23.310000', 'lon' => '-46.220000']]);
        });
        $this->user('school_secretary', [self::ESCOLA_A1]);

        $this->get($this->base().'/localizacao?procurar='.rawurlencode('R. Pref. José Basílio Alvarenga, 856 - Jardim Monte Serrat, Santa Isabel - SP, 07500-000'))
            ->assertOk()
            ->assertSee('data-lat="-23.31"', false)
            ->assertSee('Encontrei a rua, mas o mapa não tem o número exato');

        Http::assertSent(fn ($request): bool => str_contains(urldecode($request->url()), 'street=Rua Prefeito José Basílio Alvarenga')
            && str_contains(urldecode($request->url()), 'city=Santa Isabel')
            && ! str_contains($request->url(), '07500'));
    }

    public function test_servico_de_mapa_fora_do_ar_nao_derruba_a_tela(): void
    {
        Http::fake(['geocoder.example.test/*' => Http::response('erro', 503)]);
        $this->user('school_secretary', [self::ESCOLA_A1]);

        $this->get($this->base().'/alunos/'.$this->student.'/localizacao?procurar='.urlencode('Rua Inexistente'))
            ->assertOk()
            ->assertSee('Não encontrei "Rua Inexistente" no mapa', false);
    }

    private function marcarCasaEEscola(): void
    {
        $this->inMunicipality(function (): void {
            School::query()->findOrFail(self::ESCOLA_A1)->forceFill(['latitude' => -23.5, 'longitude' => -46.6, 'geo_source' => 'mapa'])->save();
            // Cerca de 1,7 km em linha reta: abaixo da regra de 2 km.
            Student::query()->findOrFail($this->student)->forceFill(['latitude' => -23.485, 'longitude' => -46.6, 'geo_source' => 'mapa'])->save();
        });
    }

    public function test_ficha_mostra_a_distancia_pelas_ruas_e_usa_ela_no_aviso_do_transporte(): void
    {
        config(['geo.router_url' => 'https://rotas.example.test/route/v1/foot']);
        Http::fake(['rotas.example.test/*' => Http::response(['code' => 'Ok', 'routes' => [['distance' => 2460.4, 'duration' => 1770]]])]);
        $this->marcarCasaEEscola();
        $this->user('school_secretary', [self::ESCOLA_A1]);

        $this->get($this->base().'/alunos/'.$this->student)
            ->assertOk()
            ->assertSeeInOrder(['Distância até a escola em linha reta', '1,7 km', 'Distância até a escola pelo mapa', '2,5 km', 'a pé, pelas ruas'])
            ->assertSee('Mora a mais de 2,0 km pelas ruas e não está no transporte');
        // A segunda abertura usa o cache: uma consulta só por casa.
        $this->get($this->base().'/alunos/'.$this->student)->assertOk()->assertSee('2,5 km');

        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://rotas.example.test/route/v1/foot/-46.600000,-23.485000;-46.600000,-23.500000'));
    }

    public function test_servico_de_rotas_fora_do_ar_cai_para_a_linha_reta_sem_derrubar_a_ficha(): void
    {
        config(['geo.router_url' => 'https://rotas.example.test/route/v1/foot']);
        Http::fake(['rotas.example.test/*' => Http::response('erro', 503)]);
        $this->marcarCasaEEscola();
        $this->user('school_secretary', [self::ESCOLA_A1]);

        $this->get($this->base().'/alunos/'.$this->student)
            ->assertOk()
            ->assertSee('1,7 km')
            ->assertSee('Não foi possível calcular agora')
            ->assertDontSee('Mora a mais de');
    }

    public function test_escola_salva_endereco_e_ponto_juntos_e_endereco_sem_ponto_volta_ao_mapa_procurando(): void
    {
        $this->user('school_director', [self::ESCOLA_A1]);
        $base = '/m/municipio-a/escolas/'.self::ESCOLA_A1;

        $this->get($base.'/localizacao')->assertOk()
            ->assertSee('Endereço da escola')->assertSee('Preencher pelo mapa')->assertSee('Salvar endereço e localização')
            ->assertSee('Abrir Google Maps')->assertSee('https://www.google.com/maps/search/?api=1&amp;query=', false);

        // Só o endereço, sem ponto: volta ao mapa já procurando o endereço salvo.
        $this->post($base.'/localizacao', [
            'postal_code' => '07500000', 'address_street' => 'Rua das Flores Fictícia', 'address_number' => '100',
            'address_district' => 'Centro', 'address_city' => 'Monte Sereno', 'address_state' => 'SP',
            'latitude' => '', 'longitude' => '', 'geo_source' => 'endereco',
        ])->assertRedirectContains('procurar=Rua');

        $this->post($base.'/localizacao', [
            'address_street' => 'Rua das Flores Fictícia', 'address_number' => '100', 'address_district' => 'Centro',
            'address_city' => 'Monte Sereno', 'address_state' => 'SP', 'postal_code' => '07500-000',
            'latitude' => '-23.300000', 'longitude' => '-46.200000', 'geo_source' => 'mapa',
        ])->assertRedirect($base);

        $this->get($base)->assertOk()->assertSee('Rua das Flores Fictícia 100, Centro, Monte Sereno/SP, 07500-000');
        // Com o ponto salvo, o Google Maps abre nas coordenadas da escola.
        $this->get($base.'/localizacao')->assertOk()->assertSee('query=-23.300000%2C-46.200000', false);
        // A casa do aluno não é enviada ao Google: o botão só existe na tela da escola.
        $this->get($this->base().'/alunos/'.$this->student.'/localizacao')->assertOk()->assertDontSee('Abrir Google Maps');
        $this->inMunicipality(function (): void {
            $school = School::query()->findOrFail(self::ESCOLA_A1);
            $this->assertSame('07500-000', $school->postal_code);
            $this->assertSame(-23.3, (float) $school->latitude);
        });

        $this->post($base.'/localizacao', ['address_state' => 'XX', 'latitude' => '-23.3', 'longitude' => '-46.2', 'geo_source' => 'mapa'])
            ->assertSessionHasErrors('address_state');
    }

    public function test_endereco_pelo_ponto_do_mapa_vem_do_geocodificador_reverso(): void
    {
        config(['geo.reverse_url' => 'https://geo.example.test/reverse']);
        Http::fake(['geo.example.test/*' => Http::response(['address' => [
            'road' => 'Rua Fictícia do Mapa', 'house_number' => '856', 'suburb' => 'Jardim Teste',
            'town' => 'Monte Sereno', 'ISO3166-2-lvl4' => 'BR-SP', 'postcode' => '07500000', 'country_code' => 'br',
        ]])]);
        $this->user('school_secretary', [self::ESCOLA_A1]);
        $url = '/m/municipio-a/escolas/'.self::ESCOLA_A1.'/localizacao/endereco';

        $this->getJson($url.'?lat=-23.3&lon=-46.2')->assertOk()->assertExactJson([
            'street' => 'Rua Fictícia do Mapa', 'number' => '856', 'district' => 'Jardim Teste',
            'city' => 'Monte Sereno', 'state' => 'SP', 'postal_code' => '07500-000',
        ]);
        $this->getJson($url.'?lat=10&lon=-46.2')->assertStatus(422);

        $this->user('teacher', [self::ESCOLA_A1]);
        $this->getJson($url.'?lat=-23.3&lon=-46.2')->assertForbidden();
    }

    public function test_endereco_do_cadastro_sem_a_rua_no_mapa_mostra_a_cidade_para_arrastar(): void
    {
        Http::fake(function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            // Só a busca pela cidade encontra: a rua e o bairro não estão no mapa.
            $cityOnly = ($query['city'] ?? null) === 'Santa Isabel' && ! isset($query['street']) && ! isset($query['q']);

            return Http::response($cityOnly ? [['lat' => '-23.317000', 'lon' => '-46.224000']] : []);
        });
        $this->user('school_director', [self::ESCOLA_A1]);

        $this->get($this->base().'/localizacao?procurar='.urlencode('Rua Fernandes Cardoso 240, 13 de Maio, Santa Isabel/SP, 07500-000'))
            ->assertOk()
            ->assertSee('mostrei a cidade')
            ->assertSee('data-lat="-23.317"', false);

        // O formato do cadastro ("Cidade/UF") foi lido em campos: rua com número e cidade separados.
        Http::assertSent(function ($request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['street'] ?? null) === '240 Rua Fernandes Cardoso' && ($query['state'] ?? null) === 'SP';
        });
    }
}
