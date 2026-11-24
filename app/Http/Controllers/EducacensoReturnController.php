<?php

namespace App\Http\Controllers;

use App\Audit\AuditTrail;
use App\Educacenso\RetornoDoEducacenso;
use App\Educacenso\Tabelas;
use App\Models\Tenant\InepIdentifier;
use App\Models\Tenant\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Retorno do Educacenso: lê o arquivo de exportação, mostra o que será
 * vinculado e só grava depois da confirmação. O arquivo não é guardado;
 * entre a conferência e a confirmação, a sessão guarda só os códigos e os
 * identificadores internos. Exclusivo da gestão municipal, com segundo
 * fator recente.
 */
class EducacensoReturnController extends Controller
{
    private const SESSAO = 'educacenso_retorno';

    private const LIMITE_DA_LISTA = 300;

    public function create(Request $request, string $municipio): Response
    {
        $municipality = $this->authorize($request);
        $request->session()->forget(self::SESSAO);

        return response()->view('census.return-form', [
            'municipality' => $municipality,
            'ano' => Tabelas::anoMaisRecente(),
            'gravados' => InepIdentifier::query()->selectRaw('kind, count(*) as total')->groupBy('kind')->pluck('total', 'kind'),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function preview(Request $request, string $municipio): Response
    {
        $municipality = $this->authorize($request);
        $request->validate([
            'arquivo' => ['required', 'file', 'max:'.(RetornoDoEducacenso::TAMANHO_MAXIMO / 1024), 'extensions:txt,zip'],
        ], [
            'arquivo.required' => 'Escolha o arquivo de exportação baixado do Educacenso.',
            'arquivo.max' => 'O arquivo passa de 20 MB.',
            'arquivo.extensions' => 'Envie o .txt ou o .zip baixado do Educacenso.',
        ]);
        $arquivo = $request->file('arquivo');
        $ano = Tabelas::anoMaisRecente();
        $retorno = RetornoDoEducacenso::doArquivo((string) $arquivo->getRealPath(), (string) $arquivo->getClientOriginalName(), $ano);

        $token = (string) Str::uuid();
        $plano = $retorno->erros === [] ? $retorno->plano() : [];
        $request->session()->put(self::SESSAO, [
            'token' => $token, 'ano' => $ano, 'sha256' => $retorno->sha256, 'plano' => $plano,
            'contagem' => $retorno->contagem(), 'escolas' => $retorno->escolas, 'fora' => count($retorno->escolasDeFora),
        ]);

        return response()->view('census.return-preview', [
            'municipality' => $municipality,
            'retorno' => $retorno,
            'contagem' => $retorno->contagem(),
            'porTipo' => collect($plano)->countBy('tipo'),
            'problemas' => $retorno->problemas()->take(self::LIMITE_DA_LISTA),
            'totalProblemas' => $retorno->problemas()->count(),
            'token' => $token,
            'temPlano' => $plano !== [],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, string $municipio): RedirectResponse
    {
        $municipality = $this->authorize($request);
        $sessao = $request->session()->get(self::SESSAO);
        if (! is_array($sessao) || ! hash_equals((string) $sessao['token'], (string) $request->input('token'))) {
            throw ValidationException::withMessages(['arquivo' => 'A conferência expirou. Envie o arquivo de novo.']);
        }
        $request->session()->forget(self::SESSAO);
        $usuario = (string) $request->user()->getAuthIdentifier();

        $resultado = DB::connection('tenant')->transaction(function () use ($sessao, $usuario): array {
            $gravados = $ignorados = 0;
            $porTipo = array_fill_keys(array_keys(InepIdentifier::KINDS), 0);
            foreach ($sessao['plano'] as $item) {
                if (! isset(InepIdentifier::KINDS[$item['tipo']])) {
                    continue;
                }
                // Revalida na hora: algo pode ter sido gravado depois da conferência.
                $existe = InepIdentifier::query()->where('kind', $item['tipo'])->where('entity_id', $item['entidade'])->lockForUpdate()->exists()
                    || ($item['tipo'] !== InepIdentifier::STAFF_MEMBER
                        && InepIdentifier::query()->where('kind', $item['tipo'])->where('inep_code', $item['codigo'])->exists());
                if ($existe) {
                    $ignorados++;

                    continue;
                }
                InepIdentifier::query()->create([
                    'id' => (string) Str::uuid(), 'kind' => $item['tipo'], 'entity_id' => $item['entidade'],
                    'inep_code' => $item['codigo'], 'census_year' => $sessao['ano'], 'imported_by' => $usuario,
                ]);
                $gravados++;
                $porTipo[$item['tipo']]++;
            }
            app(AuditTrail::class)->record('tenant', 'educacenso_return.imported', 'educacenso_return', null, null, [
                'file_sha256' => $sessao['sha256'], 'census_year' => $sessao['ano'], 'schools' => $sessao['escolas'],
                'linked' => $gravados, 'skipped' => $ignorados,
                'students' => $porTipo[InepIdentifier::STUDENT], 'staff' => $porTipo[InepIdentifier::STAFF_MEMBER],
                'classes' => $porTipo[InepIdentifier::SCHOOL_CLASS], 'enrollments' => $porTipo[InepIdentifier::ENROLLMENT],
                'conflicts' => $sessao['contagem'][RetornoDoEducacenso::CONFLITO] ?? 0,
                'mismatches' => $sessao['contagem'][RetornoDoEducacenso::DIVERGENTE] ?? 0,
                'not_found' => $sessao['contagem'][RetornoDoEducacenso::NAO_ENCONTRADO] ?? 0,
            ]);

            return [$gravados, $ignorados];
        });

        [$gravados, $ignorados] = $resultado;
        $mensagem = "Retorno do Educacenso importado: {$gravados} código(s) gravado(s).";
        if ($ignorados > 0) {
            $mensagem .= " {$ignorados} já tinham sido gravados por outra importação e foram mantidos.";
        }

        return redirect()->route('census.return.create', ['municipio' => $municipality->slug])->with('status', $mensagem);
    }

    private function authorize(Request $request): mixed
    {
        $municipality = $request->attributes->get('astuti.municipality');
        Gate::authorize('viewNetworkPanel', [School::class, $municipality]);

        return $municipality;
    }
}
