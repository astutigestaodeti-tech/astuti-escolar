<?php

namespace App\Http\Controllers;

use App\Audit\AuditTrail;
use App\Import\Students\ImportText;
use App\Import\Transport\TransportCsvException;
use App\Import\Transport\TransportCsvReader;
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

        $rows = [];
        $payloadRows = [];
        foreach ($inputRows as $entry) {
            $student = Student::query()->whereRaw("replace(upper(ra), '-', '') = replace(upper(?), '-', '')", [$entry['ra']])->first();
            $enrollment = $student === null ? null : StudentEnrollment::query()
                ->where('student_id', $student->id)->where('school_id', $school->id)
                ->where('school_year_id', $year->id)->where('status', 'active')->first();

            // A mensagem evita revelar se o RA pertence a outra escola.
            if ($enrollment === null || $student === null) {
                throw ValidationException::withMessages(['arquivo' => 'A linha '.$entry['line'].' não corresponde a matrícula ativa desta escola e ano. Confira o RA.']);
            }
            if ($entry['name'] !== null && ImportText::key($entry['name']) !== ImportText::key($student->full_name)) {
                throw ValidationException::withMessages(['arquivo' => 'O nome da linha '.$entry['line'].' não confere com o RA. Nenhuma alteração foi feita.']);
            }
            if ($enrollment->transport_status === 'cancelled' && $entry['status'] !== 'cancelled') {
                throw ValidationException::withMessages(['arquivo' => 'A linha '.$entry['line'].' tenta reativar transporte cancelado. Encaminhe para revisão manual.']);
            }

            $version = DB::connection('tenant')->selectOne('SELECT xmin::text AS row_version FROM student_enrollments WHERE id = ?', [$enrollment->id]);
            $rows[] = [
                'name' => $student->full_name,
                'ra' => $student->ra,
                'line' => $entry['line'],
                'current_status' => $enrollment->transport_status,
                'desired_status' => $entry['status'],
            ];
            $payloadRows[] = [
                'enrollment_id' => $enrollment->id,
                'student_id' => $student->id,
                'ra' => $entry['ra'],
                'current_status' => $enrollment->transport_status,
                'desired_status' => $entry['status'],
                'version' => $version->row_version,
            ];
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

        return view('imports.transport-review', compact('municipality', 'school', 'year', 'rows', 'token'));
    }

    public function confirm(Request $request, string $municipio, string $escola): RedirectResponse
    {
        [$municipality, $school] = $this->authorizeSchool($request, $escola);
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
                || ! is_string($row['version'] ?? null)
                || isset($seen[$row['enrollment_id']])) {
                throw ValidationException::withMessages(['lote' => 'A prévia contém registros inválidos ou repetidos.']);
            }
            $seen[$row['enrollment_id']] = true;
        }

        try {
            $changed = DB::connection('tenant')->transaction(function () use ($payload, $request, $school): int {
                $year = SchoolYear::query()->whereIn('status', ['active', 'planned'])
                    ->lockForUpdate()->find($payload['year_id'] ?? null);
                if ($year === null || School::query()->whereKey($school->id)
                    ->where('status', 'active')->lockForUpdate()->first() === null) {
                    throw ValidationException::withMessages(['lote' => 'Escola ou ano letivo indisponível. Refaça a conferência.']);
                }

                if (DB::connection('tenant')->table('transport_import_receipts')->where('id', $payload['id'])->exists()) {
                    throw ValidationException::withMessages(['lote' => 'Este lote já foi aplicado. Nenhuma alteração foi repetida.']);
                }

                $changed = 0;
                foreach ($payload['rows'] as $row) {
                    $enrollment = StudentEnrollment::query()->whereKey($row['enrollment_id'])
                        ->where('school_id', $school->id)->where('school_year_id', $year->id)
                        ->where('student_id', $row['student_id'])->where('status', 'active')
                        ->lockForUpdate()->first();
                    if ($enrollment === null) {
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
}
