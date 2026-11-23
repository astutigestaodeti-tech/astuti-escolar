@extends('layouts.municipal')

@php($params = ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id])

@section('title', 'Emitir declaração de matrícula')

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('school.students.show', $params) }}">{{ $student->full_name }}</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Declaração de matrícula</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div><span class="contexto">Documento escolar</span><h1>Emitir declaração de matrícula</h1>
            <p>Aluno: {{ $student->full_name }}. Os dados serão preservados como estavam na emissão.</p></div>
        <a class="botao secundario" href="{{ route('school.students.show', $params) }}">Voltar à ficha</a>
    </div>

    @if ($errors->any())
        <div class="erro" role="alert"><strong>Confira os dados.</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="folha" aria-labelledby="titulo-emissao">
        <div class="folha-cabecalho"><div><h2 id="titulo-emissao">Dados da declaração</h2>
            <p>Somente uma matrícula ativa desta escola pode ser declarada.</p></div></div>
        <div class="folha-corpo">
            @if ($enrollments->isEmpty())
                <p>O aluno não possui matrícula ativa nesta escola. Não é possível emitir nova declaração.</p>
            @else
                <form method="POST" action="{{ route('school.students.declarations.store', $params) }}">
                    @csrf
                    <div class="formulario-campo">
                        <label class="rotulo" for="matricula">Matrícula ativa *</label>
                        <select class="entrada" id="matricula" name="matricula" required>
                            @foreach ($enrollments as $enrollment)
                                <option value="{{ $enrollment->id }}" @selected(old('matricula') === $enrollment->id)>
                                    {{ $enrollment->schoolYear?->year }} - {{ $enrollment->schoolClass?->stage ?? 'Etapa não informada' }} - {{ $enrollment->schoolClass?->name ?? 'Turma não informada' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="formulario-campo declaracao-finalidade">
                        <label class="rotulo" for="purpose">Finalidade (opcional, até 120 caracteres)</label>
                        <input class="entrada" id="purpose" name="purpose" type="text" maxlength="120" value="{{ old('purpose') }}" placeholder="Ex.: para fins de comprovação escolar">
                    </div>
                    <p class="texto-suave">A emissão exige segundo fator confirmado, recebe um código único e fica registrada na auditoria.</p>
                    <div class="formulario-acoes">
                        <button class="botao primario" type="submit">Emitir declaração</button>
                        <a class="botao secundario" href="{{ route('school.students.show', $params) }}">Cancelar</a>
                    </div>
                </form>
            @endif
        </div>
    </section>
@endsection
