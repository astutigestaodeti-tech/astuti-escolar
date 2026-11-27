@extends('layouts.municipal')

@php
    use App\Attendance\SistemaPresenca;
    [$mes1, $mes2] = SistemaPresenca::PERIODOS[$periodo];
    $parametros = ['municipio' => $municipality->slug, 'escola' => $school->id];
    $pct = fn (?int $valor): string => $valor === null ? 'sem registro' : $valor.'%';
@endphp

@section('title', 'Sistema Presença - '.$school->name)
@section('body-class', 'pagina-presenca')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/sistema-presenca.css') }}?v={{ filemtime(public_path('css/sistema-presenca.css')) }}">
@endpush

@section('trilha')
    <a href="{{ route('schools.show', $parametros) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Sistema Presença</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Bolsa Família {{ $ano?->year ?? 'sem ano letivo' }}</span>
            <h1>Sistema Presença</h1>
            <p>{{ SistemaPresenca::rotulo($periodo) }}. Procure cada beneficiário da lista do Presença aqui, lance a frequência de cada mês e marque como lançado. Falta justificada conta como falta: a justificativa vira o motivo no Presença.</p>
        </div>
        <a class="botao secundario" href="{{ route('schools.show', $parametros) }}">Voltar à escola</a>
    </div>

    <section class="folha" aria-labelledby="titulo-filtro">
        <div class="folha-cabecalho"><div><h2 id="titulo-filtro">Período e busca</h2>
            <p>{{ $total }} aluno(s) de 4 a 17 anos. Abaixo do mínimo: {{ $contagem[SistemaPresenca::ABAIXO] ?? 0 }}. Mês sem frequência: {{ $contagem[SistemaPresenca::SEM_CHAMADA] ?? 0 }}. Lançados: {{ $lancados }}.</p></div></div>
        <form class="busca busca-matriculas" method="GET" action="{{ route('school.presence.index', $parametros) }}" role="search">
            <div class="filtro-ano">
                <label class="sr-only" for="periodo">Período</label>
                <select id="periodo" name="periodo">
                    @foreach (SistemaPresenca::PERIODOS as $numero => $meses)
                        <option value="{{ $numero }}" @selected($periodo === $numero)>{{ SistemaPresenca::rotulo($numero) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filtro-ano">
                <label class="sr-only" for="situacao">Situação</label>
                <select id="situacao" name="situacao">
                    <option value="">Todas as situações</option>
                    @foreach (SistemaPresenca::SITUACOES as $valor => $rotulo)
                        <option value="{{ $valor }}" @selected($filtro === $valor)>{{ $rotulo }}</option>
                    @endforeach
                </select>
            </div>
            <div class="campo">
                <x-icon name="busca" :size="18" />
                <label class="sr-only" for="busca">Nome do aluno</label>
                <input id="busca" type="search" name="busca" value="{{ $busca }}" maxlength="120" placeholder="Nome do aluno" autocomplete="off">
            </div>
            <button class="botao" type="submit"><x-icon name="busca" :size="16" /> Pesquisar</button>
        </form>
    </section>

    <section class="folha presenca-lista" aria-labelledby="titulo-lista">
        <div class="folha-cabecalho"><div><h2 id="titulo-lista">Frequência por aluno</h2><p>Percentual de presença em cada mês, sobre as chamadas registradas enquanto o aluno estava na turma.</p></div></div>
        @error('ano_letivo')
            <p class="folha-corpo" role="alert">{{ $message }}</p>
        @enderror
        @if ($linhas->isEmpty())
            <div class="vazio">{{ $ano === null ? 'Cadastre o ano letivo para apurar a frequência.' : 'Nenhum aluno nesta seleção.' }}</div>
        @else
            <div class="rolagem">
                <table class="tabela">
                    <thead><tr>
                        <th scope="col">Aluno</th><th scope="col">Turma</th><th scope="col">Mínimo</th>
                        <th scope="col">{{ ucfirst(SistemaPresenca::NOMES_DOS_MESES[$mes1]) }}</th>
                        <th scope="col">{{ ucfirst(SistemaPresenca::NOMES_DOS_MESES[$mes2]) }}</th>
                        <th scope="col">Situação</th><th scope="col">No Presença</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($linhas as $linha)
                            <tr id="aluno-{{ $linha['aluno'] }}" @class(['presenca-abaixo' => $linha['situacao'] === SistemaPresenca::ABAIXO])>
                                <td><a href="{{ route('school.students.show', [...$parametros, 'aluno' => $linha['aluno']]) }}">{{ $linha['nome'] }}</a>@if ($linha['encerrada'])<br><span class="texto-suave">Matrícula encerrada</span>@endif</td>
                                <td>{{ $linha['turma'] }}</td>
                                <td>{{ $linha['minimo'] }}%</td>
                                @foreach ([$mes1, $mes2] as $mes)
                                    @php($dados = $linha['meses'][$mes])
                                    <td><strong>{{ $pct($dados['percentual']) }}</strong>@if ($dados['aulas'] > 0)<br><span class="texto-suave">{{ $dados['faltas'] }} falta(s) em {{ $dados['aulas'] }}@if ($dados['justificadas'] > 0), {{ $dados['justificadas'] }} justificada(s)@endif</span>@endif</td>
                                @endforeach
                                <td>{{ SistemaPresenca::SITUACOES[$linha['situacao']] }}</td>
                                <td>
                                    <form method="POST" action="{{ route('school.presence.mark', $parametros) }}">
                                        @csrf
                                        <input type="hidden" name="aluno" value="{{ $linha['aluno'] }}">
                                        <input type="hidden" name="ano_letivo" value="{{ $ano->id }}">
                                        <input type="hidden" name="periodo" value="{{ $periodo }}">
                                        <input type="hidden" name="situacao" value="{{ $filtro }}">
                                        @if ($linha['lancado'])
                                            <input type="hidden" name="lancado" value="0">
                                            <span class="presenca-lancado">Lançado em {{ $linha['lancado']->format('d/m') }}</span>
                                            <button class="botao secundario" type="submit">Desfazer</button>
                                        @else
                                            <input type="hidden" name="lancado" value="1">
                                            <button class="botao secundario" type="submit">Marcar lançado</button>
                                        @endif
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
