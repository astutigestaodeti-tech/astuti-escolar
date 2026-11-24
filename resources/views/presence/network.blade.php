@extends('layouts.municipal')

@section('title', 'Sistema Presença')
@section('body-class', 'pagina-presenca')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/sistema-presenca.css') }}?v={{ filemtime(public_path('css/sistema-presenca.css')) }}">
@endpush

@section('trilha')
    <a href="{{ route('network.panel.index', ['municipio' => $municipality->slug]) }}">Painel da rede</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Sistema Presença</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Bolsa Família {{ $ano?->year ?? 'sem ano letivo' }}</span>
            <h1>Sistema Presença</h1>
            <p>Frequência mensal de todos os alunos de 4 a 17 anos, para o lançamento no sistema do MEC. Mínimo de 60% de 4 a 5 anos e de 75% de 6 a 17 anos.</p>
        </div>
    </div>

    <div class="nota" role="note">
        <x-icon name="info" :size="18" />
        <p>O Astuti não guarda quem recebe o Bolsa Família nem o motivo da baixa frequência. A lista de beneficiários e o registro do motivo ficam no próprio Sistema Presença; aqui o operador vê a frequência e marca o que já lançou.</p>
    </div>

    <section class="folha" aria-labelledby="titulo-rede">
        <div class="folha-cabecalho"><div><h2 id="titulo-rede">{{ \App\Attendance\SistemaPresenca::rotulo($periodo) }}</h2><p>Por escola. Abra a escola para a lista nominal.</p></div></div>
            <form class="busca busca-matriculas" method="GET" action="{{ route('presence.network', ['municipio' => $municipality->slug]) }}">
                <div class="filtro-ano">
                    <label class="sr-only" for="periodo">Período</label>
                    <select id="periodo" name="periodo">
                        @foreach (\App\Attendance\SistemaPresenca::PERIODOS as $numero => $meses)
                            <option value="{{ $numero }}" @selected($periodo === $numero)>{{ \App\Attendance\SistemaPresenca::rotulo($numero) }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="botao" type="submit">Ver período</button>
            </form>
        @if ($ano === null)
            <div class="vazio">Cadastre o ano letivo para apurar a frequência.</div>
        @else
            <div class="rolagem">
                <table class="tabela">
                    <thead><tr><th scope="col">Escola</th><th scope="col">Alunos de 4 a 17 anos</th><th scope="col">Abaixo do mínimo</th><th scope="col">Mês sem frequência</th><th scope="col">Lançados no Presença</th></tr></thead>
                    <tbody>
                        @foreach ($resumo as $linha)
                            <tr>
                                <td><a href="{{ route('school.presence.index', ['municipio' => $municipality->slug, 'escola' => $linha['escola']->id, 'periodo' => $periodo]) }}">{{ $linha['escola']->name }}</a></td>
                                <td>{{ $linha['total'] }}</td>
                                <td>{{ $linha['abaixo'] }}</td>
                                <td>{{ $linha['sem'] }}</td>
                                <td>{{ $linha['lancados'] }} de {{ $linha['total'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
