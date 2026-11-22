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

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'countrycodes=br') && str_contains(urldecode($request->url()), 'Rua Fictícia 100'));
        $this->assertNull($this->inMunicipality(fn () => Student::query()->findOrFail($this->student)->latitude));
    }

    public function test_salvar_mostra_distancia_e_aviso_do_transporte_com_auditoria_mascarada(): void
    {
        $this->user('school_secretary', [self::ESCOLA_A1]);

        $this->post($this->base().'/localizacao', ['latitude' => '-23.500000', 'longitude' => '-46.600000', 'geo_source' => 'mapa'])
            ->assertRedirect($this->base())->assertSessionHas('status', 'Localização da escola salva.');
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

    public function test_servico_de_mapa_fora_do_ar_nao_derruba_a_tela(): void
    {
        Http::fake(['geocoder.example.test/*' => Http::response('erro', 503)]);
        $this->user('school_secretary', [self::ESCOLA_A1]);

        $this->get($this->base().'/alunos/'.$this->student.'/localizacao?procurar='.urlencode('Rua Inexistente'))
            ->assertOk()
            ->assertSee('Não encontrei "Rua Inexistente" no mapa', false);
    }
}
