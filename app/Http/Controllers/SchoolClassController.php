<?php

namespace App\Http\Controllers;

use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SchoolClassController extends Controller
{
    public function index(Request $request, string $municipio, string $escola): View
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($escola);

        Gate::authorize('view', [$school, $municipality]);

        $classes = SchoolClass::query()
            ->where('school_id', $school->id)
            ->where('status', 'active')
            ->with('schoolYear')
            ->orderBy('name')
            ->get();

        return view('classes.index', compact('municipality', 'school', 'classes'));
    }
}
