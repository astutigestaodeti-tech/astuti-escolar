@extends('layouts.municipal')
@section('title', 'Pedido de atendimento')
@section('body-class', 'pagina-equipe equipe-restrita')
@push('head')
    <link rel="stylesheet" href="{{ asset('css/equipe-multiprofissional.css') }}?v={{ filemtime(public_path('css/equipe-multiprofissional.css')) }}">
@endpush
@php
    $params = ['municipio' => $municipality->slug, 'pedido' => $entry->id];
    $areaLabels = \App\Models\Tenant\MultiprofessionalRequest::AREAS;
    $reasonLabels = \App\Models\Tenant\MultiprofessionalRequest::REASONS;
    $statusLabels = \App\Models\Tenant\MultiprofessionalRequest::STATUSES;
    $closings = \App\Models\Tenant\MultiprofessionalRequest::CLOSINGS;
    $destinations = \App\Models\Tenant\MultiprofessionalReferral::DESTINATIONS;
    $returns = \App\Models\Tenant\MultiprofessionalReferral::RETURNS;
@endphp
@section('content')
    <div class="folha-titulo"><div><span class="contexto">Acesso restrito à equipe</span><h1>Pedido de atendimento</h1><p>{{ $entry->student?->full_name }} | {{ $entry->school?->name }}</p></div><a class="botao secundario" href="{{ route('team.index', ['municipio' => $municipality->slug]) }}">Voltar à fila</a></div>
    @if (session('status'))<div class="status-sucesso" role="status">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="erro" role="alert">{{ $errors->first() }}</div>@endif
    <section class="folha">
        <div class="folha-cabecalho"><div><h2>Informações do pedido</h2><p>Fluxo de trabalho, sem registro técnico sigiloso.</p></div></div>
        <div class="folha-corpo">
            <dl class="equipe-dados"><div><dt>Área</dt><dd>{{ $areaLabels[$entry->area] }}</dd></div><div><dt>Motivo (categoria)</dt><dd>{{ $reasonLabels[$entry->reason_category] }}</dd></div><div><dt>Situação</dt><dd>{{ $statusLabels[$entry->status] }}</dd></div><div><dt>Abertura</dt><dd>{{ $entry->opened_at->format('d/m/Y H:i') }}</dd></div><div><dt>Agenda</dt><dd>{{ $entry->scheduled_at?->format('d/m/Y H:i') ?? 'Não agendado' }}</dd></div></dl>
            @if ($entry->closing_category)<p>Encerramento: {{ $closings[$entry->closing_category] }} em {{ $entry->closed_at->format('d/m/Y') }}.</p>@endif
            @unless ($owner)
                <form method="POST" action="{{ route('team.claim', $params) }}" class="equipe-form">
                    @csrf
                    <label>Área de atendimento <select class="entrada" name="area" required>@foreach ($areas as $key)@if ($entry->area === 'undetermined' || $entry->area === $key)<option value="{{ $key }}">{{ $areaLabels[$key] }}</option>@endif @endforeach</select></label>
                    <button class="botao" type="submit">Assumir pedido</button>
                </form>
            @endunless
        </div>
    </section>
    @if ($owner && $entry->status !== 'closed')
        <section class="folha">
            <div class="folha-cabecalho"><div><h2>Agenda e atendimento</h2><p>Registre somente datas, sem descrição da sessão.</p></div></div>
            <div class="folha-corpo">
                <form method="POST" action="{{ route('team.schedule', $params) }}" class="equipe-form">
                    @csrf
                    <label>Data e horário <input class="entrada" name="agenda" type="datetime-local" required value="{{ $entry->scheduled_at?->format('Y-m-d\TH:i') }}"></label>
                    <button class="botao" type="submit">Agendar atendimento</button>
                </form>
                <form method="POST" action="{{ route('team.attend', $params) }}" class="equipe-form">
                    @csrf
                    <label>Data do atendimento realizado <input class="entrada" name="data" type="date" max="{{ today()->toDateString() }}" value="{{ today()->toDateString() }}" required></label>
                    <button class="botao secundario" type="submit">Registrar atendimento</button>
                </form>
                <p class="texto-ajuda">Um registro de atendimento por pedido e dia. Não registre atendimento que não aconteceu.</p>
            </div>
        </section>
        <section class="folha">
            <div class="folha-cabecalho"><div><h2>Devolutiva à escola</h2><p>Somente a orientação prática para a direção e o professor da turma.</p></div></div>
            <div class="folha-corpo">
                <p class="texto-ajuda">Não inclua análise técnica, hipótese diagnóstica, CID, relato familiar, teste, parecer ou conteúdo de sessão. Esta orientação será lida na escola.</p>
                <form method="POST" action="{{ route('team.guidance', $params) }}" class="equipe-form">
                    @csrf
                    <label class="equipe-largo">Orientação prática (até 500 caracteres) <textarea class="entrada" name="devolutiva" rows="3" maxlength="500" required>{{ old('devolutiva', $entry->school_guidance) }}</textarea></label>
                    <button class="botao" type="submit">Salvar devolutiva</button>
                </form>
            </div>
        </section>
        <section class="folha">
            <div class="folha-cabecalho"><div><h2>Rede de proteção</h2><p>Registre o destino e a data do encaminhamento realizado.</p></div></div>
            <div class="folha-corpo">
                <form method="POST" action="{{ route('team.refer', $params) }}" class="equipe-form">
                    @csrf
                    <label>Destino <select name="destino" class="entrada" required>@foreach ($destinations as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
                    <label>Data <input name="data" class="entrada" type="date" max="{{ today()->toDateString() }}" value="{{ today()->toDateString() }}" required></label>
                    <button class="botao" type="submit">Registrar encaminhamento</button>
                </form>
            </div>
        </section>
    @endif
    @if ($owner)
        <section class="folha">
            <div class="folha-cabecalho"><div><h2>Atendimentos registrados</h2><p>Apenas datas, sem conteúdo técnico.</p></div></div>
            <div class="folha-corpo"><ul>@forelse ($entry->attendances as $item)<li>{{ $item->attended_on->format('d/m/Y') }}</li>@empty<li>Nenhum atendimento registrado.</li>@endforelse</ul></div>
        </section>
        <section class="folha">
            <div class="folha-cabecalho"><div><h2>Encaminhamentos e contrarreferências</h2><p>Registre somente o retorno em categoria fechada. Não anexe documentos técnicos.</p></div></div>
            <div class="folha-corpo">
                @forelse ($entry->referrals as $item)
                    <div class="equipe-encaminhamento">
                        <h3>{{ $destinations[$item->destination] }} | {{ $item->referred_on->format('d/m/Y') }}</h3>
                        <p>Retorno: {{ $item->returned_on ? $item->returned_on->format('d/m/Y').' - '.$returns[$item->return_category] : 'Não informado' }}</p>
                        <a class="botao secundario" href="{{ route('team.document', [...$params, 'encaminhamento' => $item->id]) }}">Visualizar encaminhamento</a>
                        <form method="POST" action="{{ route('team.return', [...$params, 'encaminhamento' => $item->id]) }}" class="equipe-form">
                            @csrf
                            <label>Data do retorno <input type="date" name="data" class="entrada" min="{{ $item->referred_on->toDateString() }}" max="{{ today()->toDateString() }}" value="{{ $item->returned_on?->toDateString() }}" required></label>
                            <label>Retorno <select name="retorno" class="entrada" required>@foreach ($returns as $key => $label)<option value="{{ $key }}" @selected($item->return_category === $key)>{{ $label }}</option>@endforeach</select></label>
                            <button class="botao secundario" type="submit">Registrar retorno</button>
                        </form>
                    </div>
                @empty<p>Nenhum encaminhamento registrado.</p>@endforelse
            </div>
        </section>
        @if ($entry->status === 'closed' && $entry->school_guidance)
            <section class="folha"><div class="folha-cabecalho"><h2>Última devolutiva à escola</h2></div><div class="folha-corpo"><p>{{ $entry->school_guidance }}</p></div></section>
        @endif
        @if ($entry->status !== 'closed')
            <section class="folha">
                <div class="folha-cabecalho"><div><h2>Encerrar pedido</h2><p>O encerramento preserva o acompanhamento registrado.</p></div></div>
                <div class="folha-corpo">
                    <form method="POST" action="{{ route('team.close', $params) }}" class="equipe-form">
                        @csrf
                        <label>Motivo do encerramento <select name="motivo" class="entrada" required><option value="">Selecione</option>@foreach ($closings as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
                        <button class="botao secundario" type="submit">Encerrar pedido</button>
                    </form>
                </div>
            </section>
        @endif
    @endif
@endsection
