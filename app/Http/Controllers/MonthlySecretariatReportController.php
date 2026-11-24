<?php

namespace App\Http\Controllers;

use App\Audit\AuditTrail;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolYear;
use App\Reports\MonthlySecretariatReport;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MonthlySecretariatReportController extends Controller
{
    public function index(Request $request, string $municipio, MonthlySecretariatReport $report): Response
    {
        $municipality = $request->attributes->get('astuti.municipality');
        Gate::authorize('viewNetworkPanel', [School::class, $municipality]);
        $input = $request->validate(['mes' => ['nullable', 'date_format:Y-m']]);
        $month = Carbon::createFromFormat('!Y-m', $input['mes'] ?? today()->format('Y-m'));
        if ($month->gt(today())) {
            throw ValidationException::withMessages(['mes' => 'Selecione o mês atual ou um mês anterior.']);
        }
        $year = SchoolYear::query()->where('year', $month->year)->first();
        $data = $year === null ? null : $report->forMonth($year, $month);

        app(AuditTrail::class)->record('tenant', 'secretariat.monthly_report.viewed');

        return response()->view('reports.secretariat-monthly', compact('municipality', 'month', 'year', 'data'))
            ->header('Cache-Control', 'no-store, private');
    }
}
