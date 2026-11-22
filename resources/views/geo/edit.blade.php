@extends('layouts.municipal')

@section('title', 'Localização no mapa')

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('schools.show', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Localização no mapa</span>
@endsection

@push('head')
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
@endpush

@section('content')
    @php
        $temPonto = $subject->latitude !== null;
        $ponto = $suggestion && $suggestion['found'] ? $suggestion : ($temPonto ? ['lat' => (float) $subject->latitude, 'lon' => (float) $subject->longitude] : null);
        $origem = $suggestion && $suggestion['found'] ? 'endereco' : ($subject->geo_source ?? 'mapa');
    @endphp
    <div class="folha-titulo">
        <div>
            <span class="contexto">{{ $kind === 'aluno' ? 'Residência do aluno' : 'Endereço da escola' }}</span>
            <h1>Localização de {{ $subjectName }}</h1>
            <p>Procure o endereço ou clique no mapa. Arraste o marcador até a porta da {{ $kind === 'aluno' ? 'casa' : 'escola' }} e salve.</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="status-erro" role="alert"><x-icon name="info" :size="18" /> {{ $errors->first() }}</div>
    @endif
    @if ($suggestion && ! $suggestion['found'])
        <div class="status-erro" role="status"><x-icon name="info" :size="18" /> Não encontrei "{{ $suggestion['query'] }}" no mapa. Tente só a rua e a cidade, ou clique direto no mapa.</div>
    @endif

    <section class="folha geo-folha" aria-labelledby="titulo-mapa">
        <h2 id="titulo-mapa" class="sr-only">Mapa</h2>
        <form class="busca geo-busca" method="GET" action="{{ url()->current() }}" role="search">
            <div class="campo">
                <x-icon name="busca" :size="18" />
                <label class="sr-only" for="procurar">Endereço para procurar no mapa</label>
                <input id="procurar" type="search" name="procurar" maxlength="200" value="{{ $suggestion['query'] ?? $addressText }}" placeholder="Rua, número, bairro e cidade">
            </div>
            <button class="botao secundario" type="submit"><x-icon name="busca" :size="16" /> Procurar no mapa</button>
        </form>

        <div id="mapa-localizacao" class="geo-mapa" role="application" aria-label="Mapa para marcar a localização"
             data-tiles="{{ config('geo.tiles_url') }}" data-atribuicao="{{ config('geo.tiles_attribution') }}"
             @if ($ponto) data-lat="{{ $ponto['lat'] }}" data-lon="{{ $ponto['lon'] }}" @endif
             @if ($kind === 'aluno' && $school->latitude !== null) data-escola-lat="{{ $school->latitude }}" data-escola-lon="{{ $school->longitude }}" data-escola-nome="{{ $school->name }}" @endif></div>

        <form method="POST" action="{{ $saveUrl }}" class="geo-salvar">
            @csrf
            <input type="hidden" name="latitude" id="latitude" value="{{ $ponto['lat'] ?? '' }}">
            <input type="hidden" name="longitude" id="longitude" value="{{ $ponto['lon'] ?? '' }}">
            <input type="hidden" name="geo_source" id="geo_source" value="{{ $origem }}">
            <p class="texto-ajuda" id="geo-situacao">{{ $ponto ? 'Ponto marcado. Ajuste se precisar e salve.' : 'Nenhum ponto marcado ainda.' }}</p>
            <div class="formulario-acoes">
                <button class="botao" type="submit">Salvar localização</button>
                <a class="botao secundario" href="{{ $backUrl }}">Cancelar</a>
                @if ($temPonto)
                    <button class="botao secundario geo-remover" type="submit" name="remover" value="1" formnovalidate>Remover localização</button>
                @endif
            </div>
        </form>
        @if ($kind === 'aluno')
            <p class="texto-ajuda geo-lgpd">A localização da casa é usada só para calcular a distância até a escola e planejar o transporte. Ela fica visível apenas para a secretaria e a gestão; professores veem só a distância.</p>
        @endif
    </section>

    <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
    <script src="{{ asset('js/mapa-localizacao.js') }}"></script>
@endsection
