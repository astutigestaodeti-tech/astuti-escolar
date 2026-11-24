<?php

namespace App\Console\Commands;

use App\Audit\AuditActor;
use App\Models\Platform\Municipality;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\User;
use App\Services\EnrollmentMovementService;
use App\Students\DuplicateStudents;
use App\Support\EmailNormalizer;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Throwable;

/**
 * Encontra alunos cadastrados mais de uma vez (mesmo nome e nascimento igual ou em branco)
 * e arquiva a cópia que não tem nenhum registro próprio, com motivo e autoria na auditoria.
 * Cópias com chamada, notas, documentos, responsáveis ou outro vínculo ficam para a
 * secretaria decidir na ficha: nada é fundido nem apagado fisicamente.
 */
class RemoveDuplicateStudents extends Command
{
    protected $signature = 'astuti:alunos-repetidos
        {--confirmar= : Digite REMOVER-REPETIDOS para arquivar as cópias sem registros próprios}
        {--responsavel= : E-mail ou usuário de quem autoriza (fica na auditoria)}';

    protected $description = 'Lista e, com confirmação, arquiva cadastros de alunos repetidos (exclusão lógica auditada).';

    private const REASON = 'Cadastro repetido do mesmo aluno (mesmo nome e nascimento compatível), sem registros próprios. Arquivado pela conferência de cadastros repetidos.';

    public function handle(TenantContext $context, DuplicateStudents $duplicates, EnrollmentMovementService $movement, AuditActor $actor): int
    {
        $apply = $this->option('confirmar') !== null;
        if ($apply && $this->option('confirmar') !== 'REMOVER-REPETIDOS') {
            $this->error('Confirmação diferente de REMOVER-REPETIDOS. Nada foi alterado.');

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

        $total = 0;
        // Só as redes com banco configurado nesta instalação (na Prefeitura, uma só).
        $configured = array_keys((array) config('tenancy.connections', []));
        foreach (Municipality::query()->where('status', 'active')->whereIn('db_key', $configured)->orderBy('legal_name')->get() as $municipality) {
            $context->runFor($municipality, function () use ($municipality, $duplicates, $movement, $actor, $apply, $user, &$total): void {
                $groups = $duplicates->groups();
                $this->line('');
                $this->info($municipality->legal_name.': '.($groups === [] ? 'nenhum cadastro repetido.' : count($groups).' grupo(s) de cadastros repetidos.'));

                foreach ($groups as $group) {
                    $this->line('  '.$group['name'].' (fica o cadastro '.substr($group['keeper'], 0, 8).')');
                    foreach ($group['copies'] as $copy) {
                        if ($copy['blocked'] !== null) {
                            $this->warn('    - '.substr($copy['id'], 0, 8).': manter para decisão na ficha ('.$copy['blocked'].').');

                            continue;
                        }
                        if (! $apply) {
                            $this->line('    - '.substr($copy['id'], 0, 8).': será arquivado (sem registros próprios).');
                            $total++;

                            continue;
                        }
                        try {
                            $actor->runAs($user->id, function () use ($copy, $movement, $user): void {
                                $entries = StudentEnrollment::query()->selectRaw('student_enrollments.*, xmin::text AS row_token')
                                    ->where('student_id', $copy['id'])->where('status', 'active')->get();
                                foreach ($entries as $entry) {
                                    $movement->deleteRegistration($entry, (string) $entry->row_token, self::REASON, $user->id);
                                }
                                // Sem matrícula ativa sobrando, o próprio cadastro é arquivado.
                                $student = Student::query()->find($copy['id']);
                                if ($student !== null && ! StudentEnrollment::query()->where('student_id', $student->id)->exists()) {
                                    $student->deleteWithReason(self::REASON, $user->id);
                                }
                            });
                            $this->line('    - '.substr($copy['id'], 0, 8).': arquivado.');
                            $total++;
                        } catch (Throwable $exception) {
                            report($exception);
                            $this->warn('    - '.substr($copy['id'], 0, 8).': não foi possível arquivar; mantido.');
                        }
                    }
                }
            });
        }

        $this->line('');
        if (! $apply) {
            $this->info($total === 0 ? 'Nada a arquivar.' : "{$total} cópia(s) podem ser arquivadas. Nada foi alterado.");
            if ($total > 0) {
                $this->line('Para arquivar: php artisan astuti:alunos-repetidos --confirmar=REMOVER-REPETIDOS --responsavel=admin');
            }
        } else {
            $this->info("{$total} cópia(s) arquivada(s), com motivo e autoria na auditoria.");
        }

        return self::SUCCESS;
    }
}
