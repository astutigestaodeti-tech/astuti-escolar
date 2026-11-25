<?php

namespace App\Console\Commands;

use App\Audit\AuditActor;
use App\Educacenso\Texto;
use App\Models\Platform\Municipality;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentSpecialEducation;
use App\Models\User;
use App\Photos\IdentificationPhoto;
use App\Support\EmailNormalizer;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Throwable;
use ZipArchive;

/**
 * Coloca fotos fictícias (geradas para a demonstração) nos alunos sem foto,
 * a partir de um .zip com as pastas "Crianças Típicas" e "Crianças Atípicas".
 *
 * Só na instalação de demonstração. Foto de criança atípica vai apenas para
 * aluno com educação especial cadastrada, de preferência com a mesma
 * condição; aluno sem educação especial nunca recebe foto atípica, para a
 * imagem não sugerir uma deficiência que o cadastro não tem. O sexo da foto
 * acompanha o do cadastro. Cada foto é usada uma vez.
 */
class AssignDemoStudentPhotos extends Command
{
    protected $signature = 'astuti:demo-fotos-alunos
        {arquivo : Caminho do .zip com as fotos fictícias}
        {--confirmar= : Digite COLOCAR-FOTOS-FICTICIAS para gravar}
        {--responsavel= : E-mail ou usuário de quem autoriza (fica na auditoria)}';

    protected $description = 'Demonstração: distribui fotos fictícias de um .zip entre os alunos sem foto.';

    private const CONFIRMATION = 'COLOCAR-FOTOS-FICTICIAS';

    /** Condição pelas palavras do nome do arquivo (pasta 1 das atípicas). */
    private const KEYWORDS = [
        'down' => ['deficiencia_intelectual'],
        'intelectual' => ['deficiencia_intelectual'],
        'cadeirante' => ['deficiencia_fisica'],
        'protese' => ['deficiencia_fisica'],
        'auditiva' => ['deficiencia_auditiva', 'surdez'],
        'visual' => ['cegueira', 'baixa_visao', 'visao_monocular'],
        'tea' => ['tea'],
    ];

    /** Pasta 2 das atípicas, sem nome descritivo: condição visível na foto, pelo número final. */
    private const NUMBERED_ATYPICAL = [
        '18_12_14-1' => ['deficiencia_intelectual'], '18_12_16-3' => ['tea'], '18_12_17-4' => ['deficiencia_fisica'],
        '18_12_18-5' => ['deficiencia_fisica'], '18_12_19-6' => ['deficiencia_intelectual'], '18_12_22-8' => ['tea'],
        '18_12_24-9' => ['deficiencia_fisica'], '18_12_25-10' => ['deficiencia_fisica'],
    ];

    public function handle(TenantContext $context, AuditActor $actor, IdentificationPhoto $photos): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Fotos fictícias só na instalação de demonstração. Nada foi alterado.');

