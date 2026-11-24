@extends('layouts.municipal')

@php
    $parametros = ['municipio' => $municipality->slug, 'escola' => $school->id, 'turma' => $class->id];
    $selecionados = array_map('strval', (array) old('components', $record?->components ?? []));
@endphp

@section('title', 'Atribuição no Censo')
@section('body-class', 'pagina-censo-turmas')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/censo-turmas.css') }}?v={{ filemtime(public_path('css/censo-turmas.css')) }}">
@endpush

@section('trilha')
    <a href="{{ route('school.classes.show', $parametros) }}">{{ $class->name }}</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Atribuição no Censo</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Censo Escolar</span>
            <h1>Atribuição no Censo</h1>
            <p>{{ $assignment->staffMember?->full_name }} - {{ $class->name }}. Registro 50.</p>
        </div>
        <a class="botao secundario" href="{{ route('school.classes.show', $parametros) }}">Voltar à turma</a>
    </div>
    @if (session('status'))
        <div class="status-sucesso" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="erro" role="alert"><div><strong>Revise o formulário</strong><ul>@foreach ($errors->all() as $mensagem)<li>{{ $mensagem }}</li>@endforeach</ul></div></div>
    @endif
    <form method="POST" action="{{ route('school.classes.teachers.census.update', [...$parametros, 'atribuicao' => $assignment->id]) }}">
        @csrf
        @method('PUT')
        <section class="folha" aria-labelledby="titulo-vinculo">
            <div class="folha-cabecalho"><div><h2 id="titulo-vinculo">Vínculo profissional</h2><p>Confirme a função exercida nesta turma, sem inferir pelo cargo do servidor.</p></div></div>
            <div class="folha-corpo">
                <div class="formulario-grade">
                    @foreach (['class_function' => ['Função na turma', $functions], 'employment_status' => ['Situação funcional (funções 1, 5 e 6 em escola pública)', $employment]] as $campo => [$rotulo, $opcoes])
                        <div class="formulario-campo">
                            <label class="rotulo" for="{{ $campo }}">{{ $rotulo }}</label>
                            <select class="entrada" id="{{ $campo }}" name="{{ $campo }}">
                                <option value="">Não informado / não se aplica</option>
                                @foreach ($opcoes as $codigo => $nome)
                                    <option value="{{ $codigo }}" @selected((string) old($campo, $record?->{$campo}) === (string) $codigo)>{{ $nome }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
        <section class="folha censo-secao" aria-labelledby="titulo-componentes">
            <div class="folha-cabecalho"><div><h2 id="titulo-componentes">Componentes que leciona</h2><p>Somente funções 1 e 5; escolha entre os componentes oferecidos pela turma no registro 20.</p></div></div>
            <div class="folha-corpo">
                @if ($assignment->subject)
                    <p class="texto-ajuda">Disciplina cadastrada, apenas como sugestão: {{ $assignment->subject }}. Nenhum código é selecionado automaticamente.</p>
                @endif
                @if ($components === [])
                    <p>Não há componentes oferecidos neste registro 20. <a href="{{ route('school.census.classes.edit', $parametros) }}">Conferir dados da turma no Censo</a>.</p>
                @else
                    <fieldset class="censo-opcoes">
                        <legend>Selecione os componentes</legend>
                        @foreach ($components as $codigo => $nome)
                            <label><input type="checkbox" name="components[]" value="{{ $codigo }}" @checked(in_array((string) $codigo, $selecionados, true))> {{ $nome }}</label>
                        @endforeach
                    </fieldset>
                @endif
            </div>
        </section>
        <div class="formulario-acoes censo-secao">
            <p class="texto-ajuda">A gravação exige segundo fator e registra apenas os nomes dos campos alterados.</p>
            <button class="botao" type="submit">Salvar atribuição no Censo</button>
        </div>
    </form>
    <section class="folha censo-secao" aria-labelledby="titulo-conferencia">
        <div class="folha-cabecalho"><div><h2 id="titulo-conferencia">Conferência do registro 50</h2></div></div>
        <div class="folha-corpo">
            <p>{{ $registro->pronto() ? 'Sem pendências impeditivas locais' : 'Há pendências' }}</p>
            <ul class="censo-pendencias">
                @foreach ($registro->pendencias as $p)
                    <li>{{ $p->recusa() ? 'Pendência' : 'Aviso' }} (campo {{ $p->campo }}): {{ $p->mensagem }}</li>
                @endforeach
            </ul>
        </div>
    </section>
@endsection
