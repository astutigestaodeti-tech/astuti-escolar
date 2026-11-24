@extends('layouts.municipal')
@section('timbre-proprio', '1')
@section('title', 'Encaminhamento à rede de proteção')
@section('body-class', 'impressao-declaracao pagina-equipe')
@php
    $destinations = \App\Models\Tenant\MultiprofessionalReferral::DESTINATIONS;
@endphp
@section('content')
    <div class="folha-titulo declaracao-acoes"><div><span class="contexto">Documento restrito</span><h1>Encaminhamento à rede de proteção</h1><p>Via administrativa para assinatura da profissional responsável e envio por canal institucional protegido.</p></div><button class="botao" type="button" data-action="print-declaration">Imprimir A4</button></div>
    <section class="folha declaracao-papel">
        <div class="folha-corpo">
            <x-documento.timbre :municipality="$municipality" :school-name="$entry->school->name" :school-inep="$entry->school->inep_code" />
            <h2 class="declaracao-titulo">Encaminhamento à rede de proteção</h2>
            <p>Destinatário: <strong>{{ $destinations[$referral->destination] }}</strong>.</p>
            <p>Estudante: <strong>{{ $entry->student->full_name }}</strong>.</p>
            <p>Data do encaminhamento: {{ $referral->referred_on->format('d/m/Y') }}.</p>
            <p>Solicita-se a avaliação das providências pertinentes pelo serviço destinatário, no âmbito da atuação articulada da rede de proteção e educação. Solicitamos retorno institucional à equipe multiprofissional.</p>
            <p>Este documento não contém avaliação técnica, diagnóstico, parecer ou prontuário e não os substitui.</p>
            <div class="declaracao-assinatura"><span>Assinatura da profissional responsável</span></div>
            <x-documento.rodape :municipality="$municipality" :school="$entry->school" />
        </div>
    </section>
    <script type="module" src="{{ asset('js/declaracao-imprimir.js') }}"></script>
@endsection
