<?php

namespace App\Http\Controllers;

use App\Auth\SchoolAccess;
use App\Models\Platform\Membership;
use App\Models\Platform\Municipality;
use App\Multiprofessional\TeamAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request, SchoolAccess $access): View|RedirectResponse
    {
        $redes = collect();
        $teamOnlySlugs = [];
        $user = $request->user();

        // Não há página de apresentação: quem não entrou vai direto para o login.
        if ($user === null) {
            return redirect()->route('login');
        }

        if ($user->status === 'active') {
            $municipalityIds = Membership::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->distinct()
                ->pluck('municipality_id');

            $redes = Municipality::query()
                ->whereIn('id', $municipalityIds)
                ->where('status', 'active')
                ->orderBy('legal_name')
                ->get()
                ->filter(fn (Municipality $municipality) => $access->hasMunicipalAccess($user, $municipality)
                    || app(TeamAccess::class)->isTeam($user, $municipality));
            $teamOnlySlugs = $redes->reject(fn (Municipality $municipality) => $access->hasMunicipalAccess($user, $municipality))
                ->pluck('slug')->all();
        }

        // Cada instalação atende uma Prefeitura: com uma rede só, escolher é um clique a mais.
        if ($redes->count() === 1) {
            $slug = $redes->first()->slug;

            return redirect()->route(in_array($slug, $teamOnlySlugs, true) ? 'team.index' : 'schools.index', ['municipio' => $slug]);
        }

        return view('welcome', compact('redes', 'teamOnlySlugs'));
    }
}
