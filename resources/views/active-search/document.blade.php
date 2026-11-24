@extends('layouts.municipal')
@section('timbre-proprio', '1')
@section('title', 'Encaminhamento ao Conselho Tutelar')
@section('body-class', 'impressao-declaracao pagina-busca-ativa')
@push('head')
    <link rel="stylesheet" href="{{ asset('css/busca-ativa.css') }}?v={{ filemtime(public_path('css/busca-ativa.css')) }}">
@endpush
@php
    $summary = $document->referral_snapshot['summary'];
    $contacts = $document->referral_snapshot['contacts'];
    $channels = \App\Models\Tenant\ActiveSearchContact::CHANNELS;
    $outcomes = \App\Models\Tenant\ActiveSearchContact::OUTCOMES;
    $params = ['municipio' => $municipality->slug, 'escola' => $school->id, 'matricula' => $entry->id];
@endphp
@section('content')
    <div class="folha-titulo declaracao-acoes"><div><span class="contexto">Documento restrito</span><h1>Encaminhamento ao Conselho Tutelar</h1><p>Imprima em A4, colha a assinatura da direção e encaminhe por canal institucional protegido.</p></div><button type="button" class="botao" data-action="print-declaration">Imprimir</button></div>
    @if(session('status'))<div class="status-sucesso declaracao-acoes" role="status">{{ session('status') }}</div>@endif
    <section class="folha declaracao-papel">
        <div class="folha-corpo">
            <x-documento.timbre :municipality="$municipality" :municipality-name="$document->snapshot_municipality_name"
                :school-name="$document->snapshot_school_name" :school-inep="$school->inep_code" />
            <h2 class="declaracao-titulo">Encaminhamento ao Conselho Tutelar</h2>
            @if($document->revoked_at)<p class="declaracao-revogada">DOCUMENTO REVOGADO - sem validade</p>@endif
            <p>Documento de acesso restrito, para avaliação e providências de proteção da frequência escolar. Estudante: <strong>{{ $document->snapshot_student_name }}</strong>. Ano letivo: {{ $document->snapshot_school_year }}. Turma: {{ $document->snapshot_class_name ?? 'Não informada' }}.</p>
            <p>Faltas não justificadas no período: {{ $summary['absences'] }}. Justificadas: {{ $summary['justified'] }}. Sequência de faltas não justificadas: {{ $summary['streak'] }}. Frequência no mês de referência ({{ $summary['month'] ?? 'não informado' }}): {{ $summary['monthly'] === null ? 'Sem chamada' : number_format($summary['monthly'], 2, ',', '.').'%' }}.</p>
            <h3>Providências de contato com a família</h3>
            <div class="rolagem busca-ativa-rolagem"><table class="tabela busca-ativa-tabela">
                <thead><tr><th scope="col">Data</th><th scope="col">Meio</th><th scope="col">Resultado</th></tr></thead>
                <tbody>@foreach($contacts as $contact)<tr><td>{{ \Illuminate\Support\Carbon::parse($contact['date'])->format('d/m/Y') }}</td><td>{{ $channels[$contact['channel']] }}</td><td>{{ $outcomes[$contact['outcome']] }}</td></tr>@endforeach</tbody>
            </table></div>
            <p>Solicita-se a avaliação das providências pertinentes, sem caracterização automática de abandono ou responsabilização familiar.</p>
            <p class="declaracao-data">{{ $document->issued_at->format('d/m/Y') }}.</p>
            <div class="declaracao-assinatura"><span>Assinatura da direção</span></div>
            <footer class="declaracao-rodape"><div class="declaracao-verificacao"><strong>Verificação de autenticidade</strong><p>Código: {{ $document->verification_code }}</p><p>Endereço: <span class="declaracao-url">{{ $verificationUrl }}</span></p></div><div class="declaracao-qr">{!! $qrSvg !!}</div></footer>
            <x-documento.rodape :municipality="$municipality" :school="$school" />
        </div>
    </section>
    @unless($document->revoked_at)
        <section class="folha declaracao-acoes">
            <div class="folha-cabecalho"><div><h2>Revogar encaminhamento</h2><p>O documento permanece no histórico, marcado como inválido.</p></div></div>
            <div class="folha-corpo">
                <form method="POST" action="{{ route('school.active-search.revoke', [...$params, 'documento' => $document->id]) }}" class="busca-ativa-confirmar">
                    @csrf
                    <label><input type="checkbox" name="confirmar" value="1" required> Confirmo a revogação do documento.</label>
                    <button type="submit" class="botao secundario">Revogar documento</button>
                </form>
            </div>
        </section>
    @endunless
    <script type="module" src="{{ asset('js/declaracao-imprimir.js') }}"></script>
@endsection
