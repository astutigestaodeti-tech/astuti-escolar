<?php

namespace App\Multiprofessional;

use App\Equity\EquityReadiness;
use App\Models\Tenant\MultiprofessionalAttendance;
use App\Models\Tenant\MultiprofessionalReferral;
use App\Models\Tenant\MultiprofessionalRequest;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\StudentEnrollment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TeamIndicators
{
    /** Agregados por competência. Não seleciona motivo, devolutiva ou nome de aluno. */
    public function forMonth(SchoolYear $year, Carbon $month): array
    {
        $from = $month->copy()->startOfMonth();
        $until = $month->copy()->endOfMonth()->min(now());
        $schools = School::query()->orderBy('name')->pluck('name', 'id');
        $requests = MultiprofessionalRequest::query()->where('school_year_id', $year->id)
            ->whereIn('school_id', $schools->keys())->where('opened_at', '<=', $until)
            ->get(['id', 'student_id', 'school_id', 'area', 'opened_at', 'closed_at'])->keyBy('id');

        $opened = $requests->filter(fn (MultiprofessionalRequest $row): bool => $row->opened_at->betweenIncluded($from, $until));
        $closed = $requests->filter(fn (MultiprofessionalRequest $row): bool => $row->closed_at?->betweenIncluded($from, $until) === true);
        $attendances = MultiprofessionalAttendance::query()->whereIn('request_id', $requests->keys())
            ->where('attended_on', '<=', $until->toDateString())->orderBy('attended_on')
            ->get(['request_id', 'attended_on']);
        $referrals = MultiprofessionalReferral::query()->whereIn('request_id', $requests->keys())
            ->whereBetween('referred_on', [$from->toDateString(), $until->toDateString()])
            ->get(['request_id', 'destination']);

        $attended = $attendances->filter(fn (MultiprofessionalAttendance $row): bool => $row->attended_on->gte($from))
            ->map(fn (MultiprofessionalAttendance $row): array => [
                'student_id' => $requests->get($row->request_id)->student_id,
            ]);
        $destinations = $referrals->map(fn (MultiprofessionalReferral $row): array => [
            'student_id' => $requests->get($row->request_id)->student_id, 'destination' => $row->destination,
        ]);
        // O tempo de espera é medido até o primeiro atendimento efetivamente
        // registrado, e não até a agenda. Considera primeiros atendimentos do mês.
        $first = $attendances->unique('request_id')->filter(fn (MultiprofessionalAttendance $row): bool => $row->attended_on->gte($from))
            ->map(function (MultiprofessionalAttendance $row) use ($requests): array {
                $entry = $requests->get($row->request_id);

                return [
                    'student_id' => $entry->student_id,
                    'days' => max(0, (int) $entry->opened_at->copy()->startOfDay()->diffInDays($row->attended_on)),
                ];
            });

        return [
            'opened' => $this->count($opened),
            'attended' => $this->count($attended),
            'referred' => $this->count($destinations),
            'closed' => $this->count($closed),
            'wait_days' => $first->pluck('student_id')->unique()->count() >= EquityReadiness::MIN_GROUP
                ? round($first->avg('days'), 1) : null,
            'schools' => $this->distribution($opened, 'school_id', $schools->all()),
            'areas' => $this->distribution($opened, 'area', MultiprofessionalRequest::AREAS),
            'destinations' => $this->distribution($destinations, 'destination', MultiprofessionalReferral::DESTINATIONS),
        ];
    }

    /**
     * Sinais administrativos, sem carregar a devolutiva nem motivo.
     * Não existe confirmação de leitura: "nova" significa os últimos sete dias.
     */
    public function schoolActionQuery(School $school, Carbon $day, string $type): Builder
    {
        $active = StudentEnrollment::query()->where('school_id', $school->id)->where('status', 'active')
            ->whereNull('ended_at')->whereHas('student', fn ($query) => $query->whereNull('deleted_at'))->select('id');
        $query = MultiprofessionalRequest::query()->where('school_id', $school->id)->whereIn('enrollment_id', $active);

        return match ($type) {
            'guidance' => $query->whereBetween('guidance_at', [$day->copy()->subDays(6)->startOfDay(), $day->copy()->endOfDay()->min(now())]),
            'waiting' => $query->where('status', '!=', 'closed')->where('opened_at', '<', $day->copy()->endOfDay()->min(now())->subDays(15))
                ->whereDoesntHave('attendances'),
        };
    }

    public function schoolActions(School $school, Carbon $day, bool $network = false): array
    {
        $result = [];
        foreach (['guidance', 'waiting'] as $type) {
            $query = $this->schoolActionQuery($school, $day, $type);
            $row = $query->selectRaw('count(*) as total, count(distinct student_id) as students')->first();
            $count = (int) $row->total;
            $result[$type] = $network && $count > 0 && (int) $row->students < EquityReadiness::MIN_GROUP ? null : $count;
        }

        return $result;
    }

    private function count(Collection $rows): ?int
    {
        if ($rows->isEmpty()) {
            return 0;
        }

        // Contar cinco atendimentos da mesma criança não cria um grupo seguro.
        return $rows->pluck('student_id')->unique()->count() >= EquityReadiness::MIN_GROUP ? $rows->count() : null;
    }

    private function distribution(Collection $rows, string $field, array $labels): array
    {
        $groups = $rows->groupBy($field);
        $result = collect($labels)->map(fn (string $label, string $key): array => [
            'label' => $label, 'count' => $this->count($groups->get($key, collect())),
        ]);
        if ($result->contains(fn (array $row): bool => $row['count'] === null)) {
            // Supressão complementar: ocultar também uma categoria positiva
            // evita revelar o grupo pequeno por subtração dos totais.
            $complement = $result->filter(fn (array $row): bool => ($row['count'] ?? 0) > 0)
                ->sortByDesc('count')->keys()->first();
            if ($complement !== null) {
                $row = $result->get($complement);
                $row['count'] = null;
                $result->put($complement, $row);
            }
        }

        return $result->values()->all();
    }
}
