@extends('layouts.municipal')
@section('title', 'Busca ativa escolar')
@section('body-class', 'pagina-busca-ativa')
@push('head')
    <link rel="stylesheet" href="{{ asset('css/busca-ativa.css') }}?v={{ filemtime(public_path('css/busca-ativa.css')) }}">
@endpush
@php($params = ['municipio' => $municipality->slug, 'escola' => $school->id])
@section('content')
    <div class="folha-titulo"><div><span class="contexto">{{ $school->name }}</span><h1>Busca ativa escolar</h1><p>Identifique riscos pela chamada e acompanhe as providências com a família.</p></div></div>
    <section class="folha">
        <div class="folha-cabecalho"><div><h2>Período de acompanhamento</h2><p>Uso restrito à gestão da unidade e à gestão municipal.</p></div></div>
        <div class="folha-corpo">
            <form method="GET" action="{{ route('school.active-search.index', $params) }}" class="busca-ativa-form">
                <label>Ano letivo <select class="entrada" name="ano" required>@foreach($years as $option)<option value="{{ $option->year }}" @selected($option->id === $year?->id)>{{ $option->year }}</option>@endforeach</select></label>
                <button type="submit" class="botao">Consultar</button>
            </form>
            @if($settings)<p class="texto-ajuda">Alerta: {{ $settings['days'] }} faltas não justificadas consecutivas em dias letivos ou frequência mensal inferior a {{ $settings['percentage'] }}%. Dias sem chamada não são considerados faltas.</p>@endif
        </div>
    </section>
    @if($year)
        <section class="folha">
            <div class="folha-cabecalho"><div><h2>Alertas automáticos</h2><p>Confira a frequência antes de iniciar contato ou encaminhamento.</p></div></div>
            <div class="rolagem busca-ativa-rolagem"><table class="tabela busca-ativa-tabela">
                <thead><tr><th scope="col">Aluno</th><th scope="col">Turma</th><th scope="col">Faltas seguidas</th><th scope="col">Frequência mensal</th><th scope="col">Ficha</th></tr></thead>
                <tbody>
                @forelse($alerted as $entry)
                    <tr><th scope="row">{{ $entry->student->full_name }}</th><td>{{ $entry->schoolClass->name }}</td><td>{{ $summaries[$entry->id]['streak'] }}</td><td>{{ $summaries[$entry->id]['monthly'] === null ? 'Sem chamada' : number_format($summaries[$entry->id]['monthly'], 2, ',', '.').'%' }}</td><td><a href="{{ route('school.active-search.detail', [...$params, 'matricula' => $entry->id]) }}">Consultar ficha</a></td></tr>
                @empty
                    <tr><td colspan="5">Nenhum alerta automático para o recorte.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>
        <section class="folha">
            <div class="folha-cabecalho"><div><h2>Casos registrados</h2><p>O histórico permanece mesmo quando o alerta deixa de aparecer.</p></div></div>
            <div class="rolagem busca-ativa-rolagem"><table class="tabela busca-ativa-tabela">
                <thead><tr><th scope="col">Aluno</th><th scope="col">Abertura</th><th scope="col">Situação</th><th scope="col">Ficha</th></tr></thead>
                <tbody>
                @forelse($cases as $case)
                    @php($entry = $caseEnrollments->get($case->enrollment_id))
                    <tr><th scope="row">{{ $entry?->student?->full_name ?? 'Matrícula indisponível' }}</th><td>{{ $case->opened_at->format('d/m/Y') }}</td><td>{{ \App\Models\Tenant\ActiveSearchCase::STATUSES[$case->status] }}</td><td>@if($entry)<a href="{{ route('school.active-search.detail', [...$params, 'matricula' => $entry->id]) }}">Consultar ficha</a>@endif</td></tr>
                @empty
                    <tr><td colspan="4">Nenhum caso registrado.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>
    @else
        <section class="folha"><div class="folha-corpo"><p>Nenhum ano letivo disponível.</p></div></section>
    @endif
@endsection
