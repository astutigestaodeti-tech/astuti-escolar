<?php

namespace App\Http\Controllers;

use App\Audit\AuditTrail;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolYear;
use App\Multiprofessional\TeamIndicators;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MultiprofessionalIndicatorsController extends Controller
{
    public function index(Request $request, string $municipio, TeamIndicators $indicators): Response
    {
        $municipality = $request->attributes->get('astuti.municipality');
        Gate::authorize('viewNetworkPanel', [School::class, $municipality]);
        $input = $request->validate(['mes' => ['nullable', 'date_format:Y-m']]);
        $month = Carbon::createFromFormat('!Y-m', $input['mes'] ?? today()->format('Y-m'));
        if ($month->gt(today())) {
            throw ValidationException::withMessages(['mes' => 'Selecione o mês atual ou um mês anterior.']);
        }
        $year = SchoolYear::query()->where('year', $month->year)->first();
        $teamIndicators = $year === null ? null : $indicators->forMonth($year, $month);
        app(AuditTrail::class)->record('tenant', 'multiprofessional.indicators.viewed');

        return response()->view('multiprofessional.indicators', compact('municipality', 'month', 'teamIndicators'))
            ->header('Cache-Control', 'private, no-store');
    }
}
