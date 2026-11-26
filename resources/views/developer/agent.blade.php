@extends('layouts.municipal')

@section('title', 'Agente')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/agente.css') }}?v={{ filemtime(public_path('css/agente.css')) }}">
    <script defer src="{{ asset('js/agente.js') }}?v={{ filemtime(public_path('js/agente.js')) }}"></script>
@endpush

@section('trilha')
    <span>Console técnico</span>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Agente</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Console técnico privativo</span>
            <h1>Agente</h1>
            <p>Atualização automática a cada 8 segundos com a aba visível. A coleta do servidor ocorre a cada 2 minutos.</p>
        </div>
    </div>
    <section class="folha">
        <div class="folha-corpo">
            <form method="GET" action="{{ route('developer.agent.index', ['municipio' => $municipality->slug]) }}" class="agente-filtro">
                <div>
                    <label class="rotulo" for="stage">Etapa do histórico</label>
                    <select class="entrada" name="stage" id="stage">
                        <option value="">Todas as etapas</option>
                        @foreach ($stages as $key => $label)
                            <option value="{{ $key }}" @selected($stage === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="botao">Filtrar</button>
            </form>
            <p id="agente-atualizacao" role="status">Aguardando próxima atualização.</p>
        </div>
    </section>
    <div id="agente-dados" data-endpoint="{{ route('developer.agent.snapshot', ['municipio' => $municipality->slug, 'stage' => $stage, 'page' => $page]) }}">
        @include('developer.partials.agent-history')
    </div>
@endsection
