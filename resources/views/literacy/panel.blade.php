@extends('layouts.municipal')

@section('title', $school ? 'Alfabetização da escola' : 'Alfabetização da rede')
@section('body-class', 'pagina-alfabetizacao')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/alfabetizacao.css') }}?v={{ filemtime(public_path('css/alfabetizacao.css')) }}">
@endpush

@php
    $params = ['municipio' => $municipality->slug];
    $schoolParams = $school ? [...$params, 'escola' => $school->id] : $params;
    $routeName = $school ? 'school.literacy.panel' : 'literacy.network';
@endphp

@section('content')
    <div class="folha-titulo"><div><span class="contexto">{{ $school?->name ?? 'Secretaria Municipal de Educação' }}</span><h1>{{ $school ? 'Alfabetização da escola' : 'Alfabetização da rede' }}</h1><p>Acompanhamento pedagógico do 1º e 2º ano do Ensino Fundamental.</p></div></div>
    @if (session('status'))<div class="status-sucesso" role="status">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="erro" role="alert">{{ $errors->first() }}</div>@endif
    <section class="folha">
        <div class="folha-cabecalho"><div><h2>Período de acompanhamento</h2><p>Sondagem interna da rede, em quatro bimestres.</p></div></div>
        <div class="folha-corpo">
            @if ($years->isNotEmpty())
                <form method="GET" action="{{ route($routeName, $schoolParams) }}" class="alfabetizacao-form">
                    <label>Ano letivo <select class="entrada" name="ano">@foreach ($years as $option)<option value="{{ $option->id }}" @selected($year?->id === $option->id)>{{ $option->year }}</option>@endforeach</select></label>
                    <label>Bimestre para detalhamento <select class="entrada" name="bimestre">@foreach(range(1, 4) as $n)<option value="{{ $n }}" @selected($term === $n)>{{ $n }}º bimestre</option>@endforeach</select></label>
                    <button class="botao" type="submit">Consultar</button>
                </form>
            @else
                <p>Nenhum ano letivo cadastrado.</p>
            @endif
        </div>
    </section>
    @if ($year)
        <section class="folha">
            <div class="folha-cabecalho"><div><h2>Escrita alfabética ao fim do 2º ano</h2><p>Resultado da sondagem do 4º bimestre.</p></div></div>
            <div class="folha-corpo">
                <p><strong>{{ $second['alphabetic_pct'] === null ? 'Sem sondagem' : number_format($second['alphabetic_pct'], 1, ',', '.').'%' }}</strong> entre os avaliados.</p>
                <p>{{ $second['writing']['alphabetic'] }} alfabéticos; {{ $second['assessed'] }} avaliados de {{ $second['total'] }} matrículas no recorte. {{ $second['missing'] }} sem sondagem.</p>
                <p class="texto-ajuda">Indicador interno, não corresponde ao resultado oficial do SARESP, ICMS Educação ou VAAR.</p>
            </div>
        </section>
        @include('literacy.distribution')
        <section class="folha">
            <div class="folha-cabecalho"><div><h2>{{ $school ? 'Distribuição por turma' : 'Distribuição por escola' }}</h2><p>{{ $term }}º bimestre, com quantidade de avaliados e alfabéticos.</p></div></div>
            <div class="rolagem alfabetizacao-rolagem"><table class="tabela alfabetizacao-tabela">
                <thead><tr><th scope="col">{{ $school ? 'Turma' : 'Escola' }}</th><th scope="col">Matrículas</th><th scope="col">Avaliados</th><th scope="col">Alfabéticos</th><th scope="col">Percentual alfabético</th></tr></thead>
                <tbody>
                    @forelse ($school ? $classRows : $schoolRows as $item)
                        @php($row = $item['distribution'][$term])
                        <tr><th scope="row">
                            @if ($school)
                                <a href="{{ route('school.literacy.classroom', [...$schoolParams, 'turma' => $item['class']->id, 'bimestre' => $term]) }}">{{ $item['class']->name }}</a>
                            @else
                                <a href="{{ route('school.literacy.panel', [...$params, 'escola' => $item['school']->id, 'ano' => $year->id, 'bimestre' => $term]) }}">{{ $item['school']->name }}</a>
                            @endif
                        </th><td>{{ $row['total'] }}</td><td>{{ $row['assessed'] }}</td><td>{{ $row['writing']['alphabetic'] }}</td><td>{{ $row['alphabetic_pct'] === null ? 'Sem dados' : number_format($row['alphabetic_pct'], 1, ',', '.').'%' }}</td></tr>
                    @empty
                        <tr><td colspan="5">Nenhuma turma elegível encontrada.</td></tr>
                    @endforelse
                </tbody>
            </table></div>
        </section>
        @if ($nominal)
            <section class="folha">
                <div class="folha-cabecalho"><div><h2>Alunos para avaliação de reforço</h2><p>Triagem do {{ $term }}º bimestre: escrita ainda não alfabética ou leitura ainda sem fluência em textos. Avalie as necessidades de cada estudante.</p></div></div>
                <div class="rolagem alfabetizacao-rolagem"><table class="tabela alfabetizacao-tabela">
                    <thead><tr><th scope="col">Aluno</th><th scope="col">Escrita</th><th scope="col">Leitura</th><th scope="col">Sondagem</th></tr></thead>
                    <tbody>
                    @forelse ($support as $item)
                        <tr><th scope="row">{{ $item['enrollment']->student->full_name }}</th><td>{{ \App\Models\Tenant\LiteracyAssessment::WRITING[$item['assessment']->writing] }}</td><td>{{ \App\Models\Tenant\LiteracyAssessment::READING[$item['assessment']->reading] }}</td><td><a href="{{ route('school.literacy.classroom', [...$schoolParams, 'turma' => $item['enrollment']->school_class_id, 'bimestre' => $term]) }}">Consultar turma</a></td></tr>
                    @empty
                        <tr><td colspan="4">Nenhuma sondagem registrada atende ao critério de triagem.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
                <div class="folha-corpo"><p class="texto-ajuda">Lista restrita à escola. Alunos sem sondagem devem ser avaliados e não são classificados automaticamente.</p></div>
            </section>
        @elseif (! $school)
            <section class="folha">
                <div class="folha-cabecalho"><div><h2>Política do ciclo de alfabetização</h2><p>Configuração por ano letivo e série, exclusiva da gestão municipal.</p></div></div>
                <div class="folha-corpo">
                    <form method="POST" action="{{ route('literacy.settings', $params) }}" class="alfabetizacao-form">
                        @csrf
                        <input type="hidden" name="ano" value="{{ $year->id }}">
                        <label>1º ano <select class="entrada" name="retain_first"><option value="1" @selected($settings[1])>Permite retenção</option><option value="0" @selected(!$settings[1])>Ciclo sem retenção</option></select></label>
                        <label>2º ano <select class="entrada" name="retain_second"><option value="1" @selected($settings[2])>Permite retenção</option><option value="0" @selected(!$settings[2])>Ciclo sem retenção</option></select></label>
                        <button class="botao" type="submit">Salvar política</button>
                    </form>
                    <p class="texto-ajuda">Sem retenção, apenas a sugestão Retido passa a Aprovado com acompanhamento. A secretaria ou direção continua confirmando a decisão. Resultados já confirmados não são modificados. Até a configuração expressa, mantém-se a regra anterior.</p>
                </div>
            </section>
        @endif
    @endif
@endsection
