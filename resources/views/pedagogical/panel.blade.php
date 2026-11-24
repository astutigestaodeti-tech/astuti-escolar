@extends('layouts.municipal')

@section('title', 'Gestão pedagógica')
@section('body-class', 'pagina-gestao-pedagogica')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/gestao-pedagogica.css') }}?v={{ filemtime(public_path('css/gestao-pedagogica.css')) }}">
@endpush

@section('trilha')
    @if ($school)
        <a href="{{ route('schools.show', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">{{ $school->name }}</a>
    @else
        <a href="{{ route('network.panel.index', ['municipio' => $municipality->slug]) }}">Painel da rede</a>
    @endif
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Gestão pedagógica</span>
@endsection

@section('content')
    <div class="folha-titulo"><div><span class="contexto">{{ $school?->name ?? 'Secretaria Municipal de Educação' }}</span><h1>Gestão pedagógica</h1><p>Conselhos, distorção idade-série e dias de aula previstos e registrados.</p></div></div>
    <section class="folha">
        <div class="folha-corpo">
            <form method="GET" class="pedagogico-filtros">
                <label>Ano letivo <select name="ano" class="entrada">@foreach ($years as $y)<option value="{{ $y->id }}" @selected($year?->id === $y->id)>{{ $y->year }}</option>@endforeach</select></label>
                @if ($school && Gate::allows('viewEquity', [$school, $municipality]))<label class="pedagogico-marcar"><input type="checkbox" name="nominal" value="1" @checked($nominal)> Mostrar lista nominal de distorção</label>@endif
                <button class="botao secundario">Consultar</button>
            </form>
        </div>
    </section>
    @if ($data !== null)
        <section class="folha pedagogico-secao">
            <div class="folha-cabecalho"><div><h2>Distorção idade-série</h2><p>Idade em 31/03; dois anos ou mais acima da esperada. Somente anos regulares do ensino fundamental, incluindo sugestões a confirmar.</p></div></div>
            <div class="folha-corpo">
                <p><strong>{{ $data['distorted'] }} de {{ $data['eligible'] }}</strong> matrículas classificadas ({{ $data['eligible'] > 0 ? number_format(100 * $data['distorted'] / $data['eligible'], 1, ',', '.') . '%' : 'Sem base' }}). {{ $data['unclassified'] }} fora do cálculo ou sem dados.</p>
            </div>
        </section>
        @foreach ($data['schools'] as $unit)
            <section class="folha pedagogico-secao">
                <div class="folha-cabecalho">
                    <div><h2>{{ $unit['school']->name }}</h2><p>{{ $unit['distorted'] }} de {{ $unit['eligible'] }} matrículas classificadas com distorção; {{ $unit['unclassified'] }} fora do cálculo ou sem dados.</p></div>
                    @if (! $school)<a class="botao secundario" href="{{ route('pedagogical.school', ['municipio' => $municipality->slug, 'escola' => $unit['school']->id, 'ano' => $year->id]) }}">Detalhar escola</a>@endif
                </div>
                <div class="folha-corpo">
                    <h3>Conselhos concluídos</h3>
                    <ul class="pedagogico-notas">
                        @forelse ($unit['summary'] as $summary)
                            <li>{{ $summary['period']->number }}º {{ $summary['period']->kind }}: {{ $summary['completed'] }} de {{ $summary['total'] }}</li>
                        @empty
                            <li>Não há períodos cadastrados.</li>
                        @endforelse
                    </ul>
                </div>
                <div class="rolagem">
                    <table class="tabela">
                        <thead><tr><th scope="col">Turma</th><th scope="col">Período</th><th scope="col">Conselho</th><th scope="col">Previstas</th><th scope="col">Dadas</th><th scope="col">Situação</th><th scope="col">Distorção</th></tr></thead>
                        <tbody>
                            @foreach ($unit['classes'] as $row)
                                @forelse ($row['periods'] as $p)
                                    <tr>
                                        <th scope="row">{{ $row['class']->name }}</th><td>{{ $p['period']->number }}º {{ $p['period']->kind }}</td>
                                        <td>
                                            {{ \App\Models\Tenant\ClassCouncil::STATUSES[$p['council']] }}
                                            @if (! $municipal && $school)<a href="{{ route('pedagogical.council.index', ['municipio' => $municipality->slug, 'escola' => $school->id, 'turma' => $row['class']->id, 'periodo' => $p['period']->id]) }}">Abrir</a>@endif
                                        </td>
                                        <td>{{ $p['previstas'] }}</td><td>{{ $p['dadas'] }} @if ($p['fora_calendario']) ({{ $p['fora_calendario'] }} fora do calendário) @endif</td>
                                        <td>{{ ucfirst($p['situacao']) }} @if ($p['faltantes']) ({{ $p['faltantes'] }} dias sem chamada) @endif</td>
                                        <td>{{ $row['distorted'] }} de {{ $row['eligible'] }} @if ($row['suggested']) (etapa a confirmar) @endif</td>
                                    </tr>
                                @empty
                                    <tr><th scope="row">{{ $row['class']->name }}</th><td colspan="5">Sem períodos cadastrados</td><td>{{ $row['distorted'] }} de {{ $row['eligible'] }} @if ($row['suggested']) (etapa a confirmar) @endif</td></tr>
                                @endforelse
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($nominal)
                    <div class="folha-corpo">
                        <h3>Lista nominal de distorção</h3>
                        @foreach ($unit['classes'] as $row)
                            @if ($row['names'] !== [])
                                <h4>{{ $row['class']->name }} @if ($row['suggested']) (a confirmar) @endif</h4>
                                <ul>@foreach ($row['names'] as $n)<li>{{ $n['nome'] }}: {{ $n['idade'] }} anos (esperada: {{ $n['esperada'] }})</li>@endforeach</ul>
                            @endif
                        @endforeach
                    </div>
                @endif
            </section>
        @endforeach
        <p class="texto-ajuda">Aulas dadas são sessões de frequência, não horas-aula. Hoje ainda pode receber chamada; dias vencidos sem sessão geram atraso.</p>
    @else
        <section class="folha pedagogico-secao"><div class="folha-corpo"><p>Cadastre um ano letivo para consultar os indicadores.</p></div></section>
    @endif
@endsection
