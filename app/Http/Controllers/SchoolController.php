<?php

namespace App\Http\Controllers;

use App\Auth\SchoolAccess;
use App\Models\Tenant\School;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SchoolController extends Controller
{
    public function index(Request $request, SchoolAccess $access): View
    {
        $municipality = $request->attributes->get('astuti.municipality');
        Gate::authorize('viewAny', [School::class, $municipality]);

        $visibleSchoolIds = $access->visibleSchoolIds($request->user(), $municipality);
        $query = School::query()->where('status', 'active');

        if ($visibleSchoolIds !== null) {
            $query->whereIn('id', $visibleSchoolIds);
        }

        return view('schools.index', [
            'municipality' => $municipality,
            'schools' => $query->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Request $request, string $municipio, string $escola): View
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($escola);
        Gate::authorize('view', [$school, $municipality]);

        return view('schools.show', ['municipality' => $municipality, 'school' => $school]);
    }
}
