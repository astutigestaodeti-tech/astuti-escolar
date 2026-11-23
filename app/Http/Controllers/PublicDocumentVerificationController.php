<?php

namespace App\Http\Controllers;

use App\Documents\VerificationCode;
use App\Models\Platform\Municipality;
use App\Models\Tenant\IssuedDocument;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class PublicDocumentVerificationController extends Controller
{
    public function __invoke(Request $request, string $codigo): Response
    {
        $key = config('tenancy.deployed_db_key');
        $connections = config('tenancy.connections', []);
        if (! is_string($key) || ! is_array($connections) || ! isset($connections[$key])
            || preg_match('/\A[A-HJ-NP-Z2-9]{10}\z/D', $codigo) !== 1) {
            return $this->notFound();
        }

        $municipality = Municipality::query()->where('db_key', $key)->where('status', 'active')->first();
        if ($municipality === null) {
            return $this->notFound();
        }

        $info = app(TenantContext::class)->runFor($municipality, function () use ($codigo): ?array {
            $document = IssuedDocument::query()->where('verification_code', $codigo)->first();
            if ($document === null) {
                return null;
            }

            return [
                'status' => $document->revoked_at === null ? 'Documento válido' : 'Documento revogado',
                'student' => VerificationCode::abbreviate($document->snapshot_student_name),
                'school' => $document->snapshot_school_name,
                'year' => $document->snapshot_school_year,
                'stage' => $document->snapshot_stage,
                'issued' => $document->issued_at->format('d/m/Y'),
            ];
        });

        if ($info === null) {
            return $this->notFound();
        }

        return response()->view('documents.verify', ['info' => $info])->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function notFound(): Response
    {
        return response()->view('documents.verify', ['info' => null], 404)->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
