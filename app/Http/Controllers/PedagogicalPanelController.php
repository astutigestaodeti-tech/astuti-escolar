<?php

namespace App\Http\Controllers;

use App\Academics\PedagogicalAccess;
use App\Academics\PedagogicalPanel;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolYear;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PedagogicalPanelController extends Controller
{
    public function network(Request $request, string $municipio, PedagogicalPanel $panel): Response
    {
        $municipality = $request->attributes->get('astuti.municipality');
        Gate::authorize('viewNetworkPanel', [School::class, $municipality]);

        return $this->render($request, $municipality, $panel, null, false);
    }

    public function school(Request $request, string $municipio, string $escola, PedagogicalPanel $panel, PedagogicalAccess $access): Response
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($escola);
        $manager = Gate::allows('viewNetworkPanel', [School::class, $municipality]);
        abort_unless($manager || $access->directs($request->user(), $municipality, $school), 403);

        return $this->render($request, $municipality, $panel, $school, $request->boolean('nominal'));
    }

    private function render(Request $request, $municipality, PedagogicalPanel $panel, ?School $school, bool $nominal): Response
    {
        $years = SchoolYear::query()->orderByDesc('year')->get();
        $year = $request->filled('ano') ? $years->firstWhere('id', $request->query('ano')) : ($years->firstWhere('status', 'active') ?? $years->first());
        abort_if($request->filled('ano') && $year === null, 404);
        $data = $year === null ? null : $panel->forYear($year, $school, $nominal);
        $municipal = Gate::allows('viewNetworkPanel', [School::class, $municipality]);

        return response()->view('pedagogical.panel', compact('municipality', 'school', 'years', 'year', 'data', 'nominal', 'municipal'))
            ->header('Cache-Control', 'private, no-store');
    }
}
