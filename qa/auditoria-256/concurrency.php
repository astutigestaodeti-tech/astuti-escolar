<?php

// Ensaio exclusivamente em GitHub Actions com bancos descartáveis.
use App\Audit\AuditActor;
use App\Models\Platform\Municipality;
use App\Models\Tenant\AuditLog;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\SchoolYear;
use App\Models\User;
use App\Students\StudentIdentityConflict;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

function requireCase(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

requireCase(getenv('GITHUB_ACTIONS') === 'true'
    && getenv('APP_ENV') === 'testing'
    && getenv('PLATFORM_DB_HOST') === '127.0.0.1'
    && getenv('PLATFORM_DB_DATABASE') === 'astuti_platform_test'
    && getenv('TENANCY_TEST_DB_A') === 'astuti_tenant_a_test'
    && getenv('TENANCY_TEST_DB_B') === 'astuti_tenant_b_test',
    'Somente bases descartáveis no runner.');

require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$municipality = Municipality::query()->where('slug', 'municipio-a')->sole();
$actor = User::query()->where('email', 'secretaria-qa@example.invalid')->sole();
app(TenantContext::class)->enter($municipality);
app(AuditActor::class)->enter($actor->id);
$db = DB::connection('tenant');
requireCase($db->selectOne('select current_database() as db')->db === 'astuti_tenant_a_test', 'Base de destino incorreta.');
$db->statement("SET statement_timeout = '20s'");
$year = SchoolYear::query()->sole();
$school = '11111111-1111-4111-8111-111111111111';
$cases = [
    'cpf' => ['123.456.789-09', '123.456.789-09', 'Este CPF já está no cadastro de outro aluno da rede. Confira o documento.'],
    'ra' => ['000123456789-0/SP', '0001234567890/SP', 'Este RA já está cadastrado. Confira o registro existente antes de prosseguir.'],
];

function insertStudent(string $id, string $name, array $identity = []): void
{
    Student::query()->create(['id' => $id, 'full_name' => $name, ...$identity]);
}

function insertEnrollment(string $id, string $studentId, string $school, string $year): void
{
    StudentEnrollment::query()->create(['id' => $id, 'student_id' => $studentId, 'school_id' => $school, 'school_year_id' => $year, 'status' => 'active']);
}

if (($argv[1] ?? '') === 'worker') {
    [$field, $loser, $sentinel, $sentinelEnrollment] = array_slice($argv, 2);
    requireCase(isset($cases[$field]), 'Caso desconhecido.');
    [, $value, $expected] = $cases[$field];
    $db->statement("SET application_name = 'astuti-qa-conflict-worker'");
    // A gravação concorrente ainda não está visível nesta sessão.
    $query = Student::query();
    if ($field === 'cpf') {
        $query->where('cpf', $value);
    } else {
        $query->whereRaw("replace(upper(ra), '-', '') = replace(upper(?), '-', '')", [$value]);
    }
    requireCase(! $query->exists(), 'Conflito já visível antes da tentativa.');
    try {
        $db->transaction(function () use ($field, $value, $loser, $sentinel, $sentinelEnrollment, $school, $year): void {
            insertStudent($sentinel, 'Sentinela Fictícia '.$field);
            insertEnrollment($sentinelEnrollment, $sentinel, $school, $year->id);
            insertStudent($loser, 'Perdedor Fictício '.$field, [$field => $value]);
        });
        throw new RuntimeException('Conflito não recusado.');
    } catch (QueryException $exception) {
        requireCase(($exception->errorInfo[0] ?? null) === '23505', 'SQLSTATE inesperado.');
        try {
            StudentIdentityConflict::rethrow($exception);
        } catch (ValidationException $validation) {
            requireCase($validation->errors() === [$field => [$expected]], 'Mensagem de negócio incorreta.');
        }
    }
    requireCase(! Student::query()->whereIn('id', [$loser, $sentinel])->exists(), 'Rollback incompleto em alunos.');
    requireCase(! StudentEnrollment::query()->whereKey($sentinelEnrollment)->exists(), 'Matrícula sentinela persistida.');
    requireCase(! AuditLog::query()->whereIn('auditable_id', [$loser, $sentinel, $sentinelEnrollment])->exists(), 'Auditoria parcial persistida.');
    echo "Worker {$field}: SQLSTATE 23505, mensagem genérica e rollback comprovados.\n";
    exit(0);
}

foreach ($cases as $field => [$value]) {
    [$winner, $winnerEnrollment, $loser, $sentinel, $sentinelEnrollment] = array_map(fn () => (string) Str::uuid(), range(1, 5));
    $pid = (int) $db->selectOne('select pg_backend_pid() as pid')->pid;
    $process = null;
    $pipes = [];
    $db->beginTransaction();
    try {
        insertStudent($winner, 'Vencedor Fictício '.$field, [$field => $value]);
        insertEnrollment($winnerEnrollment, $winner, $school, $year->id);
        $process = proc_open([PHP_BINARY, __FILE__, 'worker', $field, $loser, $sentinel, $sentinelEnrollment], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        requireCase(is_resource($process), 'Worker não iniciado.');
        fclose($pipes[0]);
        $blocked = false;
        $deadline = microtime(true) + 12;
        do {
            $db->selectOne('select pg_stat_clear_snapshot()');
            $waiting = $db->selectOne("select count(*) as total from pg_stat_activity where datname = current_database() and application_name = 'astuti-qa-conflict-worker' and ?::integer = any(pg_blocking_pids(pid))", [$pid]);
            if ((int) $waiting->total === 1) {
                $blocked = true;
                break;
            }
            usleep(50000);
        } while (microtime(true) < $deadline);
        requireCase($blocked, 'Não comprovado bloqueio real pelo vencedor.');
        $db->commit();
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        $process = null;
        requireCase($exit === 0 && str_contains($output, "Worker {$field}:"), 'Worker falhou: '.$error);
        echo $output;
        requireCase(Student::query()->whereKey($winner)->count() === 1 && StudentEnrollment::query()->whereKey($winnerEnrollment)->count() === 1, 'Vencedor ausente.');
        requireCase(! Student::query()->whereIn('id', [$loser, $sentinel])->exists(), 'Registro parcial encontrado.');
        requireCase(! StudentEnrollment::query()->whereKey($sentinelEnrollment)->exists(), 'Matrícula parcial encontrada.');
        requireCase(! AuditLog::query()->whereIn('auditable_id', [$loser, $sentinel, $sentinelEnrollment])->exists(), 'Auditoria parcial encontrada.');
        echo "Concorrência {$field}: duas sessões PostgreSQL, bloqueio observado, um vencedor e nenhuma gravação parcial.\n";
    } finally {
        if ($db->transactionLevel() > 0) {
            $db->rollBack();
        }
        if (is_resource($process)) {
            proc_terminate($process);
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            proc_close($process);
        }
    }
}
