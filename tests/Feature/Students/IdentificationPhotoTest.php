<?php

namespace Tests\Feature\Students;

use App\Models\Tenant\AuditLog;
use App\Models\Tenant\Guardian;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\StudentGuardian;
use App\Models\User;
use App\Security\Totp;
use App\Support\UploadLimit;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Support\TwoMunicipalityTestCase;

class IdentificationPhotoTest extends TwoMunicipalityTestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private string $student;

    private string $otherSchoolStudent;

    private string $guardian;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        [$this->student, $this->otherSchoolStudent, $this->guardian] = $this->inMunicipality(function (): array {
            $year = SchoolYear::query()->create(['id' => (string) Str::uuid(), 'year' => 2027, 'start_date' => '2027-02-01', 'end_date' => '2027-12-18', 'status' => 'active']);
            $ids = [];
            foreach ([self::ESCOLA_A1, self::ESCOLA_A2] as $index => $school) {
                $student = Student::query()->create(['id' => (string) Str::uuid(), 'full_name' => 'Aluno Fictício da Foto '.($index + 1)]);
                StudentEnrollment::query()->create(['id' => (string) Str::uuid(), 'student_id' => $student->id, 'school_id' => $school, 'school_year_id' => $year->id, 'status' => 'active']);
                $ids[] = $student->id;
            }
            $guardian = Guardian::query()->create(['id' => (string) Str::uuid(), 'full_name' => 'Responsável Fictício da Foto', 'phone' => '11987654321']);
            StudentGuardian::query()->create(['id' => (string) Str::uuid(), 'student_id' => $ids[0], 'guardian_id' => $guardian->id, 'relationship' => 'mother', 'is_primary_contact' => true]);
            $ids[] = $guardian->id;

            return $ids;
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

    private function url(string $tipo, string $id, string $school = self::ESCOLA_A1): string
    {
        return '/m/municipio-a/escolas/'.$school.'/fotos/'.$tipo.'/'.$id;
    }

    /** Foto JPEG fictícia, retangular, com texto escondido nos metadados. */
    private function photo(int $width = 800, int $height = 600): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 30, 120, 200));
        ob_start();
        imagejpeg($image);
        $jpeg = (string) ob_get_clean();
        // Comentário JPEG (COM) logo após o SOI, como faria um celular com dados extras.
        $comment = 'GPS-FICTICIO-SEGREDO';
        $jpeg = substr($jpeg, 0, 2)."\xFF\xFE".pack('n', strlen($comment) + 2).$comment.substr($jpeg, 2);

        return UploadedFile::fake()->createWithContent('foto.jpg', $jpeg);
    }

    public function test_secretaria_envia_foto_do_aluno_que_vira_tres_por_quatro_sem_metadados_e_fica_privada(): void
    {
        $this->user('school_secretary', [self::ESCOLA_A1]);

        $this->from('/ficha')->post($this->url('aluno', $this->student), ['foto' => $this->photo()])
            ->assertRedirect('/ficha')->assertSessionHas('status', 'Foto adicionada.');

        [$path, $updatedAt] = $this->inMunicipality(fn (): array => [Student::query()->findOrFail($this->student)->photo_path, Student::query()->findOrFail($this->student)->photo_updated_at]);
        $this->assertStringStartsWith('fotos/municipio-a/alunos/', $path);
        $this->assertNotNull($updatedAt);

        $saved = Storage::disk('local')->get($path);
        $this->assertSame([360, 480], array_slice(getimagesizefromstring($saved), 0, 2));
        $this->assertStringNotContainsString('GPS-FICTICIO-SEGREDO', $saved);

        $this->get($this->url('aluno', $this->student))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');

        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos/'.$this->student)
            ->assertOk()
            ->assertSee($this->url('aluno', $this->student), false)
            ->assertSee('Trocar foto');

        $after = $this->inMunicipality(fn (): array => AuditLog::query()->where('auditable_type', Student::class)->where('auditable_id', $this->student)->where('action', 'updated')->sole()->after);
        $this->assertArrayHasKey('photo_updated_at', $after);
        $this->assertArrayNotHasKey('photo_path', $after);
    }

    public function test_trocar_e_remover_apaga_o_arquivo_anterior_e_audita_o_responsavel(): void
    {
        $this->user('school_secretary', [self::ESCOLA_A1]);

        $this->post($this->url('responsavel', $this->guardian), ['foto' => $this->photo()])->assertRedirect();
        $first = $this->inMunicipality(fn (): string => Guardian::query()->findOrFail($this->guardian)->photo_path);
        $this->post($this->url('responsavel', $this->guardian), ['foto' => $this->photo(600, 900)])->assertSessionHas('status', 'Foto atualizada.');
        $second = $this->inMunicipality(fn (): string => Guardian::query()->findOrFail($this->guardian)->photo_path);

        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);

        $this->post($this->url('responsavel', $this->guardian).'/remover')->assertSessionHas('status', 'Foto removida.');
        Storage::disk('local')->assertMissing($second);
        $this->assertNull($this->inMunicipality(fn (): ?string => Guardian::query()->findOrFail($this->guardian)->photo_path));

        $actions = $this->inMunicipality(fn (): array => AuditLog::query()->where('auditable_type', Guardian::class)->orderBy('occurred_at')->pluck('action')->all());
        $this->assertSame(['photo.updated', 'photo.updated', 'photo.removed'], $actions);
    }

    public function test_substituicao_do_aluno_rele_foto_atual_e_remove_arquivo_sem_vinculo(): void
    {
        $this->user('school_secretary', [self::ESCOLA_A1]);
        $url = $this->url('aluno', $this->student);
        $this->post($url, ['foto' => $this->photo()])->assertRedirect();
        $first = $this->inMunicipality(fn (): string => Student::query()->findOrFail($this->student)->photo_path);
        $intermediate = dirname($first).'/'.Str::uuid().'.jpg';
        Storage::disk('local')->put($intermediate, Storage::disk('local')->get($first));

        // Simula uma troca já confirmada entre a leitura inicial e a gravação.
        // Não é ensaio com duas conexões simultâneas: comprova a releitura.
        $changed = false;
        Student::retrieved(function (Student $student) use (&$changed, $intermediate, $first): void {
            if (! $changed && $student->id === $this->student) {
                $changed = true;
                DB::connection('tenant')->table('students')->where('id', $student->id)->update(['photo_path' => $intermediate]);
                Storage::disk('local')->delete($first);
            }
        });
        $this->post($url, ['foto' => $this->photo(600, 900)])->assertSessionHas('status', 'Foto atualizada.');
        $this->assertTrue($changed);
        $last = $this->inMunicipality(fn (): string => Student::query()->findOrFail($this->student)->photo_path);
        $this->assertNotSame($intermediate, $last);
        Storage::disk('local')->assertMissing([$first, $intermediate]);
        $this->assertSame([$last], Storage::disk('local')->allFiles(dirname($last)));
        $this->get($url)->assertOk()->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->post($url.'/remover')->assertSessionHas('status', 'Foto removida.');
        $this->assertSame([], Storage::disk('local')->allFiles(dirname($last)));
        $this->assertNull($this->inMunicipality(fn (): ?string => Student::query()->findOrFail($this->student)->photo_path));
        $this->get($url)->assertNotFound();
    }

    public function test_falha_de_gravacao_preserva_foto_anterior_e_nao_deixa_arquivo_novo(): void
    {
        $this->user('school_secretary', [self::ESCOLA_A1]);
        $url = $this->url('aluno', $this->student);
        $this->post($url, ['foto' => $this->photo()])->assertRedirect();
        [$first, $auditCount] = $this->inMunicipality(fn (): array => [
            Student::query()->findOrFail($this->student)->photo_path,
            AuditLog::query()->where('auditable_id', $this->student)->count(),
        ]);
        Student::updated(function (Student $student): void {
            if ($student->id === $this->student && $student->wasChanged('photo_path')) {
                throw new RuntimeException('Falha fictícia após gravação da foto');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->post($url, ['foto' => $this->photo(600, 900)]);
            $this->fail('A falha fictícia deveria interromper a gravação.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Falha fictícia após gravação da foto', $exception->getMessage());
        }
        $this->assertSame([$first], Storage::disk('local')->allFiles(dirname($first)));
        $this->inMunicipality(function () use ($first, $auditCount): void {
            $this->assertSame($first, Student::query()->findOrFail($this->student)->photo_path);
            $this->assertSame($auditCount, AuditLog::query()->where('auditable_id', $this->student)->count());
        });
        $this->get($url)->assertOk();
    }

    public function test_arquivo_que_nao_e_imagem_e_foto_pequena_sao_recusados(): void
    {
        $this->user('school_secretary', [self::ESCOLA_A1]);

        $this->post($this->url('aluno', $this->student), ['foto' => UploadedFile::fake()->createWithContent('foto.jpg', 'isto não é imagem')])
            ->assertSessionHasErrors('foto');
        $this->post($this->url('aluno', $this->student), ['foto' => $this->photo(80, 80)])
            ->assertSessionHasErrors(['foto' => 'A foto está muito pequena. Use uma imagem com pelo menos 120 pixels de largura e altura.']);
        $this->post($this->url('aluno', $this->student), ['foto' => UploadedFile::fake()->create('documento.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors(['foto' => 'Envie uma foto JPG, PNG ou WebP.']);

        $this->assertNull($this->inMunicipality(fn (): ?string => Student::query()->findOrFail($this->student)->photo_path));
    }

    public function test_professor_ve_a_foto_mas_nao_altera_e_outra_escola_nao_alcanca(): void
    {
        $this->user('school_secretary', [self::ESCOLA_A1]);
        $this->post($this->url('aluno', $this->student), ['foto' => $this->photo()])->assertRedirect();

        $this->user('teacher', [self::ESCOLA_A1]);
        $this->get($this->url('aluno', $this->student))->assertOk();
        $this->post($this->url('aluno', $this->student), ['foto' => $this->photo()])->assertForbidden();
        $this->post($this->url('aluno', $this->student).'/remover')->assertForbidden();

        // Aluno de outra escola e responsável sem vínculo com a escola: 404, sem revelar nada.
        $this->user('school_secretary', [self::ESCOLA_A1, self::ESCOLA_A2]);
        $this->get($this->url('aluno', $this->otherSchoolStudent))->assertNotFound();
        $this->get($this->url('aluno', $this->student, self::ESCOLA_A2))->assertNotFound();
        $this->get($this->url('responsavel', $this->guardian, self::ESCOLA_A2))->assertNotFound();

        $this->user('school_secretary', [self::ESCOLA_A2]);
        $this->get($this->url('aluno', $this->student))->assertForbidden();
    }

    public function test_foto_acima_do_limite_do_servidor_explica_o_motivo_e_png_grande_e_aceito(): void
    {
        $this->user('school_secretary', [self::ESCOLA_A1]);

        // O PHP descarta o arquivo acima de upload_max_filesize e marca o erro de tamanho.
        $temp = tempnam(sys_get_temp_dir(), 'foto');
        $rejected = new UploadedFile($temp, 'menina.png', 'image/png', UPLOAD_ERR_INI_SIZE, true);
        $this->from('/ficha')->post($this->url('aluno', $this->student), ['foto' => $rejected])
            ->assertRedirect('/ficha')
            ->assertSessionHasErrors(['foto' => 'A foto é maior que o limite de envio deste servidor ('.UploadLimit::label().'). Escolha uma foto menor ou recorte a imagem.']);

        $partial = new UploadedFile($temp, 'menina.png', 'image/png', UPLOAD_ERR_PARTIAL, true);
        $this->post($this->url('aluno', $this->student), ['foto' => $partial])
            ->assertSessionHasErrors(['foto' => 'A foto não chegou completa. Tente enviar de novo.']);

        // PNG quadrado grande, como os gerados por IA, entra normalmente.
        $image = imagecreatetruecolor(1254, 1254);
        imagefill($image, 0, 0, imagecolorallocate($image, 220, 90, 80));
        ob_start();
        imagepng($image);
        $png = UploadedFile::fake()->createWithContent('menina.png', (string) ob_get_clean());
        $this->post($this->url('aluno', $this->student), ['foto' => $png])->assertSessionHas('status', 'Foto adicionada.');
    }

    public function test_ficha_reduz_a_foto_no_navegador_e_envio_grande_demais_mostra_pagina_clara(): void
    {
        $this->user('school_secretary', [self::ESCOLA_A1]);

        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos/'.$this->student)
            ->assertOk()
            ->assertSee('data-foto-reduzir data-limite-bytes="'.UploadLimit::bytes().'"', false)
            ->assertSee('js/foto-identificacao.js', false)
            ->assertSee('class="foto-id-situacao" aria-live="polite"', false);

        $this->call('POST', $this->url('aluno', $this->student), [], [], [], ['CONTENT_LENGTH' => (string) (UploadLimit::iniBytes((string) ini_get('post_max_size')) + 1)])
            ->assertStatus(413)
            ->assertSee('Arquivo grande demais')
            ->assertSee('escolha um arquivo menor ou recorte a imagem');
    }

    public function test_foto_do_aluno_com_mais_de_um_ano_pede_atualizacao(): void
    {
        $this->user('school_secretary', [self::ESCOLA_A1]);
        $this->post($this->url('aluno', $this->student), ['foto' => $this->photo()])->assertRedirect();
        $this->inMunicipality(function (): void {
            $student = Student::query()->findOrFail($this->student);
            $student->photo_updated_at = now()->subMonths(13);
            $student->save();
        });

        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos/'.$this->student)
            ->assertOk()
            ->assertSee('vale atualizar');
    }
}
