<?php

namespace App\Http\Controllers;

use App\Audit\AuditTrail;
use App\Import\Students\ImportText;
use App\Import\Transport\TransportCsvException;
use App\Import\Transport\TransportCsvReader;
use App\Models\Platform\Municipality;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Security\TwoFactorSession;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;

/** Importa apenas a situação do transporte em matrículas existentes. */
class ConfirmedTransportImportController extends Controller
{
    public function index(Request $request, string $municipio, string $escola): View|RedirectResponse
    {
        [$municipality, $school] = $this->authorizeSchool($request, $escola);

        if ($redirect = $this->requireTwoFactor($request)) {
            return $redirect;
        }

        $years = SchoolYear::query()->whereIn('status', ['active', 'planned'])->orderByDesc('year')->get();

        return view('imports.transport-upload', compact('municipality', 'school', 'years'));
    }

    public function preview(Request $request, string $municipio, string $escola, TransportCsvReader $reader): View
    {
        [$municipality, $school] = $this->authorizeSchool($request, $escola);

        try {
            return $this->previewValidated($request, $municipality, $school, $reader);
        } catch (ValidationException $exception) {
            throw $exception->redirectTo($this->uploadUrl($municipality, $school));
        }
    }

    private function previewValidated(Request $request, Municipality $municipality, School $school, TransportCsvReader $reader): View
    {
        $data = $request->validate([
            'arquivo' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
            'school_year_id' => ['required', 'uuid'],
        ]);
        $year = SchoolYear::query()->whereIn('status', ['active', 'planned'])->find($data['school_year_id']);
        if ($year === null) {
            throw ValidationException::withMessages(['school_year_id' => 'Escolha um ano letivo disponível.']);
        }

        try {
            $inputRows = $reader->read($data['arquivo']->getRealPath());
        } catch (TransportCsvException $exception) {
            throw ValidationException::withMessages(['arquivo' => $exception->getMessage()]);
        }

        // Matrículas ativas desta escola e ano, indexadas por nome sem acento.
        $active = StudentEnrollment::query()->where('school_id', $school->id)
            ->where('school_year_id', $year->id)->where('status', 'active')->get()->groupBy('student_id');
        $studentsById = Student::query()->whereIn('id', $active->keys())->get()->keyBy('id');
        $byName = [];
        foreach ($studentsById as $student) {
            $byName[ImportText::key($student->full_name)][] = $student;
        }

        $rows = [];
        $payloadRows = [];
        $skipped = [];
        $usedEnrollments = [];
        foreach ($inputRows as $entry) {
            $match = 'ra';
            $student = null;
            if ($entry['ra'] !== null) {
                $student = Student::query()->whereRaw("replace(upper(ra), '-', '') = replace(upper(?), '-', '')", [$entry['ra']])->first();
                // Nome diferente do RA é dado contraditório: nada é gravado.
                if ($student !== null && $entry['name'] !== null && ImportText::key($entry['name']) !== ImportText::key($student->full_name)) {
                    throw ValidationException::withMessages(['arquivo' => 'O nome da linha '.$entry['line'].' não confere com o RA. Nenhuma alteração foi feita.']);
                }
            } else {
                $match = $entry['birth_date'] !== null ? 'nome_data' : 'nome';
                $candidates = array_values(array_filter(
                    $byName[ImportText::key($entry['name'])] ?? [],
                    fn (Student $candidate): bool => $entry['birth_date'] === null
                        || $candidate->birth_date?->format('Y-m-d') === $entry['birth_date'],
                ));
                if (count($candidates) > 1) {
                    $skipped[] = $this->skippedRow($entry, 'Mais de um aluno desta escola com este nome'.($entry['birth_date'] === null ? '' : ' e data').'. Use o RA.');

                    continue;
                }
                $student = $candidates[0] ?? null;
            }

            $enrollments = $student === null ? collect() : ($active->get($student->id) ?? collect());
            // A mensagem não revela se o aluno existe em outra escola.
            if ($student === null || $enrollments->count() === 0) {
                $skipped[] = $this->skippedRow($entry, 'Não encontrado entre as matrículas ativas desta escola neste ano.');

                continue;
            }
            if ($enrollments->count() > 1) {
                $skipped[] = $this->skippedRow($entry, 'O aluno tem mais de uma matrícula ativa nesta escola. Resolva antes na ficha do aluno.');

                continue;
            }
            $enrollment = $enrollments->first();
            if (isset($usedEnrollments[$enrollment->id])) {
                throw ValidationException::withMessages(['arquivo' => 'As linhas '.$usedEnrollments[$enrollment->id].' e '.$entry['line'].' indicam o mesmo aluno. Corrija o arquivo.']);
            }
            $usedEnrollments[$enrollment->id] = $entry['line'];

            if ($enrollment->transport_status === 'cancelled' && $entry['status'] !== 'cancelled') {
                $skipped[] = $this->skippedRow($entry, 'O transporte deste aluno está cancelado. Reativar só por revisão manual.');

                continue;
            }

            $version = DB::connection('tenant')->selectOne('SELECT xmin::text AS row_version FROM student_enrollments WHERE id = ?', [$enrollment->id]);
            $rows[] = [
                'name' => $student->full_name,
                'ra' => $student->ra,
                'line' => $entry['line'],
                'match' => $match,
                'current_status' => $enrollment->transport_status,
                'desired_status' => $entry['status'],
            ];
            $payloadRows[] = [
                'enrollment_id' => $enrollment->id,
                'student_id' => $student->id,
                'ra' => (string) $student->ra,
                'match' => $match,
                'current_status' => $enrollment->transport_status,
                'desired_status' => $entry['status'],
                'version' => $version->row_version,
            ];
        }

        if ($payloadRows === []) {
            throw ValidationException::withMessages(['arquivo' => 'Nenhuma linha do arquivo corresponde a uma matrícula ativa desta escola em '.$year->year.' que possa ser atualizada. Confira a escola, o ano letivo e o arquivo.']);
        }

        $payload = [
            'version' => 1,
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'year_id' => $year->id,
            'actor_id' => $request->user()->id,
            'expires_at' => now()->addHour()->getTimestamp(),
            'rows' => $payloadRows,
        ];
        $token = Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return view('imports.transport-review', compact('municipality', 'school', 'year', 'rows', 'skipped', 'token'));
    }

