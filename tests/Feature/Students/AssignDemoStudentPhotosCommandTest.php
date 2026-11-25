<?php

namespace Tests\Feature\Students;

use App\Models\Tenant\Student;
use App\Models\Tenant\StudentSpecialEducation;
use App\Photos\IdentificationPhoto;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;
use ZipArchive;

class AssignDemoStudentPhotosCommandTest extends TwoMunicipalityTestCase
{
    /** @var array<string, string> */
    private array $ids = [];

    private string $zip;

    protected function setUp(): void
    {
        parent::setUp();
        if (! class_exists(ZipArchive::class) || ! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('Extensões zip ou GD indisponíveis.');
        }
        Storage::fake(IdentificationPhoto::DISK);
        $this->ids = $this->tenant(function (): array {
            $make = function (string $name, ?string $sex, ?string $photo = null): string {
                $student = new Student;
                $student->forceFill(['id' => (string) Str::uuid(), 'full_name' => $name, 'sex' => $sex, 'photo_path' => $photo])->save();

                return $student->id;
            };
            $ids = [
                'ana' => $make('Ana Fictícia Atípica', 'female'),
                'bia' => $make('Bia Fictícia Típica', 'female'),
                'caio' => $make('Caio Fictício Típico', 'male'),
                'davi' => $make('Davi Fictício Típico', 'male'),
                'eva' => $make('Eva Fictícia Com Foto', 'female', 'fotos/municipio-a/alunos/antiga.jpg'),
            ];
            StudentSpecialEducation::query()->create(['student_id' => $ids['ana'], 'conditions' => ['tea']]);

            return $ids;
        });

        // Zip como o enviado pelo Jefferson: pastas de típicas e atípicas, nomes com e sem descrição.
        $this->zip = tempnam(sys_get_temp_dir(), 'fotos').'.zip';
        $zip = new ZipArchive;
        $zip->open($this->zip, ZipArchive::CREATE);
        foreach ([
            'Crianças Atípicas/1 - fotos/menina_02_cadeirante.png',
            'Crianças Atípicas/1 - fotos/menina_05_tea.png',
            'Crianças Atípicas/2 - fotos/Imagem do ChatGPT 24 de nov. de 2026, 18_12_15-2.png',
            'Crianças Típicas/1 - fotos/menina_01.png',
            'Crianças Típicas/2 - fotos/Imagem do ChatGPT 24 de nov. de 2026, 17_43_25-1.png',
        ] as $name) {
            $image = imagecreatetruecolor(200, 240);
            ob_start();
            imagepng($image);
            $zip->addFromString($name, (string) ob_get_clean());
        }
        $zip->close();
    }

    protected function tearDown(): void
    {
        @unlink($this->zip);
        parent::tearDown();
    }

    private function tenant(callable $callback): mixed
    {
        return app(TenantContext::class)->runFor($this->municipioA, $callback);
    }

    public function test_distribui_por_sexo_e_educacao_especial_e_avisa_o_que_falta(): void
    {
        $this->artisan('astuti:demo-fotos-alunos', ['arquivo' => $this->zip])
            ->expectsOutputToContain('5 foto(s) no arquivo: 1 menina(s) e 1 menino(s) típicos; 2 menina(s) e 1 menino(s) atípicos.')
            ->expectsOutputToContain('3 aluno(s) sem foto receberão uma.')
            ->expectsOutputToContain('Faltam 1 foto(s) de menino típico.')
            ->expectsOutputToContain('Nada foi alterado.')
            ->assertSuccessful();
        $this->assertSame(0, $this->tenant(fn () => Student::query()->whereNotNull('photo_path')->where('id', '<>', $this->ids['eva'])->count()));

        $this->artisan('astuti:demo-fotos-alunos', ['arquivo' => $this->zip, '--confirmar' => 'SIM', '--responsavel' => 'x'])->assertExitCode(2);
        $this->artisan('astuti:demo-fotos-alunos', ['arquivo' => $this->zip, '--confirmar' => 'COLOCAR-FOTOS-FICTICIAS', '--responsavel' => $this->auditFixtureUser->email])
            ->expectsOutputToContain('3 foto(s) gravada(s), com autoria na auditoria.')
            ->assertSuccessful();

        $fotos = $this->tenant(fn () => Student::query()->pluck('photo_path', 'id')->all());
        foreach (['ana', 'bia', 'caio'] as $chave) {
            $this->assertNotNull($fotos[$this->ids[$chave]]);
            $this->assertTrue(Storage::disk(IdentificationPhoto::DISK)->exists($fotos[$this->ids[$chave]]));
        }
        // Menino típico sem foto disponível não recebe a de menino atípico.
        $this->assertNull($fotos[$this->ids['davi']]);
        $this->assertSame('fotos/municipio-a/alunos/antiga.jpg', $fotos[$this->ids['eva']]);
        // Rodar de novo não troca quem já tem foto.
        $this->artisan('astuti:demo-fotos-alunos', ['arquivo' => $this->zip])->expectsOutputToContain('0 aluno(s) sem foto receberão uma.')->assertSuccessful();
    }
}
