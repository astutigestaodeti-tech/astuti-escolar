<?php

namespace App\Http\Controllers;

use App\Models\Tenant\School;
use App\Services\PostalCodeLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PostalCodeLookupController extends Controller
{
    public function show(Request $request, string $municipio, string $escola, string $cep, PostalCodeLookupService $lookup): JsonResponse
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($escola);
        Gate::authorize('manageStudents', [$school, $municipality]);

        if (preg_match('/\\A(?:\\d{8}|\\d{5}-\\d{3})\\z/', $cep) !== 1) {
            return response()->json(['message' => 'Informe um CEP com oito números.'], 422);
        }

        [$data, $status] = $lookup->lookup(str_replace('-', '', $cep));

        return response()->json($data, $status);
    }
}
