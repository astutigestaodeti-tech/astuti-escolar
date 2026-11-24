<?php

namespace App\Http\Controllers;

use App\Academics\GradeAccess;
use App\Attendance\ActiveSearchAlerts;
use App\Audit\AuditTrail;
use App\Documents\VerificationCode;
use App\Models\Tenant\ActiveSearchCase;
use App\Models\Tenant\ActiveSearchContact;
use App\Models\Tenant\ActiveSearchParameter;
use App\Models\Tenant\IssuedDocument;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\StudentEnrollment;
use App\Support\Qr\VerificationQrCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ActiveSearchController extends Controller
{
    public function __construct(private readonly ActiveSearchAlerts $alerts) {}

    public function network(Request $request, string $municipio): Response
    {
        $municipality = $request->attributes->get('astuti.municipality');
        Gate::authorize('viewNetworkPanel', [School::class, $municipality]);
        $years = SchoolYear::query()->orderByDesc('year')->get();
        $year = $this->selectedYear($request, $years);
        $settings = $year ? $this->alerts->parameters($year) : null;
        $cases = $year ? ActiveSearchCase::query()->where('school_year_id', $year->id)->get() : collect();
        $schools = School::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']);
        $rows = $schools->map(function (School $school) use ($cases): array {
            $items = $cases->where('school_id', $school->id);
            $returned = $items->filter(fn (ActiveSearchCase $item): bool => $item->returned_at !== null);
            $monthReturns = $returned->filter(fn (ActiveSearchCase $item): bool => $item->returned_at->isSameMonth(today()));
            $average = $returned->avg(fn (ActiveSearchCase $item): float => $item->opened_at->diffInDays($item->returned_at));

            return [
                'school' => $school,
                'open' => $items->whereIn('status', ['open', 'referred'])->count(),
                'returns' => $monthReturns->count(),
                'average' => $average === null ? null : round($average, 1),
            ];
        });

        $returned = $cases->filter(fn (ActiveSearchCase $item): bool => $item->returned_at !== null);
        $networkAverage = $returned->avg(fn (ActiveSearchCase $item): float => $item->opened_at->diffInDays($item->returned_at));

        return response()->view('active-search.network', compact('municipality', 'years', 'year', 'settings', 'rows', 'networkAverage'))
            ->header('Cache-Control', 'no-store, private');
    }

    public function saveParameters(Request $request, string $municipio): RedirectResponse
    {
        $municipality = $request->attributes->get('astuti.municipality');
        Gate::authorize('viewNetworkPanel', [School::class, $municipality]);
        $input = $request->validate([
            'ano_id' => ['required', 'uuid'],
            'dias' => ['required', 'integer', 'between:1,30'],
            'frequencia' => ['required', 'integer', 'between:1,100'],
        ]);
        $year = SchoolYear::query()->findOrFail($input['ano_id']);

        DB::connection('tenant')->transaction(function () use ($year, $input, $request): void {
            SchoolYear::query()->whereKey($year->id)->lockForUpdate()->firstOrFail();
            $record = ActiveSearchParameter::query()->where('school_year_id', $year->id)->first();
            $before = $record?->only(['school_year_id', 'consecutive_days', 'monthly_percentage', 'updated_by']);
            $record ??= new ActiveSearchParameter(['id' => (string) Str::uuid(), 'school_year_id' => $year->id]);
            $record->fill(['consecutive_days' => $input['dias'], 'monthly_percentage' => $input['frequencia'], 'updated_by' => $request->user()->id])->save();
            app(AuditTrail::class)->record('tenant', 'active_search.parameters.saved', ActiveSearchParameter::class,
                $record->id, $before, $record->only(['school_year_id', 'consecutive_days', 'monthly_percentage', 'updated_by']));
        });

        return redirect()->route('active-search.network', ['municipio' => $municipality->slug, 'ano' => $year->year])
            ->with('status', 'Parâmetros atualizados.');
    }

    public function school(Request $request, string $municipio, string $escola): Response
    {
        [$municipality, $school] = $this->managedSchool($request, $escola);
        $years = SchoolYear::query()->orderByDesc('year')->get();
        $year = $this->selectedYear($request, $years);
        $settings = $year ? $this->alerts->parameters($year) : null;
        $enrollments = $year ? $this->alerts->enrollments($school, $year) : collect();
        $summaries = $year ? $this->alerts->summaries($school, $year, $enrollments, today()) : [];
        $cases = $year ? ActiveSearchCase::query()->where('school_id', $school->id)
            ->where('school_year_id', $year->id)->get()->keyBy('enrollment_id') : collect();
        $alerted = $enrollments->filter(fn ($entry): bool => $summaries[$entry->id]['alert'] ?? false);
        $caseEnrollments = StudentEnrollment::query()->where('school_id', $school->id)
            ->whereIn('id', $cases->keys())->with(['student', 'schoolClass'])->get()->keyBy('id');

        return response()->view('active-search.school', compact('municipality', 'school', 'years', 'year', 'settings', 'summaries', 'alerted', 'cases', 'caseEnrollments'))
            ->header('Cache-Control', 'no-store, private');
    }

    public function classAlerts(Request $request, string $municipio, string $escola, string $turma): Response
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($escola);
        $class = SchoolClass::query()->where('school_id', $school->id)->where('status', 'active')->with('schoolYear')->findOrFail($turma);
        $access = app(GradeAccess::class);
        abort_unless($access->isManagement($request->user(), $municipality, $school)
            || $access->isAssignedTeacher($request->user(), $municipality, $school, $class), 403);
        $year = $class->schoolYear;
        $settings = $this->alerts->parameters($year);
        $enrollments = $this->alerts->enrollments($school, $year, $class->id);
        $summaries = $this->alerts->summaries($school, $year, $enrollments, today());
        $alerted = $enrollments->filter(fn ($entry): bool => $summaries[$entry->id]['alert'] ?? false);

        return response()->view('active-search.class', compact('municipality', 'school', 'class', 'settings', 'summaries', 'alerted'))
            ->header('Cache-Control', 'no-store, private');
    }

    public function detail(Request $request, string $municipio, string $escola, string $matricula): Response
    {
        [$municipality, $school] = $this->managedSchool($request, $escola);
        $entry = $this->enrollment($school, $matricula);
        $case = ActiveSearchCase::query()->where('enrollment_id', $entry->id)->where('school_id', $school->id)->first();
        abort_unless($case !== null || $entry->status === 'active', 404);
        $contacts = $case ? ActiveSearchContact::query()->where('active_search_case_id', $case->id)->orderBy('contact_date')->orderBy('created_at')->get() : collect();
        $documents = $case ? IssuedDocument::query()->where('kind', 'active_search_referral')->where('active_search_case_id', $case->id)->orderByDesc('issued_at')->get() : collect();
        $summary = $this->alerts->summaries($school, $entry->schoolYear, collect([$entry]), today())[$entry->id] ?? null;
        app(AuditTrail::class)->record('tenant', 'active_search.case.viewed', ActiveSearchCase::class, $case?->id);

        return response()->view('active-search.detail', compact('municipality', 'school', 'entry', 'case', 'contacts', 'documents', 'summary'))
            ->header('Cache-Control', 'no-store, private');
    }

    public function saveCase(Request $request, string $municipio, string $escola, string $matricula): RedirectResponse
    {
        [$municipality, $school] = $this->managedSchool($request, $escola);
        $input = $request->validate([
            'situacao' => ['required', Rule::in(['open', 'returned', 'referred', 'closed'])],
            'motivo' => ['nullable', Rule::in(array_keys(ActiveSearchCase::REASONS))],
        ]);
        DB::connection('tenant')->transaction(function () use ($school, $matricula, $input, $request): void {
            $entry = $this->enrollment($school, $matricula, true);
            $case = ActiveSearchCase::query()->where('enrollment_id', $entry->id)->where('school_id', $school->id)->lockForUpdate()->first();
            abort_unless($case !== null || $entry->status === 'active', 404);
            if ($input['situacao'] === 'referred' && $case?->status !== 'referred') {
                throw ValidationException::withMessages(['situacao' => 'Emita o documento para registrar o encaminhamento.']);
            }
            if ($case === null && $input['situacao'] !== 'open') {
                throw ValidationException::withMessages(['situacao' => 'Abra o caso antes de mudar sua situação.']);
            }
            $before = $case?->only(['id', 'status', 'opened_at', 'returned_at']);
            $previousReason = $case?->reason_category;
            $case ??= new ActiveSearchCase([
                'id' => (string) Str::uuid(), 'enrollment_id' => $entry->id, 'school_id' => $school->id,
                'school_year_id' => $entry->school_year_id, 'student_id' => $entry->student_id, 'opened_at' => now(),
            ]);
            if ($input['situacao'] === 'returned' && $case->returned_at === null) {
                $case->returned_at = now();
            }
            $case->fill(['status' => $input['situacao'], 'reason_category' => $input['motivo'] ?? null, 'updated_by' => $request->user()->id])->save();
            // O motivo, mesmo em categoria fechada, não é copiado para a auditoria.
            app(AuditTrail::class)->record('tenant', 'active_search.case.saved', ActiveSearchCase::class,
                $case->id, $before, [
                    ...$case->only(['id', 'status', 'opened_at', 'returned_at', 'updated_by']),
                    'reason_changed' => $previousReason !== $case->reason_category,
                ]);
        });

        return $this->backToCase($municipality->slug, $school->id, $matricula)->with('status', 'Ficha atualizada.');
    }

    public function contact(Request $request, string $municipio, string $escola, string $matricula): RedirectResponse
    {
        [$municipality, $school] = $this->managedSchool($request, $escola);
        $input = $request->validate([
            'data' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'meio' => ['required', Rule::in(array_keys(ActiveSearchContact::CHANNELS))],
            'resultado' => ['required', Rule::in(array_keys(ActiveSearchContact::OUTCOMES))],
        ]);
        DB::connection('tenant')->transaction(function () use ($school, $matricula, $input, $request): void {
            $entry = $this->enrollment($school, $matricula, true);
            $case = ActiveSearchCase::query()->where('school_id', $school->id)->where('enrollment_id', $entry->id)->lockForUpdate()->firstOrFail();
            if ($case->status === 'closed') {
                throw ValidationException::withMessages(['data' => 'Reabra o caso antes de registrar outro contato.']);
            }
            if ($input['data'] < $entry->schoolYear->start_date->toDateString()) {
                throw ValidationException::withMessages(['data' => 'A data não pode anteceder o ano letivo.']);
            }
            $contact = ActiveSearchContact::query()->create([
                'id' => (string) Str::uuid(), 'active_search_case_id' => $case->id,
                'contact_date' => $input['data'], 'channel' => $input['meio'],
                'outcome' => $input['resultado'], 'recorded_by' => $request->user()->id,
            ]);
            app(AuditTrail::class)->record('tenant', 'active_search.contact.recorded', ActiveSearchContact::class,
                $contact->id, null, $contact->only(['id', 'active_search_case_id', 'contact_date', 'recorded_by']));
        });

        return $this->backToCase($municipality->slug, $school->id, $matricula)->with('status', 'Contato registrado.');
    }

    public function issue(Request $request, string $municipio, string $escola, string $matricula): RedirectResponse
    {
        [$municipality, $school] = $this->managedSchool($request, $escola);
        $request->validate(['confirmar' => ['accepted']]);
        $document = DB::connection('tenant')->transaction(function () use ($municipality, $school, $matricula, $request): IssuedDocument {
            $entry = $this->enrollment($school, $matricula, true);
            $case = ActiveSearchCase::query()->where('school_id', $school->id)->where('enrollment_id', $entry->id)->lockForUpdate()->firstOrFail();
            if (! in_array($case->status, ['open', 'referred'], true)) {
                throw ValidationException::withMessages(['confirmar' => 'O caso precisa estar aberto ou encaminhado.']);
            }
            $contacts = ActiveSearchContact::query()->where('active_search_case_id', $case->id)->orderBy('contact_date')->orderBy('created_at')->get();
            if ($contacts->isEmpty()) {
                throw ValidationException::withMessages(['confirmar' => 'Registre as tentativas de contato antes de emitir o encaminhamento.']);
            }
            $summary = $this->alerts->summaries($school, $entry->schoolYear, collect([$entry]), today())[$entry->id] ?? null;
            if ($summary === null || $summary['absences'] + $summary['justified'] === 0) {
                throw ValidationException::withMessages(['confirmar' => 'Não existem faltas registradas no período para fundamentar o documento.']);
            }
            $code = null;
            for ($attempt = 0; $attempt < 12; $attempt++) {
                $candidate = VerificationCode::generate();
                if (! IssuedDocument::query()->where('verification_code', $candidate)->exists()) {
                    $code = $candidate;
                    break;
                }
            }
            if ($code === null) {
                throw new RuntimeException('Não foi possível gerar o código de verificação.');
            }
            $document = IssuedDocument::query()->create([
                'id' => (string) Str::uuid(), 'kind' => 'active_search_referral',
                'active_search_case_id' => $case->id, 'student_id' => $entry->student_id,
                'school_id' => $school->id, 'enrollment_id' => $entry->id, 'verification_code' => $code,
                'issued_by' => $request->user()->id, 'issued_at' => now(),
                'snapshot_student_name' => $entry->student->full_name, 'snapshot_school_name' => $school->name,
                'snapshot_municipality_name' => $municipality->legal_name, 'snapshot_school_year' => $entry->schoolYear->year,
                'snapshot_stage' => $entry->schoolClass?->stage, 'snapshot_class_name' => $entry->schoolClass?->name,
                'snapshot_shift' => $entry->schoolClass?->shift,
                'referral_snapshot' => [
                    'summary' => $summary,
                    'contacts' => $contacts->map(fn (ActiveSearchContact $contact): array => [
                        'date' => $contact->contact_date->toDateString(), 'channel' => $contact->channel, 'outcome' => $contact->outcome,
                    ])->all(),
                ],
            ]);
            $before = $case->status;
            $case->fill(['status' => 'referred', 'updated_by' => $request->user()->id])->save();
            app(AuditTrail::class)->record('tenant', 'active_search.referral.issued', IssuedDocument::class, $document->id,
                null, $document->only(['id', 'school_id', 'enrollment_id', 'issued_by', 'issued_at']));
            app(AuditTrail::class)->record('tenant', 'active_search.case.referred', ActiveSearchCase::class, $case->id,
                ['status' => $before], ['status' => 'referred']);

            return $document;
        });

        return redirect()->route('school.active-search.document', [
            'municipio' => $municipality->slug, 'escola' => $school->id, 'matricula' => $matricula, 'documento' => $document->id,
        ])->with('status', 'Documento emitido. A entrega ao Conselho Tutelar deve ser realizada pela escola.');
    }

    public function document(Request $request, string $municipio, string $escola, string $matricula, string $documento, VerificationQrCode $qr): Response
    {
        [$municipality, $school] = $this->managedSchool($request, $escola);
        $entry = $this->enrollment($school, $matricula);
        $document = IssuedDocument::query()->where('kind', 'active_search_referral')
            ->where('school_id', $school->id)->where('enrollment_id', $entry->id)->findOrFail($documento);
        $verificationUrl = route('documents.verify', ['codigo' => $document->verification_code]);
        $qrSvg = $qr->svg($verificationUrl);
        app(AuditTrail::class)->record('tenant', 'active_search.referral.viewed', IssuedDocument::class, $document->id);

        return response()->view('active-search.document', compact('municipality', 'school', 'entry', 'document', 'verificationUrl', 'qrSvg'))
            ->header('Cache-Control', 'no-store, private');
    }

    public function revoke(Request $request, string $municipio, string $escola, string $matricula, string $documento): RedirectResponse
    {
        [$municipality, $school] = $this->managedSchool($request, $escola);
        $request->validate(['confirmar' => ['accepted']]);
        DB::connection('tenant')->transaction(function () use ($school, $matricula, $documento, $request): void {
            $entry = $this->enrollment($school, $matricula, true);
            $document = IssuedDocument::query()->where('kind', 'active_search_referral')
                ->where('school_id', $school->id)->where('enrollment_id', $entry->id)
                ->lockForUpdate()->findOrFail($documento);
            if ($document->revoked_at !== null) {
                throw ValidationException::withMessages(['confirmar' => 'O encaminhamento já foi revogado.']);
            }
            $document->fill(['revoked_at' => now(), 'revoked_by' => $request->user()->id,
                'revoke_reason' => 'Documento invalidado pela gestão escolar.'])->save();
            app(AuditTrail::class)->record('tenant', 'active_search.referral.revoked', IssuedDocument::class,
                $document->id, ['revoked_at' => null], $document->only(['revoked_at', 'revoked_by']));
        });

        return redirect()->route('school.active-search.document', [
            'municipio' => $municipality->slug, 'escola' => $school->id, 'matricula' => $matricula, 'documento' => $documento,
        ])->with('status', 'Encaminhamento revogado. A consulta pública informa a revogação.');
    }

    private function managedSchool(Request $request, string $id): array
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($id);
        Gate::authorize('manageStudents', [$school, $municipality]);

        return [$municipality, $school];
    }

    private function enrollment(School $school, string $id, bool $lock = false): StudentEnrollment
    {
        return StudentEnrollment::query()->where('school_id', $school->id)
            ->with(['student', 'schoolYear', 'schoolClass'])
            ->when($lock, fn ($query) => $query->lockForUpdate())->findOrFail($id);
    }

    private function selectedYear(Request $request, $years): ?SchoolYear
    {
        $input = $request->validate(['ano' => ['nullable', 'integer', 'between:2024,2100']]);
        if (! isset($input['ano'])) {
            return $this->alerts->year();
        }
        $year = $years->firstWhere('year', (int) $input['ano']);
        if ($year === null) {
            throw ValidationException::withMessages(['ano' => 'Ano letivo não cadastrado.']);
        }

        return $year;
    }

    private function backToCase(string $municipio, string $escola, string $matricula): RedirectResponse
    {
        return redirect()->route('school.active-search.detail', compact('municipio', 'escola', 'matricula'));
    }
}
