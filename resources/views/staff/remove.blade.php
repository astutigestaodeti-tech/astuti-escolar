@extends('layouts.municipal')

@php
    $params = ['municipio' => $municipality->slug, 'escola' => $school->id];
@endphp

@section('title', 'Arquivar servidor - '.$school->name)

@section('trilha')
    <a href="{{ route('school.staff.index', $params) }}">Servidores</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Arquivar servidor</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div><span class="contexto">Equipe escolar</span><h1>Arquivar servidor</h1><p>O cadastro será arquivado, preservando o histórico. Encerre primeiro as atribuições abertas.</p></div>
    </div>
    <section class="folha formulario-aluno">
        <h2>{{ $member->full_name }}</h2>
        @if ($errors->any())
            <div class="erro" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <form method="POST" action="{{ route('school.staff.destroy', [...$params, 'servidor' => $member->id]) }}">
            @csrf
            @method('DELETE')
            <div class="formulario-campo">
                <label class="rotulo" for="motivo">Motivo do arquivamento *</label>
                <textarea class="entrada" id="motivo" name="motivo" minlength="10" maxlength="500" required rows="3">{{ old('motivo') }}</textarea>
                <p class="texto-ajuda">De 10 a 500 caracteres. O segundo fator recente será solicitado para confirmar.</p>
            </div>
            <div class="formulario-acoes">
                <button class="botao primario" type="submit">Arquivar servidor</button>
                <a class="botao secundario" href="{{ route('school.staff.index', $params) }}">Cancelar</a>
            </div>
        </form>
    </section>
@endsection
