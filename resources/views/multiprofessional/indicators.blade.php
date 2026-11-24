@extends('layouts.municipal')
@section('title', 'Indicadores da equipe multiprofissional')
@section('body-class', 'pagina-equipe')
@push('head')
    <link rel="stylesheet" href="{{ asset('css/equipe-multiprofissional.css') }}?v={{ filemtime(public_path('css/equipe-multiprofissional.css')) }}">
@endpush
@section('content')
    <div class="folha-titulo"><div><span class="contexto">Gestão municipal</span><h1>Indicadores da equipe multiprofissional</h1><p>Acompanhamento da Lei 13.935/2019, somente dados agregados.</p></div></div>
    <section class="folha">
        <div class="folha-cabecalho"><h2>Competência</h2></div>
        <div class="folha-corpo">
            <form method="GET" action="{{ route('team.indicators', ['municipio' => $municipality->slug]) }}" class="equipe-form">
                <label>Mês e ano <input class="entrada" name="mes" type="month" value="{{ $month->format('Y-m') }}" max="{{ today()->format('Y-m') }}" required></label>
                <button class="botao" type="submit">Consultar</button>
            </form>
            @if ($errors->any())<p class="erro" role="alert">{{ $errors->first() }}</p>@endif
        </div>
    </section>
    <section class="folha">
        <div class="folha-cabecalho"><h2>Atividade no mês</h2></div>
        @if ($teamIndicators === null)<div class="folha-corpo"><p>Ano letivo não cadastrado para este mês.</p></div>
        @else @include('multiprofessional.indicator-data') @endif
    </section>
@endsection
