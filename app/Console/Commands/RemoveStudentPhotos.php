<?php

namespace App\Console\Commands;

use App\Audit\AuditActor;
use App\Models\Platform\Municipality;
use App\Models\Tenant\Student;
use App\Models\User;
use App\Photos\IdentificationPhoto;
use App\Support\EmailNormalizer;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Remove a foto de identificação de todos os alunos ativos da rede, com
 * autoria na auditoria (a mesma da remoção pela ficha), e apaga os arquivos
 * que ficaram sem cadastro na pasta de fotos de alunos. Pensado para trocar
 * as fotos do ambiente fictício; aluno arquivado mantém o histórico.
 */
class RemoveStudentPhotos extends Command
{
    protected $signature = 'astuti:alunos-remover-fotos
        {--confirmar= : Digite REMOVER-FOTOS-DOS-ALUNOS para remover}
        {--responsavel= : E-mail ou usuário de quem autoriza (fica na auditoria)}';

    protected $description = 'Lista e, com confirmação, remove as fotos de identificação de todos os alunos ativos.';

    private const CONFIRMATION = 'REMOVER-FOTOS-DOS-ALUNOS';

    public function handle(TenantContext $context, AuditActor $actor, IdentificationPhoto $photos): int
    {
        $apply = $this->option('confirmar') !== null;
        if ($apply && $this->option('confirmar') !== self::CONFIRMATION) {
            $this->error('Confirmação diferente de '.self::CONFIRMATION.'. Nada foi alterado.');

            return self::INVALID;
        }
        $user = null;
        if ($apply) {
            $user = User::query()->where('email', EmailNormalizer::fromLogin((string) $this->option('responsavel')))->where('status', 'active')->first();
            if ($user === null) {
                $this->error('Informe --responsavel com o e-mail ou usuário de uma conta ativa. Nada foi alterado.');

                return self::INVALID;
            }
        }

        $configured = array_keys((array) config('tenancy.connections', []));
        foreach (Municipality::query()->where('status', 'active')->whereIn('db_key', $configured)->orderBy('legal_name')->get() as $municipality) {
            $context->runFor($municipality, function () use ($municipality, $actor, $photos, $apply, $user): void {
                $students = Student::query()->whereNotNull('photo_path')->orderBy('full_name')->get();
                $archived = Student::query()->onlyTrashed()->whereNotNull('photo_path')->count();
                $folder = 'fotos/'.$municipality->slug.'/alunos';
                $this->line('');
                $this->info($municipality->legal_name.': '.$students->count().' aluno(s) ativo(s) com foto.');
                if ($archived > 0) {
                    $this->line("  {$archived} aluno(s) arquivado(s) com foto ficam como estão (histórico).");
                }
                if (! $apply) {
                    return;
                }

                $removed = 0;
                foreach ($students as $student) {
                    try {
                        $actor->runAs($user->id, function () use ($student, $photos): void {
                            $old = $student->photo_path;
                            $student->forceFill(['photo_path' => null, 'photo_updated_at' => null])->save();
                            $photos->delete($old);
                        });
                        $removed++;
                    } catch (Throwable $exception) {
                        report($exception);
                        $this->warn('  - '.substr($student->id, 0, 8).': não foi possível remover; mantida.');
                    }
                }

                // Arquivos que nenhum cadastro aponta (sobras de trocas antigas). Os de aluno arquivado ficam.
                $kept = Student::query()->withTrashed()->whereNotNull('photo_path')->pluck('photo_path')->all();
                $orphans = array_values(array_diff(Storage::disk(IdentificationPhoto::DISK)->files($folder), $kept));
                foreach ($orphans as $file) {
                    $photos->delete($file);
                }
                $this->info("  {$removed} foto(s) removida(s), com autoria na auditoria; ".count($orphans).' arquivo(s) sem cadastro apagado(s).');
            });
        }

        if (! $apply) {
            $this->line('');
            $this->info('Nada foi alterado. Para remover: php artisan astuti:alunos-remover-fotos --confirmar='.self::CONFIRMATION.' --responsavel=admin');
        }

        return self::SUCCESS;
    }
}
