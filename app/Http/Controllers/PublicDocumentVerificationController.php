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
    /** Tela para digitar o código, para quem tem o papel e não leu o QR Code. */
    public function form(Request $request): Response
    {
        $typed = $request->query('codigo');
        if (is_string($typed) && trim($typed) !== '') {
            return redirect()->route('documents.verify', ['codigo' => self::normalize($typed)]);
        }

        return response()->view('documents.verify', ['info' => null, 'askCode' => true])->header('X-Robots-Tag', 'noindex, nofollow')->header('Cache-Control', 'private, no-store');
    }

    /** Aceita o código digitado em minúsculas, com espaços, pontos ou hífens. */
    public static function normalize(string $code): string
    {
        return mb_substr((string) preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($code)), 0, 20);
    }

    public function __invoke(Request $request, string $codigo): Response
    {
        $codigo = self::normalize($codigo);
        $connections = config('tenancy.connections', []);
        $key = config('tenancy.deployed_db_key');
        // Sem a chave do município (notebook de teste), vale a única conexão configurada.
        // Com mais de uma Prefeitura configurada e sem chave, a consulta não adivinha.
        if ((! is_string($key) || $key === '') && is_array($connections) && count($connections) === 1) {
            $key = (string) array_key_first($connections);
        }
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

            if ($document->kind === 'school_transcript') {
                return [
                    'kind' => 'school_transcript',
                    'status' => $document->revoked_at === null ? 'Documento válido' : 'Documento revogado',
                    'school' => $document->snapshot_school_name,
                    'issued' => $document->issued_at->format('d/m/Y'),
                ];
            }

            return [
                'kind' => 'enrollment_declaration',
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

        return response()->view('documents.verify', ['info' => $info])->header('X-Robots-Tag', 'noindex, nofollow')->header('Cache-Control', 'private, no-store');
    }

    private function notFound(): Response
    {
        return response()->view('documents.verify', ['info' => null], 404)->header('X-Robots-Tag', 'noindex, nofollow')->header('Cache-Control', 'private, no-store');
    }
}
