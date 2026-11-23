@extends('layouts.municipal')

@php
    $parametros = ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id];
    $tipoSanguineo = old('blood_type', $profile?->blood_type);
@endphp

@section('title', 'Ficha de saúde - '.$student->full_name)

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('schools.show', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('school.students.show', $parametros) }}">{{ $student->full_name }}</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Ficha de saúde</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Dado sensível, acesso restrito</span>
            <h1>Ficha de saúde</h1>
            <p>Aluno: {{ $student->full_name }}. Registre só o que a escola precisa saber e fazer no dia a dia ou numa emergência.</p>
        </div>
        <a class="botao secundario" href="{{ route('school.students.show', $parametros) }}#saude">Voltar à ficha do aluno</a>
    </div>

    @if (session('status'))
        <div class="status-sucesso" role="status"><x-icon name="info" :size="18" /> {{ session('status') }}</div>
    @endif
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

    <div class="nota saude-nota">
        <x-icon name="escudo" />
        <div>
            <strong>Não escreva diagnóstico, CID ou nome de laudo</strong>
            <p>Escreva o que fazer, não a doença. Ex.: "alergia grave a amendoim" e "não oferecer; se houver contato, chamar o SAMU (192) e a família". Alerta grave aparece para o professor na ficha e na chamada. Alerta de atenção e tipo sanguíneo só a gestão vê.</p>
        </div>
    </div>

    <section class="folha formulario-aluno saude-secao" aria-labelledby="titulo-alertas-saude">
        <div class="folha-cabecalho"><div><h2 id="titulo-alertas-saude">Alertas</h2><p>{{ $alerts->count() }} {{ $alerts->count() === 1 ? 'alerta cadastrado' : 'alertas cadastrados' }}.</p></div></div>

        @if ($alerts->isEmpty())
            <div class="vazio">Nenhum alerta de saúde informado pela família.</div>
        @else
            <ul class="saude-alertas">
                @foreach ($alerts as $alerta)
                    <li @class(['saude-alerta', 'saude-alerta-grave' => $alerta->isCritical()])>
                        <div>
                            <span class="etiqueta {{ $alerta->isCritical() ? 'conferir' : 'neutra' }}">{{ $alerta->isCritical() ? 'Grave' : 'Atenção' }}</span>
                            <span class="saude-alerta-tipo">{{ $alerta->kindLabel() }}</span>
                            <p class="saude-alerta-texto">{{ $alerta->alert_text }}</p>
                            @if ($alerta->care_instructions)
                                <p class="saude-alerta-acao"><strong>O que fazer:</strong> {{ $alerta->care_instructions }}</p>
                            @endif
                        </div>
                        <details class="saude-remover">
                            <summary>Remover</summary>
                            <form method="POST" action="{{ route('school.students.health.alerts.destroy', [...$parametros, 'alerta' => $alerta->id]) }}">
                                @csrf
                                <label class="rotulo" for="motivo-{{ $alerta->id }}">Por que remover?</label>
                                <input class="entrada" id="motivo-{{ $alerta->id }}" name="motivo" minlength="10" maxlength="300" required placeholder="Ex.: família informou que não se aplica mais">
                                <button class="botao secundario" type="submit">Remover alerta</button>
                            </form>
                        </details>
                    </li>
                @endforeach
            </ul>
        @endif

        <h3 class="saude-subtitulo">Adicionar alerta</h3>
        <form method="POST" action="{{ route('school.students.health.alerts.store', $parametros) }}">
            @csrf
            <div class="formulario-grade">
                <div class="formulario-campo">
                    <label class="rotulo" for="kind">Tipo *</label>
                    <select class="entrada" id="kind" name="kind" required>
                        @foreach (\App\Models\Tenant\StudentHealthAlert::KINDS as $valor => $rotulo)
                            <option value="{{ $valor }}" @selected(old('kind', 'allergy') === $valor)>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="formulario-campo">
                    <label class="rotulo" for="severity">Gravidade *</label>
                    <select class="entrada" id="severity" name="severity" required>
                        @foreach (\App\Models\Tenant\StudentHealthAlert::SEVERITIES as $valor => $rotulo)
                            <option value="{{ $valor }}" @selected(old('severity', 'critical') === $valor)>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="formulario-campo largo">
                    <label class="rotulo" for="alert_text">Alerta *</label>
                    <input class="entrada" id="alert_text" name="alert_text" maxlength="160" required placeholder="Ex.: alergia grave a amendoim" value="{{ old('alert_text') }}">
                </div>
                <div class="formulario-campo largo">
                    <label class="rotulo" for="care_instructions">O que fazer (obrigatório para alerta grave)</label>
                    <textarea class="entrada" id="care_instructions" name="care_instructions" maxlength="400" rows="3" placeholder="Ex.: não oferecer alimentos com amendoim; em caso de contato, chamar o SAMU (192) e avisar a família.">{{ old('care_instructions') }}</textarea>
                </div>
            </div>
            <div class="formulario-acoes"><button class="botao" type="submit">Adicionar alerta</button></div>
        </form>
    </section>

    <section class="folha formulario-aluno saude-secao" aria-labelledby="titulo-dados-emergencia">
        <div class="folha-cabecalho"><div><h2 id="titulo-dados-emergencia">Dados para emergência</h2><p>Opcional. Só a gestão vê. Serve para informar o socorro, não para decisão da escola.</p></div></div>
        <form method="POST" action="{{ route('school.students.health.update', $parametros) }}">
            @csrf
            <div class="formulario-grade">
                <div class="formulario-campo">
                    <label class="rotulo" for="blood_type">Tipo sanguíneo</label>
                    <select class="entrada" id="blood_type" name="blood_type">
                        <option value="" @selected($tipoSanguineo === null || $tipoSanguineo === '')>Não informado</option>
                        <option value="unknown" @selected($tipoSanguineo === 'unknown')>Família não sabe</option>
                        @foreach (\App\Models\Tenant\StudentHealthProfile::BLOOD_TYPES as $tipo)
                            <option value="{{ $tipo }}" @selected($tipoSanguineo === $tipo)>{{ $tipo }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="formulario-campo">
                    <label class="rotulo" for="informed_by">Informado por</label>
                    <input class="entrada" id="informed_by" name="informed_by" maxlength="120" placeholder="Ex.: mãe, na matrícula" value="{{ old('informed_by', $profile?->informed_by) }}">
                </div>
                <div class="formulario-campo">
                    <label class="rotulo" for="informed_on">Data da informação</label>
                    <input class="entrada" id="informed_on" name="informed_on" type="date" value="{{ old('informed_on', $profile?->informed_on?->format('Y-m-d')) }}">
                </div>
            </div>
            <div class="formulario-acoes"><button class="botao secundario" type="submit">Salvar dados para emergência</button></div>
        </form>
    </section>
@endsection
