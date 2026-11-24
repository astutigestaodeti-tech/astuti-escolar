@extends('layouts.municipal')

@php
    $params = ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id];
    $label = \App\Models\Tenant\IssuedDocument::DECLARATIONS[$kind];
    $back = $hasActiveEnrollment ? route('school.students.show', $params) : route('school.enrollments.index', ['municipio' => $municipality->slug, 'escola' => $school->id, 'situacao' => $kind === 'completion_declaration' ? 'concluidas' : 'transferidas']);
@endphp

@section('title', 'Emitir '.mb_strtolower($label))

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ $back }}">{{ $student->full_name }}</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">{{ $label }}</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div><span class="contexto">Documento escolar</span><h1>Emitir {{ mb_strtolower($label) }}</h1>
            <p>Aluno: {{ $student->full_name }}. Os dados serão preservados como estavam na emissão.</p></div>
        <a class="botao secundario" href="{{ $back }}">{{ $hasActiveEnrollment ? 'Voltar à ficha' : 'Voltar às matrículas' }}</a>
    </div>

    @if ($errors->any())
        <div class="erro" role="alert"><strong>Confira os dados.</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="folha" aria-labelledby="titulo-emissao">
        <div class="folha-cabecalho"><div><h2 id="titulo-emissao">Dados da declaração</h2>
            <p>
            @switch($kind)
                @case('attendance_declaration') Escolha uma matrícula e o período registrado na chamada. @break
                @case('transfer_declaration') Somente transferências já registradas nesta escola. @break
                @case('completion_declaration') Somente série concluída com aprovação confirmada em turma fechada. @break
                @default Somente uma matrícula ativa desta escola pode ser declarada.
            @endswitch
            </p></div></div>
        <div class="folha-corpo">
            @if ($enrollments->isEmpty())
                <p>Não há matrícula compatível com este documento nesta escola. Não é possível emitir nova declaração.</p>
            @else
                <form method="POST" action="{{ route('school.students.declarations.store', $params) }}">
                    @csrf
                    <input type="hidden" name="kind" value="{{ $kind }}">
                    <div class="formulario-campo">
                        <label class="rotulo" for="matricula">Matrícula {{ $kind === 'enrollment_declaration' ? 'ativa' : '' }} *</label>
                        <select class="entrada" id="matricula" name="matricula" required>
                            @foreach ($enrollments as $enrollment)
                                <option value="{{ $enrollment->id }}" @selected(old('matricula', $selected) === $enrollment->id)>
                                    {{ $enrollment->schoolYear?->year }} - {{ $enrollment->schoolClass?->stage ?? 'Etapa não informada' }} - {{ $enrollment->schoolClass?->name ?? 'Turma não informada' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @if ($kind === 'attendance_declaration')
                        <div class="formulario-campo">
                            <label class="rotulo" for="inicio">Início do período *</label>
                            <input class="entrada" id="inicio" name="inicio" type="date" value="{{ old('inicio') }}" max="{{ today()->toDateString() }}" required>
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="fim">Fim do período *</label>
                            <input class="entrada" id="fim" name="fim" type="date" value="{{ old('fim') }}" max="{{ today()->toDateString() }}" required>
                        </div>
                        <p class="texto-ajuda">O percentual usa os dias com marcação do aluno. Faltas justificadas também são ausências. Dias sem marcação não entram no cálculo. Sem chamada no período, a emissão não é permitida.</p>
                    @elseif ($kind === 'enrollment_declaration')
                        <div class="formulario-campo declaracao-finalidade">
                            <label class="rotulo" for="purpose">Finalidade (opcional, até 120 caracteres)</label>
                            <input class="entrada" id="purpose" name="purpose" type="text" maxlength="120" value="{{ old('purpose') }}" placeholder="Ex.: para fins de comprovação escolar">
                        </div>
                    @endif
                    <p class="texto-suave">A emissão exige segundo fator confirmado, recebe um código único e fica registrada na auditoria.</p>
                    <div class="formulario-acoes">
                        <button class="botao primario" type="submit">Emitir declaração</button>
                        <a class="botao secundario" href="{{ $back }}">Cancelar</a>
                    </div>
                </form>
            @endif
        </div>
    </section>
    @if ($documents->isNotEmpty())
        <section class="folha">
            <div class="folha-cabecalho"><h2>Vias já emitidas</h2></div>
            <div class="folha-corpo">
                <ul class="declaracoes-itens">
                    @foreach ($documents as $document)
                        <li><div><strong>{{ $label }} - {{ $document->snapshot_school_year }}</strong>
                            <small>{{ $document->issued_at->format('d/m/Y H:i') }} | {{ $document->revoked_at ? 'Revogada' : 'Válida' }}</small></div>
                            <a class="botao secundario" href="{{ route('school.students.declarations.show', [...$params, 'documento' => $document->id]) }}">Visualizar</a></li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
@endsection
