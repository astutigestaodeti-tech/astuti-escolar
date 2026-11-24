@extends('layouts.municipal')

@section('title', 'Educacenso - '.$school->name)
@section('body-class', 'pagina-censo-turmas pagina-educacenso-escola')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/censo-turmas.css') }}?v={{ filemtime(public_path('css/censo-turmas.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/educacenso-escola.css') }}?v={{ filemtime(public_path('css/educacenso-escola.css')) }}">
@endpush

@section('trilha')
    <a href="{{ route('census.readiness.index', ['municipio' => $municipality->slug]) }}">Conferência do Censo</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Educacenso</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Censo Escolar {{ $edicao }}</span>
            <h1>Educacenso</h1>
            <p>{{ $school->name }}. Conferência da montagem lógica da escola.</p>
        </div>
        <a class="botao secundario" href="{{ route('census.readiness.index', ['municipio' => $municipality->slug]) }}">Voltar à Conferência</a>
    </div>
    <section class="folha" aria-labelledby="titulo-situacao">
        <div class="folha-cabecalho">
            <div><h2 id="titulo-situacao">{{ $pronta ? 'Escola pronta para o arquivo' : 'Escola fora do arquivo, com '.$recusas.' recusas' }}</h2>
                <p>Conferência local por escola, sem gerar ou enviar arquivo. O Inep ainda confere regras da sua base e vínculos externos.</p></div>
        </div>
        <div class="folha-corpo">
            <dl class="educacenso-contagens">
                @foreach ($contagens as $tipo => $total)
                    <div><dt>Registro {{ $tipo }}</dt><dd>{{ $total }}</dd></div>
                @endforeach
            </dl>
        </div>
    </section>
    @forelse ($grupos as $tipo => $itens)
        <section class="folha censo-secao" aria-labelledby="pendencias-{{ $tipo }}">
            <div class="folha-cabecalho"><div><h2 id="pendencias-{{ $tipo }}">Registro {{ $tipo }}</h2><p>{{ count($itens) }} pendências e avisos</p></div></div>
            <div class="folha-corpo">
                <ul class="educacenso-pendencias">
                    @foreach ($itens as $item)
                        <li>
                            <strong>{{ $item['nome'] }}</strong>
                            <p>{{ $item['recusa'] ? 'Recusa' : 'Aviso' }}{{ $item['campo'] !== null ? ' - campo '.$item['campo'] : '' }}{{ $item['regra'] !== null ? ' - regra '.$item['regra'] : '' }}: {{ $item['mensagem'] }}</p>
                            <a href="{{ $item['url'] }}">Corrigir cadastro</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @empty
        <section class="folha censo-secao"><div class="folha-corpo"><p>Não há pendências locais nesta escola.</p></div></section>
    @endforelse
@endsection
