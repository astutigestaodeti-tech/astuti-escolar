<?php

namespace App\Http\Controllers;

use App\Audit\AuditTrail;
use App\Models\Tenant\ActiveSearchCase;
use App\Models\Tenant\MultiprofessionalReferral;
use App\Models\Tenant\MultiprofessionalRequest;
use App\Models\Tenant\School;
use App\Models\Tenant\StudentEnrollment;
use App\Multiprofessional\TeamAccess;
use App\Multiprofessional\TeamWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MultiprofessionalController extends Controller
{
    public function __construct(private readonly TeamAccess $access) {}

    public function index(Request $request, string $municipio): Response
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $areas = $this->access->areas($request->user(), $municipality);
        abort_if($areas === [], 403);
        $filters = $request->validate([
            'situacao' => ['nullable', Rule::in(array_keys(MultiprofessionalRequest::STATUSES))],
            'agenda' => ['nullable', 'boolean'],
        ]);
        $agenda = (bool) ($filters['agenda'] ?? false);
        $requests = MultiprofessionalRequest::query()
            ->where(function (Builder $query) use ($request, $areas): void {
                $query->where(function (Builder $assigned) use ($request, $areas): void {
                    $assigned->where('assigned_to', $request->user()->id)->whereIn('area', $areas);
                })->orWhere(function (Builder $unassigned) use ($areas): void {
                    $unassigned->whereNull('assigned_to')->where('status', 'open')->whereIn('area', [...$areas, 'undetermined']);
                });
            })
            ->whereHas('student')->whereHas('school')
            ->when($filters['situacao'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when(! isset($filters['situacao']), fn ($query) => $query->where('status', '!=', 'closed'))
            ->when($agenda, fn ($query) => $query->where('status', 'scheduled')->whereNotNull('scheduled_at'))
            ->with(['student:id,full_name', 'school:id,name'])
            ->orderBy($agenda ? 'scheduled_at' : 'opened_at')->orderBy('id')
            ->paginate(50)->withQueryString();
        app(AuditTrail::class)->record('tenant', 'multiprofessional.queue.viewed');

        return response()->view('multiprofessional.index', compact('municipality', 'requests', 'filters', 'agenda'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, string $municipio, string $pedido): Response
    {
        [$municipality, $entry] = $this->authorized($request, $pedido);
        $entry->load(['student:id,full_name', 'school:id,name', 'attendances' => fn ($query) => $query->orderByDesc('attended_on'), 'referrals' => fn ($query) => $query->orderByDesc('referred_on')]);
        $areas = $this->access->areas($request->user(), $municipality);
        $owner = $entry->assigned_to === $request->user()->id;
        app(AuditTrail::class)->record('tenant', 'multiprofessional.request.viewed', MultiprofessionalRequest::class, $entry->id);

        return response()->view('multiprofessional.show', compact('municipality', 'entry', 'areas', 'owner'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, string $municipio, string $escola, string $matricula, TeamWorkflow $workflow): RedirectResponse
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($escola);
        Gate::authorize('view', [$school, $municipality]);
        $data = $request->validate([
            'area' => ['required', Rule::in(array_keys(MultiprofessionalRequest::AREAS))],
            'motivo' => ['required', Rule::in(array_keys(MultiprofessionalRequest::REASONS))],
        ]);
        $student = DB::connection('tenant')->transaction(function () use ($request, $municipality, $school, $matricula, $data, $workflow): string {
            $entry = $this->activeEnrollment($school, $matricula);
            abort_unless($this->access->schoolAccess($request->user(), $municipality, $school, $entry), 403);
            $workflow->open($entry, $data['area'], $data['motivo'], $request->user()->id);

            return $entry->student_id;
        });

        return redirect()->to(route('school.students.show', ['municipio' => $municipio, 'escola' => $escola, 'aluno' => $student]).'#equipe-multiprofissional')
            ->with('status', 'Pedido encaminhado à equipe. Pedidos já abertos na mesma área não são duplicados.');
    }

    public function fromActiveSearch(Request $request, string $municipio, string $escola, string $matricula, TeamWorkflow $workflow): RedirectResponse
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($escola);
        abort_unless($this->access->isDirector($request->user(), $municipality, $school), 403);
        DB::connection('tenant')->transaction(function () use ($request, $school, $matricula, $workflow): void {
            $entry = $this->activeEnrollment($school, $matricula);
            $case = ActiveSearchCase::query()->where('enrollment_id', $entry->id)
                ->where('school_id', $school->id)->where('student_id', $entry->student_id)
                ->whereIn('status', ['open', 'referred'])->lockForUpdate()->firstOrFail();
            $workflow->open($entry, 'social_work', 'attendance', $request->user()->id, $case->id);
        });

        return redirect()->route('school.active-search.detail', ['municipio' => $municipio, 'escola' => $escola, 'matricula' => $matricula])
            ->with('status', 'Pedido encaminhado à assistente social. Não foram copiados motivos ou contatos da busca ativa.');
    }

    public function claim(Request $request, string $municipio, string $pedido): RedirectResponse
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $areas = $this->access->areas($request->user(), $municipality);
        abort_if($areas === [], 403);
        $data = $request->validate(['area' => ['required', Rule::in($areas)]]);
        DB::connection('tenant')->transaction(function () use ($request, $pedido, $data): void {
            [, $entry] = $this->authorized($request, $pedido, true);
            abort_unless($entry->assigned_to === null && $entry->status === 'open', 409);
            abort_unless($entry->area === 'undetermined' || $entry->area === $data['area'], 403);
            // Serializar com novos pedidos e com outras atribuições desta matrícula.
            StudentEnrollment::query()->whereKey($entry->enrollment_id)->lockForUpdate()->firstOrFail();
            if (MultiprofessionalRequest::query()->where('enrollment_id', $entry->enrollment_id)
                ->where('area', $data['area'])->where('status', '!=', 'closed')->where('id', '!=', $entry->id)->exists()) {
                throw ValidationException::withMessages(['area' => 'Já existe pedido aberto para esta matrícula nesta área.']);
            }
            $entry->fill(['area' => $data['area'], 'assigned_to' => $request->user()->id, 'assigned_at' => now()])->save();
            $this->audit('assigned', $entry);
        });

        return $this->back($municipio, $pedido, 'Pedido assumido.');
    }

    public function schedule(Request $request, string $municipio, string $pedido): RedirectResponse
    {
        $data = $request->validate(['agenda' => ['required', 'date_format:Y-m-d\TH:i', 'after_or_equal:now']]);
        DB::connection('tenant')->transaction(function () use ($request, $pedido, $data): void {
            $entry = $this->owned($request, $pedido);
            $entry->fill(['scheduled_at' => Carbon::parse($data['agenda']), 'status' => 'scheduled'])->save();
            $this->audit('scheduled', $entry);
        });

        return $this->back($municipio, $pedido, 'Agendamento registrado.');
    }

    public function attend(Request $request, string $municipio, string $pedido): RedirectResponse
    {
        $data = $request->validate(['data' => ['required', 'date_format:Y-m-d', 'before_or_equal:today']]);
        DB::connection('tenant')->transaction(function () use ($request, $pedido, $data): void {
            $entry = $this->owned($request, $pedido);
            if ($data['data'] < $entry->opened_at->toDateString()) {
                throw ValidationException::withMessages(['data' => 'A data não pode ser anterior à abertura do pedido.']);
            }
            if ($entry->attendances()->where('attended_on', $data['data'])->exists()) {
                throw ValidationException::withMessages(['data' => 'Já existe atendimento registrado nesta data.']);
            }
            $entry->attendances()->create([
                'id' => (string) Str::uuid(), 'attended_on' => $data['data'], 'recorded_by' => $request->user()->id,
            ]);
            $entry->fill(['status' => 'attending'])->save();
            $this->audit('attendance.recorded', $entry);
        });

        return $this->back($municipio, $pedido, 'Atendimento registrado, sem conteúdo técnico.');
    }

    public function guidance(Request $request, string $municipio, string $pedido): RedirectResponse
    {
        $data = $request->validate(['devolutiva' => ['required', 'string', 'max:500']]);
        DB::connection('tenant')->transaction(function () use ($request, $pedido, $data): void {
            $entry = $this->owned($request, $pedido);
            $entry->fill(['school_guidance' => $data['devolutiva'], 'guidance_at' => now()])->save();
            $this->audit('guidance.saved', $entry);
        });

        return $this->back($municipio, $pedido, 'Orientação prática atualizada para a escola.');
    }

    public function refer(Request $request, string $municipio, string $pedido): RedirectResponse
    {
        $data = $request->validate([
            'destino' => ['required', Rule::in(array_keys(MultiprofessionalReferral::DESTINATIONS))],
            'data' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);
        DB::connection('tenant')->transaction(function () use ($request, $pedido, $data): void {
            $entry = $this->owned($request, $pedido);
            if ($data['data'] < $entry->opened_at->toDateString()) {
                throw ValidationException::withMessages(['data' => 'A data não pode ser anterior à abertura do pedido.']);
            }
            $entry->referrals()->create([
                'id' => (string) Str::uuid(), 'destination' => $data['destino'],
                'referred_on' => $data['data'], 'recorded_by' => $request->user()->id,
            ]);
            $entry->fill(['status' => 'referred'])->save();
            $this->audit('referral.recorded', $entry);
        });

        return $this->back($municipio, $pedido, 'Encaminhamento registrado. O envio é feito por canal institucional protegido.');
    }

    public function recordReturn(Request $request, string $municipio, string $pedido, string $encaminhamento): RedirectResponse
    {
        $data = $request->validate([
            'retorno' => ['required', Rule::in(array_keys(MultiprofessionalReferral::RETURNS))],
            'data' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);
        DB::connection('tenant')->transaction(function () use ($request, $pedido, $encaminhamento, $data): void {
            // Uma contrarreferência pode chegar depois do encerramento.
            $entry = $this->owned($request, $pedido, true);
            $referral = $entry->referrals()->whereKey($encaminhamento)->lockForUpdate()->firstOrFail();
            if ($data['data'] < $referral->referred_on->toDateString()) {
                throw ValidationException::withMessages(['data' => 'O retorno não pode anteceder o encaminhamento.']);
            }
            $referral->fill(['returned_on' => $data['data'], 'return_category' => $data['retorno']])->save();
            $this->audit('referral.returned', $entry);
        });

        return $this->back($municipio, $pedido, 'Contrarreferência registrada.');
    }

    public function close(Request $request, string $municipio, string $pedido): RedirectResponse
    {
        $data = $request->validate(['motivo' => ['required', Rule::in(array_keys(MultiprofessionalRequest::CLOSINGS))]]);
        DB::connection('tenant')->transaction(function () use ($request, $pedido, $data): void {
            $entry = $this->owned($request, $pedido);
            $entry->fill(['status' => 'closed', 'closed_at' => now(), 'closing_category' => $data['motivo']])->save();
            $this->audit('closed', $entry);
        });

        return $this->back($municipio, $pedido, 'Pedido encerrado.');
    }

    public function document(Request $request, string $municipio, string $pedido, string $encaminhamento): Response
    {
        [$municipality, $entry] = $this->authorized($request, $pedido);
        abort_unless($entry->assigned_to === $request->user()->id, 403);
        $entry->load(['student:id,full_name', 'school']);
        $referral = $entry->referrals()->whereKey($encaminhamento)->firstOrFail();
        $this->audit('referral.print.viewed', $entry);

        return response()->view('multiprofessional.document', compact('municipality', 'entry', 'referral'))
            ->header('Cache-Control', 'private, no-store');
    }

    private function activeEnrollment(School $school, string $id): StudentEnrollment
    {
        return StudentEnrollment::query()->where('school_id', $school->id)->where('status', 'active')
            ->whereNull('ended_at')->whereHas('student', fn ($query) => $query->whereNull('deleted_at'))
            ->with('schoolClass')->lockForUpdate()->findOrFail($id);
    }

    private function authorized(Request $request, string $id, bool $lock = false): array
    {
        $municipality = $request->attributes->get('astuti.municipality');
        abort_unless($this->access->isTeam($request->user(), $municipality), 403);
        $entry = MultiprofessionalRequest::query()->whereHas('student')->whereHas('school')
            ->when($lock, fn ($query) => $query->lockForUpdate())->findOrFail($id);
        abort_unless($this->access->mayOpen($request->user(), $municipality, $entry), 403);

        return [$municipality, $entry];
    }

    private function owned(Request $request, string $id, bool $allowClosed = false): MultiprofessionalRequest
    {
        [, $entry] = $this->authorized($request, $id, true);
        abort_unless($entry->assigned_to === $request->user()->id, 403);
        abort_if(! $allowClosed && $entry->status === 'closed', 409, 'Pedido encerrado.');

        return $entry;
    }

    private function audit(string $event, MultiprofessionalRequest $entry): void
    {
        // Nem motivo, nem devolutiva, nem destino ou conteúdo do encaminhamento.
        app(AuditTrail::class)->record('tenant', 'multiprofessional.'.$event, MultiprofessionalRequest::class, $entry->id);
    }

    private function back(string $municipio, string $pedido, string $message): RedirectResponse
    {
        return redirect()->route('team.show', compact('municipio', 'pedido'))->with('status', $message);
    }
}
