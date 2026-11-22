<?php

namespace Tests\Feature\Import;

use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\User;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

/**
 * Tela de importacao recebendo PDF e foto: a leitura local entra na mesma
 * conferencia das planilhas, e leitura duvidosa so grava com marcacao humana.
 */
class OcrStudentImportScreenTest extends TwoMunicipalityTestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private const ESCOLA_EMEI = '44444444-4444-4444-8444-4444444444e1';

    private const KIT = __DIR__.'/../../Fixtures/kit-monte-sereno/';

    private string $yearId;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['tesseract', 'pdftoppm', 'pdftotext', 'pdfinfo'] as $binary) {
            if (trim((string) shell_exec('command -v '.$binary)) === '') {
                if (getenv('GITHUB_ACTIONS') === 'true') {
                    $this->fail("O CI precisa do programa {$binary} para testar o OCR.");
                }
                $this->markTestSkipped("Programa {$binary} ausente neste computador.");
            }
        }

        $this->yearId = app(TenantContext::class)->runFor($this->municipioA, function (): string {
            School::query()->create(['id' => self::ESCOLA_EMEI, 'name' => 'EMEI Fictícia', 'status' => 'active']);

            return SchoolYear::query()->create([
                'id' => (string) Str::uuid(), 'year' => 2027, 'start_date' => '2027-02-01', 'end_date' => '2027-12-18', 'status' => 'active',
            ])->id;
        });
    }

    private function turmas(string $schoolId, array $names): void
    {
        app(TenantContext::class)->runFor($this->municipioA, function () use ($schoolId, $names): void {
            foreach ($names as $name => $shift) {
                SchoolClass::query()->create([
                    'id' => (string) Str::uuid(), 'school_id' => $schoolId, 'school_year_id' => $this->yearId,
                    'name' => $name, 'stage' => 'Fictícia', 'modality' => 'Regular', 'shift' => $shift, 'status' => 'active',
                ]);
            }
        });
    }

    private function entrar(array $schools): User
    {
        $user = $this->criarUsuario(Str::random(12).'@example.invalid');
        $this->vincular($user, $this->municipioA, 'school_secretary', $schools);
        $user->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', [
            'code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp()),
        ])->assertRedirect();

        return $user;
    }

    private function url(string $schoolId): string
    {
        return '/m/municipio-a/escolas/'.$schoolId.'/importacoes/alunos';
    }

    /** @return array{string, string} token do lote e HTML da conferencia */
    private function enviar(string $schoolId, string $file): array
    {
        $response = $this->post($this->url($schoolId).'/previa', [
            'arquivo' => new UploadedFile(self::KIT.'ocr/'.$file, $file, null, null, true),
            'school_year_id' => $this->yearId,
        ]);
        $response->assertOk()->assertSee('Conferir antes de importar');
        $html = $response->getContent();
        $this->assertSame(1, preg_match('/name="lote" value="([^"]+)"/', $html, $matches));

        return [$matches[1], $html];
    }

    /** @return list<int> */
    private function indices(string $html, string $field): array
    {
        preg_match_all('/name="'.$field.'\[\]" value="(\d+)"/', $html, $found);

        return array_map('intval', $found[1]);
    }

    private function alunos(): int
    {
        return app(TenantContext::class)->runFor($this->municipioA, fn (): int => Student::query()->count());
    }

    public function test_pdf_digital_grava_as_40_criancas_sem_conferencia_de_leitura(): void
    {
        $this->turmas(self::ESCOLA_EMEI, ['MII-A' => 'Integral', 'PI-A' => 'Manhã', 'PII-A' => 'Tarde']);
        $this->entrar([self::ESCOLA_EMEI]);

        $envio = $this->get($this->url(self::ESCOLA_EMEI))->assertOk()->getContent();
        $this->assertSame(1, substr_count($envio, 'Voltar à tela anterior'), 'Um botao voltar so.');

        [$token, $html] = $this->enviar(self::ESCOLA_EMEI, 'lista_digital_E01.pdf');
        $this->assertSame([], $this->indices($html, 'leituras'));
        $this->assertSame(1, substr_count($html, 'Voltar à tela anterior'), 'Um botao voltar so na conferencia.');

        $this->post($this->url(self::ESCOLA_EMEI).'/confirmar', ['lote' => $token])->assertRedirect();
        $this->assertSame(40, $this->alunos());
    }

    public function test_lista_escaneada_so_grava_depois_de_conferir_cada_leitura_duvidosa(): void
    {
        $this->turmas(self::ESCOLA_A1, ['1A' => 'Manhã', '2A' => 'Manhã', '3A' => 'Manhã', '4A' => 'Tarde', '5A' => 'Tarde']);
        $this->entrar([self::ESCOLA_A1]);

        [$token, $html] = $this->enviar(self::ESCOLA_A1, 'lista_impressa_E02.pdf');
        $leituras = $this->indices($html, 'leituras');
        $this->assertNotEmpty($leituras);
        $this->assertStringContainsString('Conferi nome, data e RA com o documento.', $html);

        // Sem marcar as leituras duvidosas, nada e gravado.
        $this->post($this->url(self::ESCOLA_A1).'/confirmar', ['lote' => $token])->assertSessionHasErrors('leituras');
        $this->post($this->url(self::ESCOLA_A1).'/confirmar', ['lote' => $token, 'leituras' => array_slice($leituras, 1)])->assertSessionHasErrors('leituras');
        $this->assertSame(0, $this->alunos());
    }

    public function test_foto_da_lista_entra_na_mesma_conferencia(): void
    {
        $this->turmas(self::ESCOLA_A2, ['MULTI-1' => 'Manhã', 'MULTI-2' => 'Tarde']);
        $this->entrar([self::ESCOLA_A2]);

        [, $html] = $this->enviar(self::ESCOLA_A2, 'foto_lista_E03.jpg');
        $this->assertStringContainsString('Bernardo Soares Campos', $html);
        $this->assertSame(0, $this->alunos(), 'A previa nao grava nada.');
    }

    public function test_recusa_na_conferencia_volta_para_a_pagina_de_envio_e_nao_para_a_previa(): void
    {
        $this->turmas(self::ESCOLA_A1, ['1A' => 'Manhã', '2A' => 'Manhã', '3A' => 'Manhã', '4A' => 'Tarde', '5A' => 'Tarde']);
        $this->entrar([self::ESCOLA_A1]);
        $html = $this->post($this->url(self::ESCOLA_A1).'/previa', [
            'arquivo' => new UploadedFile(self::KIT.'E02_EMEF_Sebastiao_alunos_2027.xlsx', 'E02.xlsx', null, null, true),
            'school_year_id' => $this->yearId,
        ])->assertOk()->getContent();
        $this->assertSame(1, preg_match('/name="lote" value="([^"]+)"/', $html, $m));

        // Sem confirmar as repeticoes: antes, a recusa voltava para GET /previa (405).
        $this->from($this->url(self::ESCOLA_A1).'/previa')
            ->post($this->url(self::ESCOLA_A1).'/confirmar', ['lote' => $m[1]])
            ->assertRedirect($this->url(self::ESCOLA_A1))
            ->assertSessionHasErrors('confirmados');

        // Lote adulterado tambem volta para a pagina de envio.
        $this->from($this->url(self::ESCOLA_A1).'/previa')
            ->post($this->url(self::ESCOLA_A1).'/confirmar', ['lote' => 'adulterado'])
            ->assertRedirect($this->url(self::ESCOLA_A1))
            ->assertSessionHasErrors('lote');

        // Seguindo o redirecionamento, a pessoa ve a pagina de envio com a mensagem.
        $this->followingRedirects()->from($this->url(self::ESCOLA_A1).'/previa')
            ->post($this->url(self::ESCOLA_A1).'/confirmar', ['lote' => $m[1]])
            ->assertOk()
            ->assertSee('Não foi possível concluir.')
            ->assertSee('Confirme explicitamente cada repetição de aluno antes de gravar.');
        $this->get($this->url(self::ESCOLA_A1).'/previa')->assertStatus(405);
    }

    public function test_arquivo_com_extensao_trocada_e_recusado(): void
    {
        $this->turmas(self::ESCOLA_A1, ['1A' => 'Manhã']);
        $this->entrar([self::ESCOLA_A1]);

        $falso = UploadedFile::fake()->createWithContent('lista.pdf', 'texto qualquer');
        $this->post($this->url(self::ESCOLA_A1).'/previa', ['arquivo' => $falso, 'school_year_id' => $this->yearId])
            ->assertSessionHasErrors(['arquivo' => 'Envie a planilha (.xlsx), um PDF ou uma foto da lista (JPG ou PNG).']);

        $foto = UploadedFile::fake()->createWithContent('lista.pdf', (string) file_get_contents(self::KIT.'ocr/foto_lista_E03.jpg'));
        $this->post($this->url(self::ESCOLA_A1).'/previa', ['arquivo' => $foto, 'school_year_id' => $this->yearId])
            ->assertSessionHasErrors('arquivo');
    }
}
