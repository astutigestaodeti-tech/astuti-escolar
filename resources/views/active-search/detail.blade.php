@extends('layouts.municipal')
@section('title', 'Ficha de busca ativa')
@section('body-class', 'pagina-busca-ativa')
@push('head')
    <link rel="stylesheet" href="{{ asset('css/busca-ativa.css') }}?v={{ filemtime(public_path('css/busca-ativa.css')) }}">
@endpush
@php
    $params = ['municipio' => $municipality->slug, 'escola' => $school->id, 'matricula' => $entry->id];
    $statuses = \App\Models\Tenant\ActiveSearchCase::STATUSES;
    $reasons = \App\Models\Tenant\ActiveSearchCase::REASONS;
    $channels = \App\Models\Tenant\ActiveSearchContact::CHANNELS;
    $outcomes = \App\Models\Tenant\ActiveSearchContact::OUTCOMES;
@endphp
@section('content')
    <div class="folha-titulo"><div><span class="contexto">Acesso restrito - {{ $school->name }}</span><h1>Ficha de busca ativa</h1><p>{{ $entry->student->full_name }} | {{ $entry->schoolYear->year }} | {{ $entry->schoolClass?->name }}</p></div></div>
    @if(session('status'))<div class="status-sucesso" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="erro" role="alert">{{ $errors->first() }}</div>@endif
    <section class="folha">
        <div class="folha-cabecalho"><div><h2>Frequência e situação</h2><p>Triagem, sem presumir abandono. Dados familiares são restritos.</p></div></div>
        <div class="folha-corpo">
            @if($summary)
                <p>Faltas não justificadas no ano letivo: <strong>{{ $summary['absences'] }}</strong>. Faltas justificadas: {{ $summary['justified'] }}. Sequência atual: {{ $summary['streak'] }}. Frequência mensal: {{ $summary['monthly'] === null ? 'Sem chamada' : number_format($summary['monthly'], 2, ',', '.').'%' }}.</p>
            @endif
            <p>Situação: <strong>{{ $case ? $statuses[$case->status] : 'Sem caso aberto' }}</strong>@if($case) | Abertura: {{ $case->opened_at->format('d/m/Y') }}@endif.</p>
            <form method="POST" action="{{ route('school.active-search.save', $params) }}" class="busca-ativa-form">
                @csrf
                <label>Situação <select name="situacao" class="entrada" required>
                    @if($case?->status === 'referred')<option value="referred" selected>Encaminhado</option>@endif
                    <option value="open" @selected(!$case || $case->status === 'open')>Aberto</option>
                    @if($case)<option value="returned" @selected($case->status === 'returned')>Retornou</option><option value="closed" @selected($case->status === 'closed')>Encerrado</option>@endif
                </select></label>
                <label>Motivo informado (opcional) <select name="motivo" class="entrada"><option value="">Não informado</option>@foreach($reasons as $key => $label)<option value="{{ $key }}" @selected(old('motivo', $case?->reason_category) === $key)>{{ $label }}</option>@endforeach</select></label>
                <button type="submit" class="botao">{{ $case ? 'Salvar ficha' : 'Abrir caso' }}</button>
            </form>
            <p class="texto-ajuda">Não registre laudos, diagnósticos ou relatos pessoais. A situação encaminhado é registrada na emissão do documento. A revogação invalida o papel; a equipe deve atualizar a situação do caso separadamente.</p>
        </div>
    </section>
    @if ($canReferTeam)
        <section class="folha">
            <div class="folha-cabecalho"><div><h2>Equipe multiprofissional</h2><p>Abrir pedido para Serviço Social, sem copiar motivos ou contatos.</p></div></div>
            <div class="folha-corpo">
                <form method="POST" action="{{ route('school.team.active-search', $params) }}" class="busca-ativa-form">
                    @csrf
                    <button class="botao secundario" type="submit">Encaminhar à assistente social</button>
                </form>
            </div>
        </section>
    @endif
    @if($case)
        @if($case->status !== 'closed')
            <section class="folha">
                <div class="folha-cabecalho"><div><h2>Registrar contato com a família</h2><p>Somente data, meio e resultado em categorias fechadas.</p></div></div>
                <div class="folha-corpo">
                    <form method="POST" action="{{ route('school.active-search.contact', $params) }}" class="busca-ativa-form">
                        @csrf
                        <label>Data <input name="data" class="entrada" type="date" max="{{ today()->toDateString() }}" value="{{ old('data', today()->toDateString()) }}" required></label>
                        <label>Meio <select name="meio" class="entrada" required>@foreach($channels as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
                        <label>Resultado <select name="resultado" class="entrada" required>@foreach($outcomes as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
                        <button type="submit" class="botao">Registrar contato</button>
                    </form>
                </div>
            </section>
        @endif
        <section class="folha">
            <div class="folha-cabecalho"><div><h2>Histórico de contatos</h2><p>Registros preservados para acompanhar a busca ativa.</p></div></div>
            <div class="rolagem busca-ativa-rolagem"><table class="tabela busca-ativa-tabela">
                <thead><tr><th scope="col">Data</th><th scope="col">Meio</th><th scope="col">Resultado</th></tr></thead>
                <tbody>@forelse($contacts as $contact)<tr><td>{{ $contact->contact_date->format('d/m/Y') }}</td><td>{{ $channels[$contact->channel] }}</td><td>{{ $outcomes[$contact->outcome] }}</td></tr>@empty<tr><td colspan="3">Nenhum contato registrado.</td></tr>@endforelse</tbody>
            </table></div>
        </section>
        <section class="folha">
            <div class="folha-cabecalho"><div><h2>Encaminhamento ao Conselho Tutelar</h2><p>Emita somente após conferir a frequência e as providências escolares.</p></div></div>
            <div class="folha-corpo">
                @if(in_array($case->status, ['open', 'referred'], true) && $contacts->isNotEmpty())
                    <form method="POST" action="{{ route('school.active-search.issue', $params) }}" class="busca-ativa-confirmar">
                        @csrf
                        <label><input type="checkbox" name="confirmar" value="1" required> Confirmo que a equipe conferiu as faltas e as tentativas de contato e avaliou a necessidade de comunicação ao Conselho Tutelar.</label>
                        <button type="submit" class="botao">Emitir encaminhamento verificável</button>
                    </form>
                @else
                    <p class="texto-ajuda">Abra o caso e registre as tentativas de contato para emitir o documento.</p>
                @endif
                <p class="texto-ajuda">O sistema não envia documentos ao Conselho Tutelar. A unidade deve imprimir, colher assinatura da direção e encaminhar por meio institucional protegido.</p>
                @foreach($documents as $document)
                    <p><a href="{{ route('school.active-search.document', [...$params, 'documento' => $document->id]) }}">Documento de {{ $document->issued_at->format('d/m/Y H:i') }}</a>{{ $document->revoked_at ? ' (revogado)' : '' }}</p>
                @endforeach
            </div>
        </section>
    @endif
@endsection
