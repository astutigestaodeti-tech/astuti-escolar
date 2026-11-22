@extends('layouts.municipal')

@php
    $titulo = 'Desvincular responsável';
    $parametros = ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id];
    $parametrosVinculo = [...$parametros, 'vinculo' => $link->id];
    $voltar = route('school.students.show', $parametros).'#responsaveis';
@endphp

@section('title', $titulo.' - '.$student->full_name)

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('schools.show', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('school.students.show', $parametros) }}">{{ $student->full_name }}</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">{{ $titulo }}</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Secretaria escolar</span>
            <h1>{{ $titulo }}</h1>
            <p>Confira o responsável e informe o motivo antes de remover o vínculo com o aluno.</p>
        </div>
    </div>

    <section class="folha formulario-aluno" aria-labelledby="titulo-desvinculo">
        <div class="folha-cabecalho">
            <div>
                <h2 id="titulo-desvinculo">Confirmar desvínculo</h2>
                <p>Aluno: {{ $student->full_name }}</p>
                <p>Responsável: <strong>{{ $guardian->full_name }}</strong> ({{ $relationship }})</p>
            </div>
        </div>

        <div class="nota" role="note">
            <x-icon name="info" :size="18" />
            <div>
                <strong>Apenas o vínculo será removido.</strong>
                <p>O cadastro municipal do responsável será preservado, inclusive para outros alunos da família. Se ele for o contato principal, ninguém será indicado automaticamente no lugar.</p>
            </div>
        </div>

        @if ($errors->any())
            <div class="erro" role="alert">
                <x-icon name="alerta" :size="18" />
                <div>
                    <strong>Revise o formulário</strong>
                    <ul>
                        @foreach ($errors->all() as $mensagem)
                            <li>{{ $mensagem }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <form method="POST" novalidate action="{{ route('school.students.guardians.unlink', $parametrosVinculo) }}">
            @csrf
            <div class="formulario-grade">
                <div class="formulario-campo largo">
                    <label class="rotulo" for="motivo">Motivo do desvínculo *</label>
                    <textarea class="entrada" id="motivo" name="motivo" rows="4" minlength="10" maxlength="500" required aria-describedby="motivo-ajuda">{{ old('motivo') }}</textarea>
                    <p class="texto-ajuda" id="motivo-ajuda">Escreva de 10 a 500 caracteres. O motivo e a autoria ficarão registrados na auditoria.</p>
                    @error('motivo')<p class="campo-erro">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="formulario-acoes">
                <button class="botao" type="submit">Confirmar desvínculo</button>
                <a class="botao secundario" href="{{ $voltar }}">Cancelar</a>
            </div>
        </form>
    </section>
@endsection
