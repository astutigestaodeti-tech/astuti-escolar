@extends('layouts.municipal')

@section('title', 'Notas da turma '.$class->name)
@section('body-class', 'pagina-notas')

@php
    $params = ['municipio' => $municipality->slug, 'escola' => $school->id, 'turma' => $class->id];
@endphp

@push('head')
    <link rel="stylesheet" href="{{ asset('css/notas-boletim.css') }}?v={{ filemtime(public_path('css/notas-boletim.css')) }}">
@endpush

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('schools.show', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('school.classes.show', $params) }}">{{ $class->name }}</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Notas</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Ensino Fundamental</span>
            <h1>Notas da turma {{ $class->name }}</h1>
            <p>{{ $school->name }}. Ano letivo {{ $class->schoolYear->year }}. Lançamento por componente e período.</p>
        </div>
        @if ($isMunicipalManager)
            <div class="acoes-titulo">
                <a class="botao secundario" href="{{ route('academic.periods.index', ['municipio' => $municipality->slug, 'ano' => $class->school_year_id]) }}">Configurar períodos</a>
                <a class="botao secundario" href="{{ route('academic.components.index', ['municipio' => $municipality->slug]) }}">Componentes da rede</a>
            </div>
        @endif
    </div>

    @if (session('status'))
        <div class="status-sucesso" role="status">{{ session('status') }}</div>
    @endif

    <section class="folha" aria-labelledby="titulo-lancamento-notas">
        <div class="folha-cabecalho">
            <div>
                <h2 id="titulo-lancamento-notas">Lançamento de notas</h2>
                <p>Notas entre 0,0 e 10,0. Use vírgula ou ponto. Tab passa para o próximo campo.</p>
            </div>
        </div>
        <div class="folha-corpo">
            <form class="notas-filtros" method="GET" action="{{ route('school.classes.grades.index', $params) }}">
                <label for="select-periodo">Período
                    <select id="select-periodo" name="periodo">
                        @foreach ($periods as $option)
                            <option value="{{ $option->id }}" @selected($period?->id === $option->id)>
                                {{ $option->number }}º {{ $option->kind }} {{ $option->closed_at ? '(fechado)' : '(aberto)' }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <label for="select-componente">Componente
                    <select id="select-componente" name="componente">
                        @foreach ($components as $option)
                            <option value="{{ $option->id }}" @selected($component?->id === $option->id)>{{ $option->name }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="botao secundario" type="submit">Consultar</button>
            </form>

            @if ($period)
                <p class="texto-ajuda">Período {{ $period->number }}: de {{ $period->starts_on->format('d/m/Y') }} a {{ $period->ends_on->format('d/m/Y') }}.
                    @if ($period->closed_at)
                        <strong>Fechado em {{ $period->closed_at->format('d/m/Y H:i') }}.</strong>
                    @else
                        Aberto para lançamentos autorizados.
                    @endif
                </p>
            @endif
            @if ($readOnlyReason)
                <div class="nota notas-aviso" role="note"><x-icon name="info" :size="18" /><p>{{ $readOnlyReason }}</p></div>
            @endif
            @if ($errors->any())
                <div class="erro" role="alert"><ul>@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
            @endif
        </div>

        @if (! $period || ! $component)
            <div class="folha-corpo"><p>Não há períodos ou componentes ativos. A gestão municipal deve configurar os cadastros para iniciar os lançamentos.</p></div>
        @elseif ($enrollments->isEmpty())
            <div class="folha-corpo"><p>Nenhuma matrícula ativa nesta turma.</p></div>
        @else
            <form method="POST" action="{{ route($period->closed_at ? 'school.classes.grades.correct' : 'school.classes.grades.store', $params) }}">
                @csrf
                <input type="hidden" name="periodo" value="{{ $period->id }}">
                <input type="hidden" name="componente" value="{{ $component->id }}">
                <div class="rolagem notas-rolagem">
                    <table class="tabela notas-tabela">
                        <thead><tr>
                            <th scope="col">Aluno</th>
                            <th scope="col">Nota (0 a 10)</th>
                            <th scope="col">Faltas no componente</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($enrollments as $enrollment)
                                @php
                                    $entry = $grades->get($enrollment->id);
                                    $gradeValue = old('grades.'.$enrollment->id.'.grade', $entry?->grade === null ? '' : number_format((float) $entry->grade, 1, ',', ''));
                                @endphp
                                <tr>
                                    <th scope="row">{{ $enrollment->student?->full_name }}</th>
                                    <td>
                                        <label class="sr-only" for="nota-{{ $enrollment->id }}">Nota de {{ $enrollment->student?->full_name }}</label>
                                        <input class="entrada" type="text" inputmode="decimal" autocomplete="off" id="nota-{{ $enrollment->id }}"
                                               name="grades[{{ $enrollment->id }}][grade]" value="{{ $gradeValue }}"
                                               placeholder="Ex.: 8,5" maxlength="4" @disabled(! $canWrite)>
                                    </td>
                                    <td>
                                        <label class="sr-only" for="faltas-{{ $enrollment->id }}">Faltas de {{ $enrollment->student?->full_name }}</label>
                                        <input class="entrada" type="number" min="0" max="32767" step="1"
                                               id="faltas-{{ $enrollment->id }}"
                                               name="grades[{{ $enrollment->id }}][absences]" value="{{ old('grades.'.$enrollment->id.'.absences', $entry?->absences) }}"
                                               placeholder="Opcional" @disabled(! $canWrite)>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($canWrite)
                    <div class="folha-corpo">
                        @if ($period->closed_at)
                            <label class="rotulo" for="notas-motivo">Motivo obrigatório da correção após fechamento (mínimo 10 caracteres)</label>
                            <textarea class="entrada" name="reason" id="notas-motivo" minlength="10" maxlength="500" rows="3" required>{{ old('reason') }}</textarea>
                            <p class="texto-ajuda">A correção fica auditada com o valor anterior, o novo e o motivo, e exige segundo fator recente.</p>
                        @endif
                        <div class="formulario-acoes">
                            <button class="botao" type="submit">{{ $period->closed_at ? 'Corrigir notas com justificativa' : 'Salvar alterações' }}</button>
                        </div>
                    </div>
                @endif
            </form>
        @endif
    </section>
@endsection
