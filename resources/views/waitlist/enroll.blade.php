@extends('layouts.municipal')

@section('title', 'Matricular pela lista de espera')
@section('body-class', 'pagina-lista-espera')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/lista-espera.css') }}?v={{ filemtime(public_path('css/lista-espera.css')) }}">
@endpush

@section('trilha')
    <a href="{{ route('waitlist.index', ['municipio' => $municipality->slug, 'ano' => $record->intake_year]) }}">Lista de espera</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('waitlist.show', ['municipio' => $municipality->slug, 'pedido' => $record->id]) }}">{{ $record->protocol }}</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Matricular</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Lista de espera - protocolo {{ $record->protocol }}</span>
            <h1>Matricular {{ $record->child_name }}</h1>
            <p>{{ $stages[$record->stage] }}, ingresso {{ $record->intake_year }}, turno desejado {{ mb_strtolower($shifts[$record->desired_shift] ?? $record->desired_shift) }}. Os dados da criança, do endereço e do responsável vêm do pedido.</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="status-erro" role="alert"><x-icon name="info" :size="18" /> {{ $errors->first() }}</div>
    @endif

    @if ($duplicate)
        <section class="folha" aria-labelledby="titulo-ja-aluno">
            <div class="folha-cabecalho"><div><h2 id="titulo-ja-aluno">Esta criança já está no cadastro</h2><p>Existe aluno com o mesmo nome e data de nascimento, ou com o mesmo CPF. Para não criar um cadastro repetido, matricule pela ficha desse aluno e depois registre no pedido a situação "Atendido".</p></div></div>
        </section>
    @elseif ($options === [])
        <section class="folha" aria-labelledby="titulo-sem-turma">
            <div class="folha-cabecalho"><div><h2 id="titulo-sem-turma">Nenhuma turma disponível</h2><p>Não há turmas do ano letivo {{ $record->intake_year }} nas escolas de preferência que você pode gerir. Cadastre a turma na escola ou peça à gestão da escola indicada que faça a matrícula.</p></div></div>
        </section>
    @else
        <section class="folha" aria-labelledby="titulo-matricula">
            <div class="folha-cabecalho"><div><h2 id="titulo-matricula">Turma e início</h2><p>A turma da mesma etapa aparece primeiro, marcada como sugerida.</p></div></div>
            <div class="folha-corpo">
                <form method="POST" action="{{ route('waitlist.enroll', ['municipio' => $municipality->slug, 'pedido' => $record->id]) }}" class="lista-espera-mudanca">
                    @csrf
                    <label for="school_class_id">Turma
                        <select id="school_class_id" name="school_class_id" required>
                            <option value="">Selecione</option>
                            @foreach ($options as $option)
                                <optgroup label="{{ $option['school']->name }}">
                                    @foreach ($option['classes'] as $class)
                                        <option value="{{ $class['id'] }}" @selected(old('school_class_id') === $class['id'])>{{ $class['label'] }}{{ $class['suggested'] ? ' (sugerida)' : '' }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </label>
                    <label for="started_at">Início da matrícula
                        <input id="started_at" name="started_at" type="date" class="entrada" value="{{ old('started_at', $today) }}" required>
                    </label>
                    <label for="relationship">{{ $record->guardian_name }} é
                        <select id="relationship" name="relationship" required>
                            <option value="mother" @selected(old('relationship') === 'mother')>Mãe</option>
                            <option value="father" @selected(old('relationship') === 'father')>Pai</option>
                            <option value="other" @selected(old('relationship', 'other') === 'other')>Outro responsável</option>
                        </select>
                    </label>
                    <p class="texto-ajuda">Ao confirmar, o pedido fica "Atendido" e sai da fila de todas as escolas. A matrícula pede o código do aplicativo se ele foi digitado há mais de 15 minutos.</p>
                    <div class="formulario-acoes">
                        <button class="botao" type="submit">Matricular e encerrar o pedido</button>
                        <a class="botao secundario" href="{{ route('waitlist.show', ['municipio' => $municipality->slug, 'pedido' => $record->id]) }}">Cancelar</a>
                    </div>
                </form>
            </div>
        </section>
    @endif
@endsection
