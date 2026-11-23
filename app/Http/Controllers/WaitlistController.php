<?php

namespace App\Http\Controllers;

use App\Address\BrazilianStates;
use App\Audit\AuditTrail;
use App\Auth\WaitlistAccess;
use App\Documents\VerificationCode;
use App\Models\Tenant\School;
use App\Models\Tenant\WaitlistRequest;
use App\Services\PostalCodeLookupService;
use App\Validation\Cpf;
use App\Waitlist\StageCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WaitlistController extends Controller
{
    private const OPEN_STATUSES = ['aguardando', 'convocado'];

    private const SHIFTS = ['manha' => 'Manhã', 'tarde' => 'Tarde', 'integral' => 'Integral'];

    public function index(Request $request, string $municipio, WaitlistAccess $access, StageCalculator $calculator): Response
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $user = $request->user();

        abort_unless($access->canView($user, $municipality), 403);
        $visible = $access->visibleSchoolIds($user, $municipality);
        $filters = $request->validate([
            'escola' => ['nullable', 'uuid', Rule::in($visible)],
            'etapa' => ['nullable', Rule::in(array_keys(WaitlistRequest::STAGES))],
            'situacao' => ['nullable', Rule::in(['abertos', 'todos', ...array_keys(WaitlistRequest::STATUSES)])],
            'busca' => ['nullable', 'string', 'max:120'],
            'ano' => ['nullable', 'integer', 'between:2020,2100'],
        ]);

        $year = (int) ($filters['ano'] ?? $calculator->cutoff()->year);
        $status = $filters['situacao'] ?? 'abertos';
        $search = trim((string) ($filters['busca'] ?? ''));

        // A posição é calculada ANTES dos filtros e da busca. Pedidos encerrados
        // ficam em partição separada e jamais alteram a posição dos abertos.
        // Uma inscrição aparece uma vez em cada escola de preferência.
        $ranked = DB::connection('tenant')->table('waitlist_requests as pedido')
            ->join('waitlist_preferences as pref', 'pref.waitlist_request_id', '=', 'pedido.id')
            ->join('schools as escola', 'escola.id', '=', 'pref.school_id')
            ->whereIn('pref.school_id', $visible)
            ->where('escola.status', 'active')
            ->whereNull('pedido.deleted_at')
            ->select([
                'pedido.id', 'pedido.protocol', 'pedido.child_name', 'pedido.guardian_name',
                'pedido.stage', 'pedido.intake_year', 'pedido.status',
                'pedido.priority_count', 'pedido.requested_at',
                'pref.school_id', 'pref.preference_order', 'escola.name as school_name',
            ])
            ->selectRaw("CASE WHEN pedido.status IN ('aguardando', 'convocado')
                THEN ROW_NUMBER() OVER (
                    PARTITION BY pref.school_id, pedido.intake_year, pedido.stage,
                        (pedido.status IN ('aguardando', 'convocado'))
                    ORDER BY pedido.priority_count DESC, pedido.requested_at, pedido.id
                ) ELSE NULL END AS queue_position");

        $items = DB::connection('tenant')->query()->fromSub($ranked, 'fila')
            ->where('fila.intake_year', $year)
            ->when(isset($filters['escola']), fn ($q) => $q->where('fila.school_id', $filters['escola']))
            ->when(isset($filters['etapa']), fn ($q) => $q->where('fila.stage', $filters['etapa']))
            ->when($status === 'abertos', fn ($q) => $q->whereIn('fila.status', self::OPEN_STATUSES))
            ->when($status !== 'abertos' && $status !== 'todos', fn ($q) => $q->where('fila.status', $status))
            ->when($search !== '', function ($q) use ($search): void {
                $pattern = '%'.addcslashes($search, '%_').'%';
                $q->where(function ($q) use ($pattern): void {
                    $q->where('fila.child_name', 'ilike', $pattern)
                        ->orWhere('fila.guardian_name', 'ilike', $pattern)
                        ->orWhere('fila.protocol', 'ilike', $pattern);
                });
            })
            ->orderBy('fila.school_name')->orderBy('fila.stage')
            ->orderByRaw('fila.queue_position ASC NULLS LAST')
            ->orderBy('fila.requested_at')->paginate(50)->withQueryString();

        $schools = School::query()->whereIn('id', $visible)->where('status', 'active')->orderBy('name')->get(['id', 'name']);
        app(AuditTrail::class)->record('tenant', 'waitlist.list.viewed');

        return response()->view('waitlist.index', [
            'municipality' => $municipality,
            'items' => $items,
            'schools' => $schools,
            'filters' => $filters,
            'search' => $search,
            'year' => $year,
            'status' => $status,
            'canCreate' => $access->canCreate($user, $municipality),
            'stages' => WaitlistRequest::STAGES,
            'statuses' => WaitlistRequest::STATUSES,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function create(Request $request, string $municipio, WaitlistAccess $access, StageCalculator $calculator): Response
    {
        $municipality = $request->attributes->get('astuti.municipality');
        abort_unless($access->canCreate($request->user(), $municipality), 403);

        $eligible = $access->offeringSchoolIds();
        $schools = School::query()->whereIn('id', $eligible)->where('status', 'active')->orderBy('name')->get(['id', 'name']);

        return response()->view('waitlist.create', [
            'municipality' => $municipality,
            'schools' => $schools,
            'firstChoiceIds' => $access->writableSchoolIds($request->user(), $municipality),
            'cutoff' => $calculator->cutoff(),
            'priorities' => WaitlistRequest::PRIORITIES,
            'shifts' => self::SHIFTS,
            'states' => BrazilianStates::NAMES,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(
        Request $request,
        string $municipio,
        WaitlistAccess $access,
        StageCalculator $calculator,
    ): RedirectResponse {
        $municipality = $request->attributes->get('astuti.municipality');
        abort_unless($access->canCreate($request->user(), $municipality), 403);

        $rawCpf = trim((string) $request->input('cpf', ''));
        $request->merge([
            'child_name' => $this->cleanName($request->input('child_name')),
            'guardian_name' => $this->cleanName($request->input('guardian_name')),
            'cpf' => $rawCpf === '' ? null : (Cpf::normalize($rawCpf) ?? $rawCpf),
            'guardian_phone' => preg_replace('/\D/', '', (string) $request->input('guardian_phone')),
            'postal_code' => $this->formatCep($request->input('postal_code')),
        ]);

        // Os dois campos de preferência opcionais chegam vazios no HTML.
        // Não contam como escolhas nem acionam a validação de UUID.
        $preferences = $request->input('preferences', []);
        if (is_array($preferences)) {
            $request->merge(['preferences' => array_values(array_filter(
                $preferences,
                fn ($value): bool => is_string($value) && trim($value) !== '',
            ))]);
        }

        $data = $request->validate([
            'child_name' => ['required', 'string', 'min:5', 'max:180'],
            'birth_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'cpf' => ['nullable', new Cpf],
            'guardian_name' => ['required', 'string', 'min:5', 'max:180'],
            'guardian_phone' => ['required', 'regex:/\A[1-9]\d{9,10}\z/'],
            'postal_code' => ['required', 'regex:/\A\d{5}-\d{3}\z/'],
            'address_street' => ['required', 'string', 'max:180'],
            'address_number' => ['required', 'string', 'max:20'],
            'address_complement' => ['nullable', 'string', 'max:180'],
            'address_district' => ['required', 'string', 'max:120'],
            'address_city' => ['required', 'string', 'max:120'],
            'address_state' => ['required', 'size:2', BrazilianStates::VALIDATION_RULE],
            'desired_shift' => ['required', Rule::in(array_keys(self::SHIFTS))],
            'preferences' => ['required', 'array', 'min:1', 'max:3'],
            'preferences.*' => ['required', 'uuid', 'distinct'],
            'priority_flags' => ['nullable', 'array'],
            'priority_flags.*' => ['nullable', 'in:1'],
            'priority_reasons' => ['nullable', 'array'],
            'priority_reasons.*' => ['nullable', 'string', 'max:200'],
            'confirm_stage' => ['accepted'],
            'confirm_duplicate' => ['nullable', 'boolean'],
        ], [
            'confirm_stage.accepted' => 'Confira e confirme a etapa calculada pela data de corte.',
            'guardian_phone.regex' => 'Informe um telefone com DDD, de 10 ou 11 números.',
            'postal_code.regex' => 'Informe um CEP no formato 00000-000.',
        ]);

        $stage = $calculator->stageFor($data['birth_date']);
        if ($stage === null) {
            throw ValidationException::withMessages([
                'birth_date' => 'A data de nascimento não corresponde às etapas de educação infantil deste ano de ingresso.',
            ]);
        }

        $schools = array_values(array_unique($data['preferences']));
        $available = $access->offeringSchoolIds();
        if (array_diff($schools, $available) !== []) {
            throw ValidationException::withMessages(['preferences' => 'Selecione somente escolas ativas que ofereçam educação infantil.']);
        }
        if (! in_array($schools[0], $access->writableSchoolIds($request->user(), $municipality), true)) {
            abort(403, 'A primeira escola deve estar no escopo de cadastro da sua unidade.');
        }

        $reasons = $data['priority_reasons'] ?? [];
        $flags = $data['priority_flags'] ?? [];
        if (array_diff(array_keys($flags), array_keys(WaitlistRequest::PRIORITIES)) !== []
            || array_diff(array_keys($reasons), array_keys(WaitlistRequest::PRIORITIES)) !== []) {
            throw ValidationException::withMessages(['priority_flags' => 'Selecione somente critérios de prioridade disponíveis.']);
        }

        $criteria = [];
        foreach (WaitlistRequest::PRIORITIES as $code => $label) {
            if (($flags[$code] ?? null) === '1' || ($flags[$code] ?? null) === 1) {
                $justification = trim((string) ($reasons[$code] ?? ''));
                if (mb_strlen($justification) < 10) {
                    throw ValidationException::withMessages([
                        'priority_reasons.'.$code => 'Explique brevemente o critério selecionado, com pelo menos 10 caracteres.',
                    ]);
                }
                $criteria[$code] = $justification;
            }
        }

        if (! $request->boolean('confirm_duplicate') && $this->hasOpenDuplicate($data)) {
            return back()->withInput()->with('waitlist_duplicate_warning', true)->withErrors([
                'child_name' => 'Existe um pedido aberto com estes dados. Confira antes de registrar. Se for outro pedido legítimo, marque a confirmação e envie novamente.',
            ]);
        }

        $cutoff = $calculator->cutoff();
        $record = DB::connection('tenant')->transaction(function () use ($request, $data, $stage, $cutoff, $criteria, $schools): WaitlistRequest {
            $record = WaitlistRequest::query()->create([
                'id' => (string) Str::uuid(),
                'protocol' => VerificationCode::generate(),
                'child_name' => $data['child_name'],
                'birth_date' => $data['birth_date'],
                'cpf' => $data['cpf'] ?? null,
                'guardian_name' => $data['guardian_name'],
                'guardian_phone' => $data['guardian_phone'],
                'postal_code' => $data['postal_code'],
                'address_street' => trim($data['address_street']),
                'address_number' => trim($data['address_number']),
                'address_complement' => $data['address_complement'] ?? null,
                'address_district' => trim($data['address_district']),
                'address_city' => trim($data['address_city']),
                'address_state' => $data['address_state'],
                'intake_year' => $cutoff->year,
                'cutoff_date' => $cutoff->format('Y-m-d'),
                'stage' => $stage,
                'desired_shift' => $data['desired_shift'],
                'priority_criteria' => $criteria,
                'priority_count' => count($criteria),
                'status' => 'aguardando',
                'requested_at' => now(),
                'created_by' => $request->user()->id,
            ]);

            foreach ($schools as $position => $schoolId) {
                $record->preferences()->create([
                    'id' => (string) Str::uuid(),
                    'school_id' => $schoolId,
                    'preference_order' => $position + 1,
                ]);
            }

            app(AuditTrail::class)->record('tenant', 'waitlist.created', WaitlistRequest::class, $record->id, null, [
                'status' => $record->status,
                'stage' => $record->stage,
                'intake_year' => $record->intake_year,
                'requested_at' => $record->requested_at,
                'cpf' => $record->cpf,
                'guardian_phone' => $record->guardian_phone,
                'priority_criteria' => $record->priority_criteria,
            ]);

            return $record;
        });

        return redirect()->route('waitlist.show', ['municipio' => $municipality->slug, 'pedido' => $record->id])
            ->with('status', 'Pedido de vaga registrado. Informe à família o protocolo '.$record->protocol.'.');
    }

    public function show(Request $request, string $municipio, string $pedido, WaitlistAccess $access): Response
    {
        [$municipality, $record, $canChange] = $this->authorizedRequest($request, $pedido, $access);
        $record->load(['preferences.school']);
        $history = DB::connection('tenant')->table('waitlist_status_changes')
            ->where('waitlist_request_id', $record->id)->orderByDesc('changed_at')->get();

        app(AuditTrail::class)->record('tenant', 'waitlist.viewed', WaitlistRequest::class, $record->id);

        return response()->view('waitlist.show', [
            'municipality' => $municipality,
            'record' => $record,
            'history' => $history,
            'canChange' => $canChange,
            'statuses' => WaitlistRequest::STATUSES,
            'stages' => WaitlistRequest::STAGES,
            'priorities' => WaitlistRequest::PRIORITIES,
            'shifts' => self::SHIFTS,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function changeStatus(
        Request $request,
        string $municipio,
        string $pedido,
        WaitlistAccess $access,
    ): RedirectResponse {
        [$municipality, $record, $canChange] = $this->authorizedRequest($request, $pedido, $access);
        abort_unless($canChange, 403);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(WaitlistRequest::STATUSES))],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        DB::connection('tenant')->transaction(function () use ($record, $data, $request): void {
            $locked = WaitlistRequest::query()->lockForUpdate()->findOrFail($record->id);
            $before = $locked->status;
            if ($before === $data['status']) {
                throw ValidationException::withMessages(['status' => 'Escolha uma situação diferente da atual.']);
            }

            $locked->status = $data['status'];
            $locked->save();

            DB::connection('tenant')->table('waitlist_status_changes')->insert([
                'id' => (string) Str::uuid(),
                'waitlist_request_id' => $locked->id,
                'previous_status' => $before,
                'new_status' => $locked->status,
                'reason' => trim($data['reason']),
                'changed_by' => $request->user()->id,
                'changed_at' => now(),
            ]);
            app(AuditTrail::class)->record('tenant', 'waitlist.status.changed', WaitlistRequest::class, $locked->id,
                ['status' => $before], ['status' => $locked->status, 'status_reason' => $data['reason']]);
        });

        return redirect()->route('waitlist.show', ['municipio' => $municipality->slug, 'pedido' => $record->id])
            ->with('status', 'Situação atualizada com justificativa e auditoria.');
    }

    public function archive(
        Request $request,
        string $municipio,
        string $pedido,
        WaitlistAccess $access,
    ): RedirectResponse {
        [$municipality, $record, $canChange] = $this->authorizedRequest($request, $pedido, $access);
        abort_unless($canChange, 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']]);

        DB::connection('tenant')->transaction(function () use ($record, $data, $request): void {
            $locked = WaitlistRequest::query()->lockForUpdate()->findOrFail($record->id);
            // O motivo fica no pedido (que pode conter dado sensível) e não vai para a auditoria.
            $locked->forceFill([
                'deleted_at' => now(),
                'deleted_by' => $request->user()->id,
                'deletion_reason' => trim($data['reason']),
            ])->save();

            app(AuditTrail::class)->record('tenant', 'waitlist.archived', WaitlistRequest::class, $locked->id,
                ['deleted_at' => null], ['deleted_at' => $locked->deleted_at]);
        });

        return redirect()->route('waitlist.index', ['municipio' => $municipality->slug])
            ->with('status', 'Pedido arquivado sem exclusão dos registros.');
    }

    public function postalLookup(
        Request $request,
        string $municipio,
        string $cep,
        WaitlistAccess $access,
        PostalCodeLookupService $lookup,
    ): JsonResponse {
        $municipality = $request->attributes->get('astuti.municipality');
        abort_unless($access->canCreate($request->user(), $municipality), 403);

        if (preg_match('/\A(?:\d{8}|\d{5}-\d{3})\z/', $cep) !== 1) {
            return response()->json(['message' => 'Informe um CEP com oito números.'], 422);
        }

        [$data, $status] = $lookup->lookup(str_replace('-', '', $cep));

        return response()->json($data, $status)->header('Cache-Control', 'private, no-store');
    }

    /** @return array{0: mixed, 1: WaitlistRequest, 2: bool} */
    private function authorizedRequest(Request $request, string $id, WaitlistAccess $access): array
    {
        $municipality = $request->attributes->get('astuti.municipality');
        abort_unless($access->canView($request->user(), $municipality), 403);

        $visible = $access->visibleSchoolIds($request->user(), $municipality);
        $record = WaitlistRequest::query()->whereHas('preferences', fn ($query) => $query->whereIn('school_id', $visible))
            ->findOrFail($id);

        $writable = $access->writableSchoolIds($request->user(), $municipality);
        $canChange = $record->preferences()->whereIn('school_id', $writable)->exists();

        return [$municipality, $record, $canChange];
    }

    private function cleanName(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return preg_replace('/\s+/u', ' ', trim($value));
    }

    private function formatCep(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }
        $digits = preg_replace('/\D/', '', $value);

        return is_string($digits) && strlen($digits) === 8
            ? substr($digits, 0, 5).'-'.substr($digits, 5)
            : $value;
    }

    private function hasOpenDuplicate(array $data): bool
    {
        return WaitlistRequest::query()->whereIn('status', self::OPEN_STATUSES)
            ->where(function ($q) use ($data): void {
                $q->where(function ($q) use ($data): void {
                    $q->whereRaw('lower(child_name) = ?', [mb_strtolower($data['child_name'])])
                        ->whereDate('birth_date', $data['birth_date']);
                });
                if (! empty($data['cpf'])) {
                    $q->orWhere('cpf', $data['cpf']);
                }
            })->exists();
    }
}
