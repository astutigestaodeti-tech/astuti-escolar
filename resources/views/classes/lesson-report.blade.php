@extends('layouts.municipal')

@section('title', 'Conteúdos ministrados - '.$class->name)
@section('body-class', 'impressao-conteudos')

@php
    $params = ['municipio' => $municipality->slug, 'escola' => $school->id, 'turma' => $class->id];
@endphp

@push('head')
    <link rel="stylesheet" href="{{ asset('css/conteudo-aula.css') }}?v={{ filemtime(public_path('css/conteudo-aula.css')) }}">
    <script type="module" src="{{ asset('js/lesson-report-print.js') }}"></script>
@endpush

@section('trilha')
    <a href="{{ route('school.classes.show', $params) }}">Turma {{ $class->name }}</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Conteúdos ministrados</span>
@endsection

@section('content')
    <div class="folha-titulo aula-relatorio-acoes">
        <div><span class="contexto">Diário da turma</span><h1>Conteúdos ministrados</h1></div>
        <a class="botao secundario" href="{{ route('school.classes.show', $params) }}">Voltar à turma</a>
        <button type="button" class="botao primario" data-action="print-lesson-report">Imprimir</button>
    </div>
    <section class="folha aula-relatorio">
        <header class="folha-cabecalho"><div><h2>{{ $school->name }} | {{ $class->name }}</h2><p>{{ $municipality->legal_name }} | Ano letivo {{ $class->schoolYear->year }}</p></div></header>
        <div class="folha-corpo aula-campos">
            <form method="GET" class="aula-filtros aula-relatorio-acoes">
                <label>Período <select class="entrada" name="periodo" @disabled($periods->isEmpty())>
                    @foreach ($periods as $p)<option value="{{ $p->id }}" @selected($period?->id === $p->id)>{{ $p->number }}º {{ $p->kind }}</option>@endforeach
                </select></label>
                <button type="submit" class="botao secundario" @disabled($periods->isEmpty())>Consultar</button>
            </form>
            @if ($period)
                <h3>Conteúdos ministrados | {{ $period->number }}º {{ $period->kind }}</h3>
                <p>{{ $period->starts_on->format('d/m/Y') }} a {{ $period->ends_on->format('d/m/Y') }} | {{ $records->count() }} registro(s)</p>
                @forelse ($records as $record)
                    <article class="aula-relatorio-item">
                        <h4>{{ $record->lesson_date->format('d/m/Y') }} | {{ $record->component?->name ?? 'Polivalente (sem componente)' }}</h4>
                        <p class="aula-texto">{{ $record->content }}</p>
                        @if ($record->activity)<p class="aula-texto"><strong>Atividade:</strong> {{ $record->activity }}</p>@endif
                        @if ($record->bncc_codes !== [])<p><strong>BNCC:</strong> {{ implode(', ', $record->bncc_codes) }}</p>@endif
                    </article>
                @empty
                    <p>Nenhum conteúdo registrado neste período.</p>
                @endforelse
            @else
                <p>Não há períodos acadêmicos cadastrados.</p>
            @endif
        </div>
    </section>
@endsection
