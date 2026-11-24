@extends('layouts.municipal')
@section('title', 'Busca ativa da rede')
@section('body-class', 'pagina-busca-ativa')
@push('head')
    <link rel="stylesheet" href="{{ asset('css/busca-ativa.css') }}?v={{ filemtime(public_path('css/busca-ativa.css')) }}">
@endpush
@php($params = ['municipio' => $municipality->slug])
@section('content')
    <div class="folha-titulo"><div><span class="contexto">Secretaria Municipal de Educação</span><h1>Busca ativa da rede</h1><p>Acompanhamento de casos de risco de abandono e retornos à escola.</p></div></div>
    @if(session('status'))<div class="status-sucesso" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="erro" role="alert">{{ $errors->first() }}</div>@endif
    <section class="folha">
        <div class="folha-cabecalho"><div><h2>Ano letivo</h2><p>Selecione o período de acompanhamento.</p></div></div>
        <div class="folha-corpo">
            <form method="GET" action="{{ route('active-search.network', $params) }}" class="busca-ativa-form">
                <label>Ano <select name="ano" class="entrada" required>@foreach($years as $option)<option value="{{ $option->year }}" @selected($option->id === $year?->id)>{{ $option->year }}</option>@endforeach</select></label>
                <button type="submit" class="botao">Consultar</button>
            </form>
        </div>
    </section>
    @if($year)
        <section class="folha">
            <div class="folha-cabecalho"><div><h2>Parâmetros dos alertas</h2><p>Uma sequência de faltas não justificadas ou frequência mensal abaixo do limite gera alerta.</p></div></div>
            <div class="folha-corpo">
                <form method="POST" action="{{ route('active-search.parameters', $params) }}" class="busca-ativa-form">
                    @csrf
                    <input type="hidden" name="ano_id" value="{{ $year->id }}">
                    <label>Dias letivos consecutivos <input class="entrada" type="number" name="dias" min="1" max="30" value="{{ old('dias', $settings['days']) }}" required></label>
                    <label>Frequência mensal mínima (%) <input class="entrada" type="number" name="frequencia" min="1" max="100" value="{{ old('frequencia', $settings['percentage']) }}" required></label>
                    <button type="submit" class="botao">Salvar parâmetros</button>
                </form>
                <p class="texto-ajuda">O padrão é 5 dias letivos consecutivos e 75%. O alerta não é uma declaração de abandono e não aciona automaticamente o Conselho Tutelar.</p>
            </div>
        </section>
        <section class="folha">
            <div class="folha-cabecalho"><div><h2>Casos por escola</h2><p>Retornos no mês corrente e média de dias corridos desde a abertura até o primeiro retorno informado.</p></div></div>
            <div class="rolagem busca-ativa-rolagem"><table class="tabela busca-ativa-tabela">
                <thead><tr><th scope="col">Escola</th><th scope="col">Casos em acompanhamento</th><th scope="col">Retornos no mês</th><th scope="col">Tempo médio até o retorno</th></tr></thead>
                <tbody>
                @foreach($rows as $row)
                    <tr><th scope="row"><a href="{{ route('school.active-search.index', [...$params, 'escola' => $row['school']->id, 'ano' => $year->year]) }}">{{ $row['school']->name }}</a></th><td>{{ $row['open'] }}</td><td>{{ $row['returns'] }}</td><td>{{ $row['average'] === null ? 'Sem retorno' : number_format($row['average'], 1, ',', '.').' dias' }}</td></tr>
                @endforeach
                <tr><th scope="row">Total</th><td>{{ $rows->sum('open') }}</td><td>{{ $rows->sum('returns') }}</td><td>{{ $networkAverage === null ? 'Sem retorno' : number_format($networkAverage, 1, ',', '.').' dias' }}</td></tr>
                </tbody>
            </table></div>
            <div class="folha-corpo"><p class="texto-ajuda">Casos em acompanhamento incluem os abertos e encaminhados. O painel não exibe nomes ou motivos familiares.</p></div>
        </section>
    @else
        <section class="folha"><div class="folha-corpo"><p>Cadastre um ano letivo antes de configurar a busca ativa.</p></div></section>
    @endif
@endsection
