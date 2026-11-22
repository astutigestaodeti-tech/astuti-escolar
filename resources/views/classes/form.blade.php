@extends('layouts.municipal')

@php
    $editando = $class !== null;
    $titulo = $editando ? 'Editar turma' : 'Nova turma';
    $parametrosEscola = ['municipio' => $municipality->slug, 'escola' => $school->id];
@endphp

@section('title', $titulo.' - '.$school->name)

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('schools.show', $parametrosEscola) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('school.classes.index', $parametrosEscola) }}">Turmas</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">{{ $titulo }}</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Organização acadêmica</span>
            <h1>{{ $titulo }}</h1>
            <p>{{ $editando ? 'Atualize os dados da turma desta unidade.' : 'Cadastre uma turma para o ano letivo da escola.' }} Toda alteração fica registrada na auditoria.</p>
        </div>
    </div>

    <section class="folha formulario-aluno formulario-turma" aria-labelledby="titulo-formulario">
        <div class="folha-cabecalho">
            <div><h2 id="titulo-formulario">Dados da turma</h2><p>Os campos marcados com * são obrigatórios.</p></div>
        </div>

        @if ($errors->any())
            <div class="erro" role="alert" id="resumo-erros">
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

        <form method="POST" novalidate
              action="{{ $editando
                  ? route('school.classes.update', [...$parametrosEscola, 'turma' => $class->id])
                  : route('school.classes.store', $parametrosEscola) }}">
            @csrf
            @if ($editando)
                @method('PUT')
                <input type="hidden" name="versao" value="{{ old('versao', $class->row_token) }}">
            @endif

            <div class="formulario-grade">
                <div class="formulario-campo largo">
                    <label class="rotulo" for="name">Nome ou código da turma *</label>
                    <input class="entrada" type="text" id="name" name="name" maxlength="120" required
                           value="{{ old('name', $class?->name) }}" placeholder="Ex.: 3º Ano A ou MULTI-1"
                           @error('name') aria-invalid="true" aria-describedby="name-erro" @enderror>
                    @error('name')<p class="campo-erro" id="name-erro">{{ $message }}</p>@enderror
                </div>
                <div class="formulario-campo">
                    <label class="rotulo" for="stage">Etapa de ensino *</label>
                    <input class="entrada" type="text" id="stage" name="stage" maxlength="120" required
                           value="{{ old('stage', $class?->stage) }}" placeholder="Ex.: Ensino Fundamental"
                           @error('stage') aria-invalid="true" aria-describedby="stage-erro" @enderror>
                    @error('stage')<p class="campo-erro" id="stage-erro">{{ $message }}</p>@enderror
                </div>
                <div class="formulario-campo">
                    <label class="rotulo" for="modality">Modalidade *</label>
                    <input class="entrada" type="text" id="modality" name="modality" maxlength="120" required
                           value="{{ old('modality', $class?->modality) }}" placeholder="Ex.: Regular"
                           @error('modality') aria-invalid="true" aria-describedby="modality-erro" @enderror>
                    @error('modality')<p class="campo-erro" id="modality-erro">{{ $message }}</p>@enderror
                </div>
                <div class="formulario-campo">
                    <label class="rotulo" for="shift">Turno *</label>
                    <input class="entrada" type="text" id="shift" name="shift" maxlength="60" required
                           value="{{ old('shift', $class?->shift) }}" placeholder="Ex.: Manhã, Tarde ou Integral"
                           @error('shift') aria-invalid="true" aria-describedby="shift-erro" @enderror>
                    @error('shift')<p class="campo-erro" id="shift-erro">{{ $message }}</p>@enderror
                </div>
                <div class="formulario-campo">
                    <label class="rotulo" for="capacity">Vagas (opcional)</label>
                    <input class="entrada" type="number" id="capacity" name="capacity" min="1" max="32767" step="1"
                           value="{{ old('capacity', $class?->capacity) }}" placeholder="Não informadas"
                           @error('capacity') aria-invalid="true" aria-describedby="capacity-erro" @enderror>
                    @error('capacity')<p class="campo-erro" id="capacity-erro">{{ $message }}</p>@enderror
                </div>
                <div class="formulario-campo">
                    <label class="rotulo" for="school_year_id">Ano letivo *</label>
                    <select class="entrada" id="school_year_id" name="school_year_id" required
                            @error('school_year_id') aria-invalid="true" aria-describedby="school_year_id-erro" @enderror>
                        <option value="">Selecione o ano letivo</option>
                        @foreach ($years as $year)
                            <option value="{{ $year->id }}" @selected(old('school_year_id', $class?->school_year_id) === $year->id)>
                                {{ $year->year }} - {{ match ($year->status) {'active' => 'Em andamento', 'planned' => 'Planejado', default => 'Encerrado'} }}
                            </option>
                        @endforeach
                    </select>
                    @error('school_year_id')<p class="campo-erro" id="school_year_id-erro">{{ $message }}</p>@enderror
                </div>
            </div>

            @if ($years->isEmpty())
                <p class="texto-ajuda">Nenhum ano letivo aberto foi encontrado. Solicite à gestão municipal a abertura do ano antes de cadastrar turmas.</p>
            @endif

            <div class="formulario-acoes">
                <button class="botao" type="submit">{{ $editando ? 'Salvar alterações' : 'Cadastrar turma' }}</button>
                <a class="botao secundario" href="{{ route('school.classes.index', $parametrosEscola) }}">Cancelar</a>
            </div>
        </form>
    </section>
@endsection
