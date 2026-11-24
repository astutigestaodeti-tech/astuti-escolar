@extends('layouts.municipal')

@php
    use App\Models\Tenant\TransportRouteCensus as Rota;
    $parametros = ['municipio' => $municipality->slug, 'escola' => $school->id];
    $indice = 0;
@endphp

@section('title', 'Transporte no Censo - '.$school->name)
@section('body-class', 'pagina-censo-turmas')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/censo-turmas.css') }}?v={{ filemtime(public_path('css/censo-turmas.css')) }}">
@endpush

@section('trilha')
    <a href="{{ route('schools.show', $parametros) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('school.transport.routes.index', $parametros) }}">Rotas de transporte</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Transporte no Censo</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Censo Escolar</span>
            <h1>Transporte no Censo</h1>
            <p>Poder público e tipo de veículo de cada rota. O Censo pede isso para cada aluno que usa transporte; aqui você informa uma vez por rota.</p>
        </div>
        <a class="botao secundario" href="{{ route('school.transport.routes.index', $parametros) }}">Voltar às rotas</a>
    </div>

    @if (session('status'))
        <div class="status-sucesso" role="status"><x-icon name="info" :size="18" /> {{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="erro" role="alert">
            <x-icon name="alerta" :size="18" />
            <div><strong>Revise o formulário</strong><ul>@foreach ($errors->all() as $mensagem)<li>{{ $mensagem }}</li>@endforeach</ul></div>
        </div>
    @endif
    @if ($withoutRoute > 0)
        <div class="censo-sugestao" role="note">
            <x-icon name="info" :size="18" />
            <p>{{ $withoutRoute }} {{ $withoutRoute === 1 ? 'aluno usa' : 'alunos usam' }} transporte sem rota informada. Informe a rota na ficha do aluno para que o Censo saiba o veículo.</p>
        </div>
    @endif

    @if ($routes === [])
        <section class="folha"><div class="folha-corpo"><p class="texto-suave">Nenhum aluno desta escola usa transporte com rota informada.</p></div></section>
    @else
        <form method="POST" action="{{ route('school.census.transport.update', $parametros) }}" novalidate>
            @csrf
            @method('PUT')
            @foreach ($routes as $nome => $alunos)
                @php
                    $salvo = $saved[$nome] ?? null;
                    $poder = (string) old("rotas.$indice.poder", $salvo?->public_power ?? 2);
                    $marcados = old("rotas.$indice.veiculos", $salvo?->vehicles ?? []);
                @endphp
                <section class="folha" aria-labelledby="rota-{{ $indice }}">
                    <div class="folha-cabecalho"><div><h2 id="rota-{{ $indice }}">{{ $nome }}</h2><p>{{ $alunos }} {{ $alunos === 1 ? 'aluno' : 'alunos' }}. {{ $salvo ? 'Informada.' : 'Ainda não informada.' }}</p></div></div>
                    <div class="folha-corpo">
                        <input type="hidden" name="rotas[{{ $indice }}][nome]" value="{{ $nome }}">
                        <div class="formulario-grade">
                            <div class="formulario-campo">
                                <label class="rotulo" for="poder-{{ $indice }}">Poder público responsável</label>
                                <select class="entrada" id="poder-{{ $indice }}" name="rotas[{{ $indice }}][poder]">
                                    @foreach (Rota::PUBLIC_POWERS as $valor => $rotulo)
                                        <option value="{{ $valor }}" @selected($poder === (string) $valor)>{{ $rotulo }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <fieldset class="censo-opcoes">
                            <legend class="rotulo">Veículos usados nesta rota</legend>
                            @foreach (Rota::VEHICLES as $chave => $dados)
                                <label><input type="checkbox" name="rotas[{{ $indice }}][veiculos][]" value="{{ $chave }}" @checked(in_array($chave, $marcados, true))> {{ $dados['rotulo'] }}</label>
                            @endforeach
                        </fieldset>
                    </div>
                </section>
                @php $indice++; @endphp
            @endforeach
            <div class="formulario-acoes censo-secao">
                <p class="texto-ajuda">Gravar pede o código do segundo fator. Toda alteração fica na auditoria.</p>
                <button class="botao" type="submit">Salvar transporte no Censo</button>
            </div>
        </form>
    @endif
@endsection
