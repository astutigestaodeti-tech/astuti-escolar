@extends('layouts.municipal')

@php
    $editando = $student !== null;
    $titulo = $editando ? 'Editar dados do aluno' : 'Novo aluno';
@endphp

@section('title', $titulo.' - '.$school->name)

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('schools.show', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('school.students.index', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">Alunos</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">{{ $titulo }}</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Secretaria escolar</span>
            <h1>{{ $titulo }}</h1>
            <p>{{ $editando ? 'Corrija os dados de '.$student->full_name.'.' : 'Cadastro e matrícula na unidade '.$school->name.'.' }} Toda alteração fica registrada no histórico.</p>
        </div>
    </div>

    <section class="folha formulario-aluno" aria-labelledby="titulo-formulario">
        <div class="folha-cabecalho"><div><h2 id="titulo-formulario">Dados do aluno</h2><p>Os campos marcados com * são obrigatórios.</p></div></div>

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
                  ? route('school.students.update', ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id])
                  : route('school.students.store', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">
            @csrf
            @if ($editando)
                @method('PUT')
                <input type="hidden" name="enrollment_id" value="{{ $enrollment->id }}">
                <input type="hidden" name="versao" value="{{ old('versao', $studentVersion) }}">
                <input type="hidden" name="versao_matricula" value="{{ old('versao_matricula', $enrollmentVersion) }}">
            @endif

            @if ($editando)
                <p class="texto-ajuda">Você está editando a matrícula do ano letivo {{ $enrollment->schoolYear?->year ?? 'não informado' }}.
                    <a href="{{ route('school.students.show', ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id]) }}">Veja os outros vínculos na ficha.</a>
                </p>
            @endif

            <div class="formulario-grade">
                <div class="formulario-campo largo">
                    <label class="rotulo" for="full_name">Nome completo *</label>
                    <input class="entrada" id="full_name" name="full_name" type="text" maxlength="180" autocomplete="off" required
                           value="{{ old('full_name', $student?->full_name) }}"
                           @error('full_name') aria-invalid="true" aria-describedby="full_name-erro" @enderror>
                    @error('full_name')<p class="campo-erro" id="full_name-erro">{{ $message }}</p>@enderror
                </div>

                <div class="formulario-campo">
                    <label class="rotulo" for="ra">RA do aluno</label>
                    <input class="entrada" id="ra" name="ra" type="text" maxlength="32" autocomplete="off"
                           placeholder="000123456789-0/SP" value="{{ old('ra', $student?->ra) }}"
                           aria-describedby="{{ $errors->has('ra') ? 'ra-ajuda ra-erro' : 'ra-ajuda' }}"
                           @error('ra') aria-invalid="true" @enderror>
                    <p class="texto-ajuda" id="ra-ajuda">Formato da SED: números, hífen, dígito e UF. Ex.: 000123456789-0/SP. Pode ficar em branco.</p>
                    @error('ra')<p class="campo-erro" id="ra-erro">{{ $message }}</p>@enderror
                </div>

                <div class="formulario-campo">
                    <label class="rotulo" for="birth_date">Data de nascimento</label>
                    <input class="entrada" id="birth_date" name="birth_date" type="date" min="1900-01-01" max="{{ now()->subDay()->format('Y-m-d') }}"
                           value="{{ old('birth_date', $student?->birth_date?->format('Y-m-d')) }}"
                           @error('birth_date') aria-invalid="true" aria-describedby="birth_date-erro" @enderror>
                    @error('birth_date')<p class="campo-erro" id="birth_date-erro">{{ $message }}</p>@enderror
                </div>

                @unless ($editando)
                    <div class="formulario-campo">
                        <label class="rotulo" for="school_year_id">Ano letivo da matrícula *</label>
                        <select class="entrada" id="school_year_id" name="school_year_id" required
                                @error('school_year_id') aria-invalid="true" aria-describedby="school_year_id-erro" @enderror>
                            <option value="">Escolha o ano letivo</option>
                            @foreach ($years as $year)
                                <option value="{{ $year->id }}" @selected(old('school_year_id') === $year->id)>{{ $year->year }}</option>
                            @endforeach
                        </select>
                        @error('school_year_id')<p class="campo-erro" id="school_year_id-erro">{{ $message }}</p>@enderror
                    </div>

                    <div class="formulario-campo">
                        <label class="rotulo" for="started_at">Data de início na escola</label>
                        <input class="entrada" id="started_at" name="started_at" type="date" value="{{ old('started_at') }}"
                               @error('started_at') aria-invalid="true" aria-describedby="started_at-erro" @enderror>
                        @error('started_at')<p class="campo-erro" id="started_at-erro">{{ $message }}</p>@enderror
                    </div>
                @endunless

                <div class="formulario-campo largo">
                    <label class="rotulo" for="school_class_id">Turma da matrícula</label>
                    <select class="entrada" id="school_class_id" name="school_class_id"
                            @error('school_class_id') aria-invalid="true" aria-describedby="school_class_id-erro" @enderror>
                        <option value="">Sem turma definida</option>
                        @foreach ($classes as $class)
                            <option value="{{ $class->id }}" @selected(old('school_class_id', $enrollment?->school_class_id) === $class->id)>{{ $class->name }} ({{ $class->schoolYear?->year ?? 'Ano não informado' }})</option>
                        @endforeach
                    </select>
                    @error('school_class_id')<p class="campo-erro" id="school_class_id-erro">{{ $message }}</p>@enderror
                    <p class="texto-ajuda">A turma deve pertencer a esta escola e ao mesmo ano letivo da matrícula. Se ainda não foi definida, deixe em branco.</p>
                </div>
            </div>

            @if (session('possivel_duplicidade'))
                <label class="confirmacao">
                    <input type="checkbox" name="confirmar_duplicidade" value="1">
                    <span>Conferi: é outra pessoa, com o mesmo nome. Quero cadastrar mesmo assim.</span>
                </label>
            @endif

            <div class="formulario-acoes">
                <button class="botao" type="submit">{{ $editando ? 'Salvar alterações' : 'Cadastrar e matricular' }}</button>
                <a class="botao secundario" href="{{ $editando
                    ? route('school.students.show', ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id])
                    : route('school.students.index', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">Cancelar</a>
            </div>
        </form>
    </section>

    <div class="nota"><x-icon name="escudo" /><div><strong>Use apenas dados fictícios neste ambiente</strong><p>Documentos, laudos e dados de saúde não são cadastrados aqui.</p></div></div>
@endsection
