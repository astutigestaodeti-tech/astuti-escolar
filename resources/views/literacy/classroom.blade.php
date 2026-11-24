@extends('layouts.municipal')

@section('title', 'Sondagem de alfabetização')
@section('body-class', 'pagina-alfabetizacao')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/alfabetizacao.css') }}?v={{ filemtime(public_path('css/alfabetizacao.css')) }}">
@endpush

@php
    $params = ['municipio' => $municipality->slug, 'escola' => $school->id, 'turma' => $class->id];
    $writing = \App\Models\Tenant\LiteracyAssessment::WRITING;
    $reading = \App\Models\Tenant\LiteracyAssessment::READING;
@endphp

@section('content')
    <div class="folha-titulo"><div><span class="contexto">{{ $school->name }} | {{ $class->schoolYear->year }}</span><h1>Sondagem de alfabetização</h1><p>{{ $class->name }}. Registro acadêmico restrito à gestão e aos professores vinculados.</p></div><a class="botao secundario" href="{{ route('school.classes.show', $params) }}">Voltar à turma</a></div>
    @if (session('status'))<div class="status-sucesso" role="status">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="erro" role="alert">{{ $errors->first() }}</div>@endif
    <section class="folha">
        <div class="folha-cabecalho"><div><h2>Bimestre da sondagem</h2><p>Selecione o período para consultar ou lançar.</p></div></div>
        <div class="folha-corpo">
            <form method="GET" action="{{ route('school.literacy.classroom', $params) }}" class="alfabetizacao-form">
                <label>Bimestre <select class="entrada" name="bimestre">@foreach (range(1, 4) as $n)<option value="{{ $n }}" @selected($term === $n)>{{ $n }}º bimestre</option>@endforeach</select></label>
                <button class="botao" type="submit">Consultar</button>
            </form>
            @if ($closed)<p class="texto-ajuda">Ano letivo encerrado. Somente consulta.</p>
            @elseif ($periodClosed)<p class="texto-ajuda">Bimestre fechado. Correção exclusiva da gestão com justificativa.</p>
            @endif
            <p class="texto-ajuda">Sondagem pedagógica, sem nota numérica e sem alteração automática do resultado final.</p>
        </div>
    </section>
    <section class="folha">
        <div class="folha-cabecalho"><div><h2>Registros do {{ $term }}º bimestre</h2><p>Informe os níveis observados na escrita e na leitura. Sem registro permanece sem sondagem.</p></div></div>
        <div class="folha-corpo">
            @forelse ($enrollments as $entry)
                @php($record = $records->get($entry->id))
                <div class="alfabetizacao-aluno">
                    <h3>{{ $entry->student->full_name }}</h3>
                    @if ($canWrite)
                        <form method="POST" action="{{ route('school.literacy.save', $params) }}" class="alfabetizacao-form">
                            @csrf
                            <input type="hidden" name="bimestre" value="{{ $term }}">
                            <input type="hidden" name="matricula" value="{{ $entry->id }}">
                            <label>Escrita <select class="entrada" name="escrita" required><option value="">Selecione</option>@foreach($writing as $key => $label)<option value="{{ $key }}" @selected($record?->writing === $key)>{{ $label }}</option>@endforeach</select></label>
                            <label>Leitura <select class="entrada" name="leitura" required><option value="">Selecione</option>@foreach($reading as $key => $label)<option value="{{ $key }}" @selected($record?->reading === $key)>{{ $label }}</option>@endforeach</select></label>
                            @if ($periodClosed)<label>Justificativa da correção <input class="entrada" name="motivo" minlength="10" maxlength="500" required></label>@endif
                            <button type="submit" class="botao secundario">Salvar sondagem</button>
                        </form>
                    @elseif ($record)
                        <p>Escrita: {{ $writing[$record->writing] }}. Leitura: {{ $reading[$record->reading] }}.</p>
                    @else
                        <p>Sem sondagem.</p>
                    @endif
                </div>
            @empty
                <p>Nenhuma matrícula ativa nesta turma.</p>
            @endforelse
        </div>
    </section>
    @include('literacy.distribution')
@endsection