    public function confirm(Request $request, string $municipio, string $escola): RedirectResponse
    {
        [$municipality, $school] = $this->authorizeSchool($request, $escola);

        try {
            return $this->confirmValidated($request, $municipality, $school);
        } catch (ValidationException $exception) {
            throw $exception->redirectTo($this->uploadUrl($municipality, $school));
        }
    }

    private function confirmValidated(Request $request, Municipality $municipality, School $school): RedirectResponse
    {
        $data = $request->validate(['lote' => ['required', 'string', 'max:500000']]);

        try {
            $payload = json_decode(Crypt::decryptString($data['lote']), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            throw ValidationException::withMessages(['lote' => 'Prévia inválida ou alterada. Envie o CSV novamente.']);
        }

        if (! is_array($payload) || ($payload['version'] ?? null) !== 1
            || ! Str::isUuid($payload['id'] ?? '')
            || ($payload['school_id'] ?? null) !== $school->id
            || ($payload['actor_id'] ?? null) !== $request->user()->id
            || ! is_int($payload['expires_at'] ?? null) || $payload['expires_at'] < now()->getTimestamp()
            || ! is_array($payload['rows'] ?? null)
            || count($payload['rows']) === 0 || count($payload['rows']) > 500) {
            throw ValidationException::withMessages(['lote' => 'Conferência vencida ou não autorizada. Envie o CSV novamente.']);
        }

        $seen = [];
        foreach ($payload['rows'] as $row) {
            if (! is_array($row)
                || ! Str::isUuid($row['enrollment_id'] ?? '')
                || ! Str::isUuid($row['student_id'] ?? '')
                || ! in_array($row['desired_status'] ?? null, ['active', 'not_requested', 'cancelled'], true)
                || ! in_array($row['current_status'] ?? null, [null, 'active', 'not_requested', 'cancelled'], true)
                || ! is_string($row['ra'] ?? null)
                || ! in_array($row['match'] ?? 'ra', ['ra', 'nome_data', 'nome'], true)
                || ! is_string($row['version'] ?? null)
                || isset($seen[$row['enrollment_id']])) {
                throw ValidationException::withMessages(['lote' => 'A prévia contém registros inválidos ou repetidos.']);
            }
            $seen[$row['enrollment_id']] = true;
        }

        // Alunos achados pelo nome (lista da Secretaria, sem RA) exigem conferência explícita.
        $byName = array_filter($payload['rows'], fn (array $row): bool => ($row['match'] ?? 'ra') !== 'ra');
        if ($byName !== [] && ! $request->boolean('conferido_nomes')) {
            throw ValidationException::withMessages(['conferido_nomes' => 'Confira os alunos identificados pelo nome e marque a confirmação antes de gravar.']);
        }

        try {
            $changed = DB::connection('tenant')->transaction(function () use ($payload, $request, $school): int {
                $lockedSchool = School::query()->whereKey($school->id)
                    ->where('status', 'active')->lockForUpdate()->first();
                $year = SchoolYear::query()->whereIn('status', ['active', 'planned'])
                    ->lockForUpdate()->find($payload['year_id'] ?? null);
                if ($lockedSchool === null || $year === null) {
                    throw ValidationException::withMessages(['lote' => 'Escola ou ano letivo indisponível. Refaça a conferência.']);
                }

                if (DB::connection('tenant')->table('transport_import_receipts')->where('id', $payload['id'])->exists()) {
                    throw ValidationException::withMessages(['lote' => 'Este lote já foi aplicado. Nenhuma alteração foi repetida.']);
                }

                $changed = 0;
                foreach ($payload['rows'] as $row) {
                    $enrollments = StudentEnrollment::query()->where('school_id', $school->id)
                        ->where('school_year_id', $year->id)->where('student_id', $row['student_id'])
                        ->where('status', 'active')->lockForUpdate()->limit(2)->get();
                    $enrollment = $enrollments->count() === 1 ? $enrollments->first() : null;
                    if ($enrollment === null || $enrollment->id !== $row['enrollment_id']) {
                        throw ValidationException::withMessages(['lote' => 'Uma matrícula deixou de estar ativa nesta escola. Nenhuma alteração foi gravada.']);
                    }

                    $version = DB::connection('tenant')->selectOne('SELECT xmin::text AS row_version FROM student_enrollments WHERE id = ?', [$enrollment->id]);
                    $student = Student::query()->whereKey($row['student_id'])->lockForUpdate()->first();
                    if ($version->row_version !== $row['version'] || $student === null
                        || strtoupper(str_replace('-', '', (string) $student->ra)) !== strtoupper(str_replace('-', '', $row['ra']))
                        || $enrollment->transport_status !== $row['current_status']) {
                        throw ValidationException::withMessages(['lote' => 'O cadastro foi alterado depois da prévia. Reenvie o arquivo para conferir novamente.']);
                    }
                    if ($enrollment->transport_status === 'cancelled' && $row['desired_status'] !== 'cancelled') {
                        throw ValidationException::withMessages(['lote' => 'Transporte cancelado não pode ser reativado por importação.']);
                    }

                    if ($enrollment->transport_status !== $row['desired_status']) {
                        $enrollment->update(['transport_status' => $row['desired_status']]);
                        $changed++;
                    }
                }

                DB::connection('tenant')->table('transport_import_receipts')->insert([
                    'id' => $payload['id'],
                    'school_id' => $school->id,
                    'school_year_id' => $year->id,
                    'created_by' => $request->user()->id,
                    'row_count' => count($payload['rows']),
                    'changed_count' => $changed,
                    'created_at' => now(),
                ]);

                app(AuditTrail::class)->record('tenant', 'transport_import.completed', 'transport_import_receipt', $payload['id'], null, [
                    'id' => $payload['id'],
                    'school_id' => $school->id,
                    'school_year_id' => $year->id,
                    'row_count' => count($payload['rows']),
                    'changed_count' => $changed,
                ]);

                return $changed;
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23505') {
                throw ValidationException::withMessages(['lote' => 'Este lote já foi aplicado. Confira a situação atual.']);
            }

            throw $exception;
        }

        return redirect()->route('school.imports.transport.index', ['municipio' => $municipality->slug, 'escola' => $school->id])
            ->with('status', $changed.' matrícula(s) atualizada(s) com auditoria.');
    }

    private function uploadUrl(Municipality $municipality, School $school): string
    {
        return route('school.imports.transport.index', ['municipio' => $municipality->slug, 'escola' => $school->id]);
    }

    /** @return array{0: mixed, 1: School} */
    private function authorizeSchool(Request $request, string $id): array
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($id);
        Gate::authorize('manageStudents', [$school, $municipality]);

        return [$municipality, $school];
    }

    private function requireTwoFactor(Request $request): ?RedirectResponse
    {
        return app(TwoFactorSession::class)->isVerified($request, $request->user()->fresh())
            ? null
            : redirect()->guest(route('two-factor.challenge'));
    }

    /** Linha que não será aplicada: só dados do próprio arquivo, sem consultar outras escolas. */
    private function skippedRow(array $entry, string $reason): array
    {
        return [
            'line' => $entry['line'],
            'name' => $entry['name'] ?? $entry['ra'],
            'school' => $entry['school'],
            'reason' => $reason,
        ];
    }
}
