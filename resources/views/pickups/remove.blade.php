@extends('layouts.municipal')

@php
    $parametros = ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id];
    $voltar = route('school.students.show', $parametros).'#quem-pode-buscar';
@endphp

@section('title', 'Remover autorização de retirada - '.$student->full_name)

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('schools.show', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('school.students.show', $parametros) }}">{{ $student->full_name }}</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Remover autorização</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Segurança na saída</span>
            <h1>Remover autorização de retirada</h1>
            <p>Confira os dados da pessoa e registre o motivo antes de remover a autorização.</p>
        </div>
    </div>

    <section class="folha formulario-aluno" aria-labelledby="titulo-remover-retirada">
        <div class="folha-cabecalho">
            <div>
                <h2 id="titulo-remover-retirada">Confirmar remoção</h2>
                <p>Aluno: {{ $student->full_name }}</p>
                <p>Pessoa: <strong>{{ $authorization->full_name }}</strong></p>
                <p>Relação: {{ $authorization->relationship ?? 'Não informada' }}</p>
            </div>
        </div>
        <div class="nota" role="note">
            <x-icon name="info" :size="18" />
            <div><strong>A autorização de retirada será removida.</strong><p>Os vínculos dos responsáveis não serão alterados. A remoção e seu motivo ficarão na auditoria; será solicitado segundo fator recente.</p></div>
        </div>

        @if ($errors->any())
            <div class="erro" role="alert">
                <x-icon name="alerta" :size="18" />
                <div><strong>Revise o formulário</strong><ul>
                    @foreach ($errors->all() as $mensagem)
                        <li>{{ $mensagem }}</li>
                    @endforeach
                </ul></div>
            </div>
        @endif

        <form method="POST" novalidate action="{{ route('school.students.pickups.remove', [...$parametros, 'autorizacao' => $authorization->id]) }}">
            @csrf
            <div class="formulario-grade">
                <div class="formulario-campo largo">
                    <label class="rotulo" for="motivo">Motivo da remoção *</label>
                    <textarea class="entrada" id="motivo" name="motivo" rows="4" minlength="10" maxlength="500" required aria-describedby="motivo-ajuda">{{ old('motivo') }}</textarea>
                    <p class="texto-ajuda" id="motivo-ajuda">De 10 a 500 caracteres. O motivo e a autoria serão registrados.</p>
                    @error('motivo')<p class="campo-erro">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="formulario-acoes">
                <button class="botao" type="submit">Confirmar remoção</button>
                <a class="botao secundario" href="{{ $voltar }}">Cancelar</a>
            </div>
        </form>
    </section>
@endsection
