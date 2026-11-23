@extends('layouts.municipal')

@section('title', 'Períodos avaliativos')
@section('body-class', 'pagina-notas')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/notas-boletim.css') }}?v={{ filemtime(public_path('css/notas-boletim.css')) }}">
@endpush

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Períodos avaliativos</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Configuração da rede</span>
            <h1>Períodos avaliativos</h1>
            <p>Somente gestão municipal. As datas valem para todas as turmas do ano letivo selecionado.</p>
        </div>
        <a class="botao secundario" href="{{ route('academic.components.index', ['municipio' => $municipality->slug]) }}">Componentes curriculares</a>
    </div>

    @if (session('status'))<div class="status-sucesso" role="status">{{ session('status') }}</div>@endif
    @if ($errors->any())
        <div class="erro" role="alert"><ul>@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
    @endif

    <section class="folha" aria-labelledby="titulo-periodos">
        <div class="folha-cabecalho">
            <div><h2 id="titulo-periodos">Ano letivo {{ $year->year }}</h2><p>{{ $year->start_date->format('d/m/Y') }} a {{ $year->end_date->format('d/m/Y') }}. Quatro bimestres por padrão.</p></div>
        </div>
        <div class="folha-corpo">
            <form class="notas-filtros" method="GET" action="{{ route('academic.periods.index', ['municipio' => $municipality->slug, 'ano' => $year->id]) }}">
                <label for="formato-periodos">Planejar em
                    <select id="formato-periodos" name="formato">
                        <option value="bimestre" @selected($kind === 'bimestre')>Quatro bimestres</option>
                        <option value="trimestre" @selected($kind === 'trimestre')>Três trimestres</option>
                    </select>
                </label>
                <button class="botao secundario" type="submit">Mostrar datas</button>
            </form>
            <p class="texto-ajuda">As datas não podem se sobrepor. Períodos fechados não permitem ajuste das datas. Não é possível mudar o formato depois de lançar notas.</p>

            <form method="POST" action="{{ route('academic.periods.update', ['municipio' => $municipality->slug, 'ano' => $year->id]) }}">
                @csrf
                <input type="hidden" name="kind" value="{{ $kind }}">
                <div class="rolagem notas-rolagem">
                    <table class="tabela notas-tabela notas-periodos-tabela">
                        <thead><tr><th scope="col">Período</th><th scope="col">Início</th><th scope="col">Fim</th><th scope="col">Situação atual</th></tr></thead>
                        <tbody>
                            @foreach ($formPeriods as $number => $range)
                                <tr>
                                    <th scope="row">{{ $number }}º {{ $kind }}</th>
                                    <td><label class="sr-only" for="inicio-{{ $number }}">Início {{ $number }}</label>
                                        <input class="entrada" type="date" id="inicio-{{ $number }}"
                                               name="periods[{{ $number }}][starts_on]" value="{{ old('periods.'.$number.'.starts_on', $range['starts_on']) }}" required>
                                    </td>
                                    <td><label class="sr-only" for="fim-{{ $number }}">Fim {{ $number }}</label>
                                        <input class="entrada" type="date" id="fim-{{ $number }}"
                                               name="periods[{{ $number }}][ends_on]" value="{{ old('periods.'.$number.'.ends_on', $range['ends_on']) }}" required>
                                    </td>
                                    <td>{{ ($periods->firstWhere('number', $number)?->closed_at) ? 'Fechado' : 'Aberto' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="formulario-acoes">
                    <button class="botao" type="submit">Salvar configuração de períodos</button>
                </div>
            </form>
        </div>
    </section>

    <section class="folha notas-secao" aria-labelledby="titulo-fechar-periodo">
        <div class="folha-cabecalho">
            <div><h2 id="titulo-fechar-periodo">Fechamento de período</h2><p>Requer confirmação recente do segundo fator e motivo registrado na auditoria.</p></div>
        </div>
        <div class="folha-corpo">
            @foreach ($periods as $period)
                <div class="notas-fechamento">
                    <strong>{{ $period->number }}º {{ $period->kind }}</strong>
                    @if ($period->closed_at)
                        <span>Fechado em {{ $period->closed_at->format('d/m/Y H:i') }}.</span>
                    @else
                        <form method="POST" action="{{ route('academic.periods.close', ['municipio' => $municipality->slug, 'ano' => $year->id, 'periodo' => $period->id]) }}">
                            @csrf
                            <label class="rotulo" for="motivo-fechar-{{ $period->id }}">Motivo do fechamento
                                <input class="entrada" type="text" name="reason" id="motivo-fechar-{{ $period->id }}" maxlength="500" minlength="10" required placeholder="Ex.: lançamento do período conferido pela Secretaria">
                            </label>
                            <button class="botao secundario" type="submit">Fechar período</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    </section>
@endsection
