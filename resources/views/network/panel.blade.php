@extends('layouts.municipal')

@section('title', 'Painel da rede')
@section('body-class', 'pagina-painel-rede')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/painel-rede.css') }}?v={{ filemtime(public_path('css/painel-rede.css')) }}">
@endpush

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Painel da rede</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Secretaria Municipal de Educação</span>
            <h1>Painel da rede</h1>
            <p>Indicadores de todas as escolas ativas, para identificar prioridades de acompanhamento.</p>
        </div>
        @if ($year)
            <span class="marcador">Ano letivo {{ $year->year }}</span>
        @endif
    </div>

    <section class="folha" aria-labelledby="titulo-resumo-rede">
        <div class="folha-cabecalho">
            <div><h2 id="titulo-resumo-rede">Visão geral</h2><p>Totais das unidades escolares ativas.</p></div>
        </div>
        <div class="folha-corpo">
            <dl class="painel-rede-resumo">
                <div><dt>Escolas</dt><dd>{{ $totals['schools'] }}</dd></div>
                <div><dt>Alunos ativos</dt><dd>{{ $totals['students'] }}</dd></div>
                <div><dt>Turmas</dt><dd>{{ $totals['classes'] }}</dd></div>
                <div><dt>Alertas de faltas</dt><dd>{{ $totals['alerts'] }}</dd></div>
                <div><dt>Cadastros com pendência</dt><dd>{{ $totals['pendencies'] }}</dd></div>
                <div><dt>Usuários do transporte</dt><dd>{{ $totals['transport'] }}</dd></div>
                <div><dt>Transporte sem rota</dt><dd>{{ $totals['without_route'] }}</dd></div>
                <div><dt>Calendários abaixo de 200 dias</dt><dd>{{ $totals['calendar_below_minimum'] }}</dd></div>
                <div><dt>Escolas sem mapa</dt><dd>{{ $totals['unmapped'] }}</dd></div>
            </dl>
        </div>
    </section>

    <section class="folha painel-rede-unidades" aria-labelledby="titulo-unidades-rede">
        <div class="folha-cabecalho">
            <div><h2 id="titulo-unidades-rede">Escolas da rede</h2><p>Os números abrem os detalhes da escola correspondente.</p></div>
        </div>
        <div class="folha-corpo">
            <form method="GET" action="{{ route('network.panel.index', ['municipio' => $municipality->slug]) }}" class="painel-rede-filtros">
                <label for="ordenar-painel-rede">Ordenar escolas
                    <select name="ordenar" id="ordenar-painel-rede">
                        <option value="alertas" @selected($order === 'alertas')>Mais alertas de faltas</option>
                        <option value="pendencias" @selected($order === 'pendencias')>Mais pendências</option>
                        <option value="nome" @selected($order === 'nome')>Nome da escola</option>
                    </select>
                </label>
                <button class="botao secundario" type="submit">Ordenar</button>
            </form>
            @if ($year === null)
                <p class="texto-ajuda">Não há ano letivo em andamento. Dias previstos e alertas de faltas dependem do cadastro do ano letivo.</p>
            @endif
        </div>

        @if ($rows->isEmpty())
            <div class="folha-corpo"><p>Nenhuma escola ativa foi encontrada.</p></div>
        @else
            <div class="rolagem painel-rede-rolagem">
                <table class="tabela painel-rede-tabela">
                    <thead>
                        <tr>
                            <th scope="col">Escola</th>
                            <th scope="col">Alunos ativos</th>
                            <th scope="col">Turmas</th>
                            <th scope="col">Alertas de faltas</th>
                            <th scope="col">Pendências</th>
                            <th scope="col">Transporte</th>
                            <th scope="col">Sem rota</th>
                            <th scope="col">Dias letivos previstos</th>
                            <th scope="col">Mapa</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $item)
                            @php
                                $params = ['municipio' => $municipality->slug, 'escola' => $item['school']->id];
                            @endphp
                            <tr>
                                <th scope="row"><a href="{{ route('schools.show', $params) }}">{{ $item['school']->name }}</a></th>
                                <td><a href="{{ route('school.students.index', $params) }}">{{ $item['students'] }}</a></td>
                                <td><a href="{{ route('school.classes.index', $params) }}">{{ $item['classes'] }}</a></td>
                                <td><a href="{{ route('school.absences.index', $params) }}" @if ($item['alerts'] > 0) class="painel-rede-atencao" @endif>{{ $item['alerts'] }}</a></td>
                                <td><a href="{{ route('school.pendencies.index', $params) }}" @if ($item['pendencies'] > 0) class="painel-rede-atencao" @endif>{{ $item['pendencies'] }}</a></td>
                                <td><a href="{{ route('school.transport.routes.index', $params) }}">{{ $item['transport'] }}</a></td>
                                <td><a href="{{ route('school.transport.routes.index', [...$params, 'rota' => '__sem_rota__']) }}" @if ($item['without_route'] > 0) class="painel-rede-atencao" @endif>{{ $item['without_route'] }}</a></td>
                                <td>
                                    <a href="{{ route('school.calendar.index', $params) }}" @if ($item['planned_days'] !== null && $item['planned_days'] < 200) class="painel-rede-atencao" @endif>
                                        {{ $item['planned_days'] ?? 'Não informado' }}
                                        @if ($item['planned_days'] !== null && $item['planned_days'] < 200)<span class="sr-only">, abaixo de 200</span>@endif
                                    </a>
                                </td>
                                <td>
                                    <a href="{{ route('schools.geo.edit', $params) }}">{{ $item['mapped'] ? 'Marcada' : 'Não marcada' }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
