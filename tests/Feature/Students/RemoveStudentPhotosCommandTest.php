<?php

namespace Tests\Feature\Students;

use App\Models\Tenant\AuditLog;
use App\Models\Tenant\Guardian;
use App\Models\Tenant\Student;
use App\Photos\IdentificationPhoto;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class RemoveStudentPhotosCommandTest extends TwoMunicipalityTestCase
{
    /** @var array<string, string> */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(IdentificationPhoto::DISK);
        $disk = Storage::disk(IdentificationPhoto::DISK);
        $this->ids = $this->tenant(function () use ($disk): array {
            $comFoto = new Student;
            $comFoto->forceFill(['id' => (string) Str::uuid(), 'full_name' => 'Ana Fictícia Foto', 'photo_path' => 'fotos/municipio-a/alunos/ana.jpg', 'photo_updated_at' => now()])->save();
            $semFoto = new Student;
            $semFoto->forceFill(['id' => (string) Str::uuid(), 'full_name' => 'Bia Fictícia Sem Foto'])->save();
            $responsavel = Guardian::query()->create(['id' => (string) Str::uuid(), 'full_name' => 'Mãe Fictícia', 'phone' => '11987654321']);
            $responsavel->forceFill(['photo_path' => 'fotos/municipio-a/responsaveis/mae.jpg'])->save();
            foreach (['alunos/ana.jpg', 'alunos/sobra-antiga.jpg', 'responsaveis/mae.jpg'] as $arquivo) {
                $disk->put('fotos/municipio-a/'.$arquivo, 'jpeg');
            }

            return ['com_foto' => $comFoto->id, 'responsavel' => $responsavel->id];
        });
    }

    private function tenant(callable $callback): mixed
    {
        return app(TenantContext::class)->runFor($this->municipioA, $callback);
    }

    public function test_lista_sem_alterar_e_remove_com_confirmacao_e_auditoria(): void
    {
        $disk = Storage::disk(IdentificationPhoto::DISK);

        $this->artisan('astuti:alunos-remover-fotos')
            ->expectsOutputToContain('1 aluno(s) ativo(s) com foto.')
            ->expectsOutputToContain('Nada foi alterado.')
            ->assertSuccessful();
        $this->assertTrue($disk->exists('fotos/municipio-a/alunos/ana.jpg'));

        $this->artisan('astuti:alunos-remover-fotos', ['--confirmar' => 'SIM', '--responsavel' => 'x'])->assertExitCode(2);
        $this->artisan('astuti:alunos-remover-fotos', ['--confirmar' => 'REMOVER-FOTOS-DOS-ALUNOS', '--responsavel' => 'ninguem@example.invalid'])->assertExitCode(2);
        $this->assertNotNull($this->tenant(fn () => Student::query()->findOrFail($this->ids['com_foto'])->photo_path));

        $this->artisan('astuti:alunos-remover-fotos', ['--confirmar' => 'REMOVER-FOTOS-DOS-ALUNOS', '--responsavel' => $this->auditFixtureUser->email])
            ->expectsOutputToContain('1 foto(s) removida(s), com autoria na auditoria; 1 arquivo(s) sem cadastro apagado(s).')
            ->assertSuccessful();

        $aluno = $this->tenant(fn () => Student::query()->findOrFail($this->ids['com_foto']));
        $this->assertNull($aluno->photo_path);
        $this->assertNull($aluno->photo_updated_at);
        $this->assertFalse($disk->exists('fotos/municipio-a/alunos/ana.jpg'));
        $this->assertFalse($disk->exists('fotos/municipio-a/alunos/sobra-antiga.jpg'));
        // Responsáveis e autorizados não são tocados.
        $this->assertTrue($disk->exists('fotos/municipio-a/responsaveis/mae.jpg'));
        $this->assertSame('fotos/municipio-a/responsaveis/mae.jpg', $this->tenant(fn () => Guardian::query()->findOrFail($this->ids['responsavel'])->photo_path));

        $log = $this->tenant(fn () => AuditLog::query()->where('auditable_id', $this->ids['com_foto'])->where('action', 'like', '%updated%')->latest('occurred_at')->first());
        $this->assertNotNull($log);
        $this->assertSame($this->auditFixtureUser->id, $log->user_id);
    }
}