            return self::FAILURE;
        }
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

        $zip = new ZipArchive;
        if (! is_file((string) $this->argument('arquivo')) || $zip->open((string) $this->argument('arquivo')) !== true) {
            $this->error('Não consegui abrir o .zip informado. Nada foi alterado.');

            return self::INVALID;
        }
        $catalog = $this->catalog($zip);
        $this->info(count($catalog).' foto(s) no arquivo: '.$this->describe($catalog));

        $configured = array_keys((array) config('tenancy.connections', []));
        foreach (Municipality::query()->where('status', 'active')->whereIn('db_key', $configured)->orderBy('legal_name')->get() as $municipality) {
            $context->runFor($municipality, function () use ($municipality, $catalog, $zip, $actor, $photos, $apply, $user): void {
                [$plan, $missing] = $this->plan($catalog);
                $this->line('');
                $this->info($municipality->legal_name.': '.count($plan).' aluno(s) sem foto receberão uma.');
                foreach ($missing as $label => $total) {
                    $this->warn("  Faltam {$total} foto(s) de {$label}.");
                }
                $unused = count($catalog) - count($plan);
                if ($unused > 0) {
                    $this->line("  {$unused} foto(s) do arquivo sobraram.");
                }
                if (! $apply) {
                    return;
                }
                $done = 0;
                foreach ($plan as $studentId => $entry) {
                    try {
                        $actor->runAs($user->id, function () use ($studentId, $entry, $zip, $photos, $municipality): void {
                            $student = Student::query()->whereNull('photo_path')->find($studentId);
                            if ($student === null) {
                                return;
                            }
                            $path = $photos->storeContents((string) $zip->getFromIndex($entry['index']), $municipality->slug.'/alunos');
                            $student->forceFill(['photo_path' => $path, 'photo_updated_at' => now()])->save();
                        });
                        $done++;
                    } catch (Throwable $exception) {
                        report($exception);
                        $this->warn('  - '.substr($studentId, 0, 8).': não foi possível gravar a foto.');
                    }
                }
                $this->info("  {$done} foto(s) gravada(s), com autoria na auditoria.");
            });
        }
        $zip->close();

        if (! $apply) {
            $this->line('');
            $this->info('Nada foi alterado. Para gravar: php artisan astuti:demo-fotos-alunos '.$this->argument('arquivo').' --confirmar='.self::CONFIRMATION.' --responsavel=demo.monte-sereno@astuti.invalid');
        }

        return self::SUCCESS;
    }

    /**
     * Fotos do .zip com sexo, tipo e condições.
     *
     * @return list<array{index: int, name: string, sex: string, atypical: bool, conditions: list<string>}>
     */
    private function catalog(ZipArchive $zip): array
    {
        $catalog = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $path = (string) $zip->getNameIndex($i);
            $base = pathinfo($path, PATHINFO_FILENAME);
            if (! preg_match('/\.(png|jpe?g|webp)$/i', $path) || str_contains($path, '__MACOSX')) {
                continue;
            }
            $normalized = strtolower((string) Texto::normalizar(str_replace(['/', '_', '-'], ' ', $path)));
            $atypical = str_contains($normalized, 'atipic');
            $sex = match (true) {
                str_starts_with(strtolower($base), 'menina') => 'female',
                str_starts_with(strtolower($base), 'menino') => 'male',
                preg_match('/-(\d+)$/', $base, $m) === 1 => (int) $m[1] <= 5 ? 'male' : 'female',
                default => null,
            };
            if ($sex === null) {
                $this->warn("Sem como saber se é menino ou menina, ignorada: {$base}");

                continue;
            }
            $conditions = [];
            if ($atypical) {
                foreach (self::KEYWORDS as $word => $keys) {
                    if (preg_match('/(^|_)'.$word.'(_|$)/', strtolower($base)) === 1) {
                        $conditions = [...$conditions, ...$keys];
                    }
                }
                foreach (self::NUMBERED_ATYPICAL as $suffix => $keys) {
                    if (str_ends_with($base, $suffix)) {
                        $conditions = $keys;
                    }
                }
            }
            $catalog[] = ['index' => $i, 'name' => $base, 'sex' => $sex, 'atypical' => $atypical, 'conditions' => array_values(array_unique($conditions))];
        }
        usort($catalog, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $catalog;
    }

    /**
     * Aluno sem foto => foto, e quantas faltaram por grupo.
     *
     * @param  list<array>  $catalog
     * @return array{0: array<string, array>, 1: array<string, int>}
     */
    private function plan(array $catalog): array
    {
        $special = StudentSpecialEducation::query()->get(['student_id', 'conditions'])
            ->filter(fn ($r): bool => ($r->conditions ?? []) !== [])->mapWithKeys(fn ($r): array => [$r->student_id => $r->conditions]);
        $students = Student::query()->whereNull('photo_path')->orderBy('full_name')->get(['id', 'full_name', 'sex']);
        $available = $catalog;
        $plan = [];
        $missing = [];
        $take = function (callable $accept) use (&$available): ?array {
            foreach ($available as $key => $entry) {
                if ($accept($entry)) {
                    unset($available[$key]);

                    return $entry;
                }
            }

            return null;
        };
        // Atípicos primeiro, para a foto com a mesma condição não ir para outro.
        $ordered = $students->sortBy(fn (Student $s): int => $special->has($s->id) ? 0 : 1)->values();
        foreach ($ordered as $student) {
            $conditions = $special->get($student->id, []);
            $sameSex = fn (array $e): bool => $student->sex === null || $e['sex'] === $student->sex;
            if ($conditions !== []) {
                $photo = $take(fn (array $e): bool => $e['atypical'] && $sameSex($e) && array_intersect($e['conditions'], $conditions) !== [])
                    ?? $take(fn (array $e): bool => $e['atypical'] && $sameSex($e) && $e['conditions'] === [])
                    ?? $take(fn (array $e): bool => ! $e['atypical'] && $sameSex($e));
            } else {
                $photo = $take(fn (array $e): bool => ! $e['atypical'] && $sameSex($e));
            }
            if ($photo === null) {
                $label = match ($student->sex) {
                    'female' => $conditions !== [] ? 'menina atípica (ou típica)' : 'menina típica',
                    'male' => $conditions !== [] ? 'menino atípico (ou típico)' : 'menino típico',
                    default => 'criança sem sexo no cadastro',
                };
                $missing[$label] = ($missing[$label] ?? 0) + 1;

                continue;
            }
            $plan[$student->id] = $photo;
        }

        return [$plan, $missing];
    }

    /** @param  list<array>  $catalog */
    private function describe(array $catalog): string
    {
        $count = fn (bool $atypical, string $sex): int => count(array_filter($catalog, fn ($e) => $e['atypical'] === $atypical && $e['sex'] === $sex));

        return sprintf('%d menina(s) e %d menino(s) típicos; %d menina(s) e %d menino(s) atípicos.',
            $count(false, 'female'), $count(false, 'male'), $count(true, 'female'), $count(true, 'male'));
    }
}
