@extends('layouts.municipal')
@section('title', 'Equipe multiprofissional')
@section('body-class', 'pagina-equipe equipe-restrita')
@push('head')
    <link rel="stylesheet" href="{{ asset('css/equipe-multiprofissional.css') }}?v={{ filemtime(public_path('css/equipe-multiprofissional.css')) }}">
@endpush
@php
    $params = ['municipio' => $municipality->slug];
    $statuses = \App\Models\Tenant\MultiprofessionalRequest::STATUSES;
    $areas = \App\Models\Tenant\MultiprofessionalRequest::AREAS;
@endphp
@section('content')
    <div class="folha-titulo"><div><span class="contexto">Equipe multiprofissional</span><h1>Fila e agenda da equipe</h1><p>Organização do atendimento educacional. Sem prontuário psicológico ou social.</p></div></div>
    <section class="folha">
        <div class="folha-cabecalho"><div><h2>Consultar pedidos</h2><p>Somente pedidos disponíveis para sua área ou assumidos por você.</p></div></div>
        <div class="folha-corpo">
            <form method="GET" action="{{ route('team.index', $params) }}" class="equipe-form">
                <label>Situação <select class="entrada" name="situacao"><option value="">Em acompanhamento</option>@foreach ($statuses as $key => $label)<option value="{{ $key }}" @selected(($filters['situacao'] ?? null) === $key)>{{ $label }}</option>@endforeach</select></label>
                <label class="equipe-check"><input type="checkbox" name="agenda" value="1" @checked($agenda)> Mostrar somente agenda</label>
                <button class="botao" type="submit">Consultar</button>
                <a class="botao secundario" href="{{ route('team.index', $params) }}">Limpar filtros</a>
            </form>
            @if ($errors->any())<p class="erro" role="alert">{{ $errors->first() }}</p>@endif
        </div>
        <div class="rolagem equipe-rolagem">
            <table class="tabela equipe-tabela">
                <thead><tr><th scope="col">Aluno</th><th scope="col">Escola</th><th scope="col">Área</th><th scope="col">Situação</th><th scope="col">Abertura</th><th scope="col">Agenda</th><th scope="col">Ação</th></tr></thead>
                <tbody>
                    @forelse ($requests as $item)
                        <tr><th scope="row">{{ $item->student?->full_name }}</th><td>{{ $item->school?->name }}</td><td>{{ $areas[$item->area] }}</td><td>{{ $statuses[$item->status] }}{{ $item->assigned_to ? ' (assumido)' : '' }}</td><td>{{ $item->opened_at->format('d/m/Y') }}</td><td>{{ $item->scheduled_at?->format('d/m/Y H:i') ?? 'Não agendado' }}</td><td><a href="{{ route('team.show', [...$params, 'pedido' => $item->id]) }}">Consultar pedido</a></td></tr>
                    @empty<tr><td colspan="7">Nenhum pedido neste recorte.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
        @if ($requests->hasPages())<div class="folha-corpo equipe-apos">{{ $requests->links() }}</div>@endif
    </section>
@endsection
