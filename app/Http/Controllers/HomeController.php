<?php

namespace App\Http\Controllers;

use App\Auth\SchoolAccess;
use App\Models\Platform\Membership;
use App\Models\Platform\Municipality;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request, SchoolAccess $access): View|RedirectResponse
    {
        $redes = collect();
        $user = $request->user();

        if ($user !== null && $user->status === 'active') {
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
                ->filter(fn (Municipality $municipality) => $access->hasMunicipalAccess($user, $municipality));
        }

        // Cada instalação atende uma Prefeitura: com uma rede só, escolher é um clique a mais.
        if ($redes->count() === 1) {
            return redirect()->route('schools.index', ['municipio' => $redes->first()->slug]);
        }

        return view('welcome', ['redes' => $redes]);
    }
}
