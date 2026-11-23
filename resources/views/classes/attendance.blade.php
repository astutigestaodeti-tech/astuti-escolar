@extends('layouts.municipal')

@php
    $params = ['municipio' => $municipality->slug, 'escola' => $school->id, 'turma' => $class->id];
    $resumoPresencas = $present.' '.($present === 1 ? 'presente' : 'presentes');
    $resumoFaltas = $absent.' '.($absent === 1 ? 'falta' : 'faltas');
@endphp

@section('title', 'Chamada da turma '.$class->name)

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('schools.show', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('school.classes.show', $params) }}">Turma {{ $class->name }}</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Chamada</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Frequência escolar</span>
            <h1>{{ $historical ? 'Conferir chamada anterior' : ($session ? 'Corrigir chamada de hoje' : 'Fazer chamada de hoje') }}</h1>
            <p>Turma {{ $class->name }} - {{ $date->format('d/m/Y') }}. Os alunos começam como presentes: marque apenas as faltas.</p>
        </div>
        <div class="acoes-titulo">
            <a class="botao secundario" href="{{ route('school.classes.attendance.monthly', $params) }}">Relatório do mês</a>
            <a class="botao secundario" href="{{ route('school.classes.show', $params) }}">Voltar à turma</a>
        </div>
    </div>

    <section class="folha chamada-painel" aria-labelledby="chamada-titulo">
        <div class="folha-cabecalho">
            <div>
                <h2 id="chamada-titulo">{{ $students->count() }} {{ $students->count() === 1 ? 'aluno ativo' : 'alunos ativos' }}</h2>
                <p>Foto de identificação para conferência visual. Nenhuma foto pode ser alterada nesta tela.</p>
            </div>
            @if ($session)
                <span class="marcador chamada-resumo">{{ $resumoPresencas }}, {{ $resumoFaltas }}@if ($justified > 0) ({{ $justified }} {{ $justified === 1 ? 'justificada' : 'justificadas' }})@endif</span>
            @endif
        </div>

        @if ($historical && $session === null)
            <div class="nota chamada-aviso" role="status">Não existe chamada registrada em {{ $date->format('d/m/Y') }}. Correções de dias anteriores só são permitidas sobre chamadas já feitas.</div>
        @elseif ($historical && ! $canManage)
            <div class="nota chamada-aviso" role="status">A consulta está disponível, mas somente a gestão da escola pode corrigir chamadas de dias anteriores.</div>
        @endif

        @if ($errors->any())
            <div class="nota chamada-erros" role="alert">
                <strong>Confira os dados da chamada.</strong>
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        @if ($students->isEmpty())
            <div class="vazio">Ainda não há alunos com matrícula ativa nesta turma.</div>
        @else
            @if ($canSubmit)
                <form method="POST" action="{{ $historical ? route('school.classes.attendance.correct', $params) : route('school.classes.attendance.store', $params) }}">
                    @csrf
                    <input type="hidden" name="data" value="{{ $date->toDateString() }}">
            @endif
                    <div class="chamada-lista">
                        @foreach ($students as $student)
                            @php
                                $currentStatus = old('presencas.'.$student->id.'.situacao', $marks->get($student->id)?->status ?? 'present');
                                $currentObservation = old('presencas.'.$student->id.'.observacao', $marks->get($student->id)?->observation ?? '');
                            @endphp
                            <article class="chamada-aluno">
                                <div class="chamada-identificacao">
                                    <x-foto-identificacao tipo="aluno" :id="$student->id" :escola="$school->id" :municipio="$municipality->slug" :nome="$student->full_name" :temFoto="$student->photo_path !== null" :podeAlterar="false" tamanho="pequena" />
                                    <strong>{{ $student->full_name }}</strong>
                                    @foreach ($healthAlerts->get($student->id, collect()) as $alertaSaude)
                                        <details class="chamada-saude-detalhe">
                                            <summary class="chamada-saude">Alerta de saúde</summary>
                                            <p><strong>{{ $alertaSaude->alert_text }}</strong>@if ($alertaSaude->care_instructions) O que fazer: {{ $alertaSaude->care_instructions }}@endif</p>
                                        </details>
                                    @endforeach
                                </div>
                                <fieldset class="chamada-opcoes" @disabled(! $canSubmit)>
                                    <legend class="sr-only">Presença de {{ $student->full_name }}</legend>
                                    <label class="chamada-opcao">
                                        <input type="radio" name="presencas[{{ $student->id }}][situacao]" value="present" {{ $currentStatus === 'present' ? 'checked' : '' }}>
                                        <span>Presente</span>
                                    </label>
                                    <label class="chamada-opcao">
                                        <input type="radio" name="presencas[{{ $student->id }}][situacao]" value="absent" {{ $currentStatus === 'absent' ? 'checked' : '' }}>
                                        <span>Falta</span>
                                    </label>
                                    <label class="chamada-opcao">
                                        <input type="radio" name="presencas[{{ $student->id }}][situacao]" value="justified" {{ $currentStatus === 'justified' ? 'checked' : '' }}>
                                        <span>Falta justificada</span>
                                    </label>
                                    <div class="chamada-observacao">
                                        <label for="chamada-obs-{{ $student->id }}">Observação opcional, até 160 caracteres. Não registre dados de saúde</label>
                                        <input id="chamada-obs-{{ $student->id }}" type="text" name="presencas[{{ $student->id }}][observacao]" maxlength="160" value="{{ $currentObservation }}" placeholder="Informação breve para acompanhamento interno">
                                    </div>
                                </fieldset>
                            </article>
                        @endforeach
                    </div>
            @if ($canSubmit)
                    @if ($historical)
                        <div class="chamada-motivo">
                            <label class="rotulo" for="motivo">Motivo da correção *</label>
                            <textarea id="motivo" name="motivo" rows="3" minlength="10" maxlength="500" required>{{ old('motivo', $session?->correction_reason ?? '') }}</textarea>
                            <p class="texto-ajuda">Informe de 10 a 500 caracteres sem dados de saúde. Para salvar uma chamada anterior, será exigido segundo fator recente.</p>
                        </div>
                    @else
                        <p class="chamada-ajuda">Se todos estiverem presentes, basta salvar. Faltas justificadas também contam como ausência no percentual mensal.</p>
                    @endif
                    <button type="submit" class="botao primario">Salvar chamada</button>
                </form>
            @endif
        @endif
    </section>
@endsection
