<?php

namespace App\Http\Controllers;

use App\Educacenso\MontagemDaEscola;
use App\Educacenso\Tabelas;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolYear;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class EducacensoSchoolController extends Controller
{
    public function show(Request $request, string $municipio, string $escola, MontagemDaEscola $montagem): Response
    {
        $municipality = $request->attributes->get('astuti.municipality');
        Gate::authorize('viewNetworkPanel', [School::class, $municipality]);
        $school = School::query()->where('status', 'active')->findOrFail($escola);
        $edicao = Tabelas::anoMaisRecente();
        $ano = SchoolYear::query()->where('year', $edicao)->first();
        $resultado = $montagem->para($school, $ano);
        $parametros = ['municipio' => $municipality->slug, 'escola' => $school->id];
        $grupos = collect($resultado['pendencias'])->map(function (array $item) use ($parametros): array {
            $p = $item['pendencia'];
            $origem = $item['origem'];
            $url = match ($origem['tipo']) {
                'infraestrutura' => route('school.census.infrastructure.edit', $parametros),
                'turma' => route('school.census.classes.edit', [...$parametros, 'turma' => $origem['id']]),
                'aluno' => route('school.students.census.edit', [...$parametros, 'aluno' => $origem['id']]),
                'servidor' => route('school.staff.census.edit', [...$parametros, 'servidor' => $origem['id']]),
                'atribuicao' => route('school.classes.teachers.census.edit', [...$parametros, 'turma' => $origem['turma'], 'atribuicao' => $origem['id']]),
                default => route('school.census.school.edit', $parametros),
            };
            // Nunca envia os valores de registros à view. Suprime detalhes
            // de saúde e caracteres do dado pessoal nas mensagens.
            $mensagem = $p->mensagem;
            if (($p->registro === '30' && $p->campo !== null && $p->campo >= 17 && $p->campo <= 49)
                || str_contains($mensagem, 'tem caractere não aceito')) {
                $mensagem = 'Confira o preenchimento do campo na tela de origem.';
            }

            return ['registro' => $p->registro, 'campo' => $p->campo, 'recusa' => $p->recusa(), 'mensagem' => $mensagem,
                'regra' => $item['regra'], 'nome' => $origem['nome'] ?? 'Escola', 'url' => $url];
        })->groupBy('registro')->sortKeys();

        return response()->view('census.educacenso-school', [
            'municipality' => $municipality, 'school' => $school, 'edicao' => $edicao,
            'contagens' => $resultado['contagens'], 'recusas' => $resultado['recusas'],
            'pronta' => $resultado['pronta'], 'grupos' => $grupos,
        ])->header('Cache-Control', 'private, no-store');
    }
}
