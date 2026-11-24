@extends('layouts.municipal')

@php
    use App\Models\Tenant\StudentSpecialEducation as Especial;
    $parametros = ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id];
    $condicoes = old('conditions', $record?->conditions ?? []);
    $recursos = old('exam_resources', $record?->exam_resources ?? []);
    $transtornos = old('learning_disorders', $record?->learning_disorders ?? []);
    $atividades = old('aee_activities', $record?->aee_activities ?? []);
    $situacaoAee = old('aee_status', $record?->aee_status);
    $apoio = old('support_professional', $record?->support_professional === null ? '' : ($record->support_professional ? 'sim' : 'nao'));
@endphp

@section('title', 'Educação especial - '.$student->full_name)
@section('body-class', 'pagina-educacao-especial')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/educacao-especial.css') }}?v={{ filemtime(public_path('css/educacao-especial.css')) }}">
@endpush

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('schools.show', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('school.students.show', $parametros) }}">{{ $student->full_name }}</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Educação especial</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Dado sensível, acesso restrito</span>
            <h1>Educação especial e AEE</h1>
            <p>Aluno: {{ $student->full_name }}. As opções seguem o Censo Escolar, para a matrícula de AEE contar no Fundeb e na merenda.</p>
        </div>
        <a class="botao secundario" href="{{ route('school.students.show', $parametros) }}#educacao-especial">Voltar à ficha do aluno</a>
    </div>

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

    <div class="nota especial-nota">
        <x-icon name="escudo" />
        <div>
            <strong>Sem CID, diagnóstico ou cópia de laudo</strong>
            <p>Marque só a categoria do Censo. O laudo médico não é exigido para registrar o aluno no Censo nem para o atendimento educacional especializado; o que vale é a avaliação pedagógica da escola.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('school.students.special-education.update', $parametros) }}">
        @csrf
        <section class="folha especial-secao" aria-labelledby="titulo-condicoes">
            <div class="folha-cabecalho"><div><h2 id="titulo-condicoes">Categorias do Censo</h2><p>Marque todas as que se aplicam. Mais de uma deficiência indica deficiência múltipla.</p></div></div>
            <div class="folha-corpo">
                <fieldset class="especial-opcoes">
                    <legend class="sr-only">Categorias do Censo</legend>
                    @foreach (Especial::CONDITIONS as $valor => $rotulo)
                        <label><input type="checkbox" name="conditions[]" value="{{ $valor }}" @checked(in_array($valor, $condicoes, true))> {{ $rotulo }}</label>
                    @endforeach
                </fieldset>
            </div>
        </section>

        <section class="folha especial-secao" aria-labelledby="titulo-transtornos">
            <div class="folha-cabecalho"><div><h2 id="titulo-transtornos">Transtornos que impactam a aprendizagem</h2><p>Novidade do Censo: marque só o que a escola identificou na avaliação pedagógica.</p></div></div>
            <div class="folha-corpo">
                <fieldset class="especial-opcoes">
                    <legend class="sr-only">Transtornos que impactam a aprendizagem</legend>
                    @foreach (Especial::LEARNING_DISORDERS as $valor => $rotulo)
                        <label><input type="checkbox" name="learning_disorders[]" value="{{ $valor }}" @checked(in_array($valor, $transtornos, true))> {{ $rotulo }}</label>
                    @endforeach
                </fieldset>
            </div>
        </section>

        <section class="folha especial-secao" aria-labelledby="titulo-aee">
            <div class="folha-cabecalho"><div><h2 id="titulo-aee">Atendimento educacional especializado (AEE)</h2><p>A matrícula de AEE no contraturno conta a mais no Fundeb e na merenda.</p></div></div>
            <div class="folha-corpo">
                <div class="formulario-grade">
                    <div class="formulario-campo">
                        <label class="rotulo" for="aee_status">Situação</label>
                        <select class="entrada" id="aee_status" name="aee_status">
                            <option value="">Não informado</option>
                            @foreach (Especial::AEE_STATUSES as $valor => $rotulo)
                                <option value="{{ $valor }}" @selected($situacaoAee === $valor)>{{ $rotulo }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="formulario-campo">
                        <label class="rotulo" for="aee_school_id">Escola do AEE (se frequenta)</label>
                        <select class="entrada" id="aee_school_id" name="aee_school_id">
                            <option value="">Escolha a escola</option>
                            @foreach ($schools as $unidade)
                                <option value="{{ $unidade->id }}" @selected(old('aee_school_id', $record?->aee_school_id ?? ($situacaoAee === null ? $school->id : null)) === $unidade->id)>{{ $unidade->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="formulario-campo">
                        <label class="rotulo" for="aee_started_on">Início no AEE</label>
                        <input class="entrada" type="date" id="aee_started_on" name="aee_started_on" value="{{ old('aee_started_on', $record?->aee_started_on?->format('Y-m-d')) }}">
                    </div>
                    <div class="formulario-campo">
                        <label class="rotulo" for="support_professional">Profissional de apoio escolar</label>
                        <select class="entrada" id="support_professional" name="support_professional">
                            <option value="">Não informado</option>
                            <option value="sim" @selected($apoio === 'sim')>Tem profissional de apoio</option>
                            <option value="nao" @selected($apoio === 'nao')>Não tem</option>
                        </select>
                    </div>
                </div>
                <fieldset class="especial-opcoes">
                    <legend class="rotulo">Atividades no AEE (se frequenta)</legend>
                    @foreach (Especial::AEE_ACTIVITIES as $valor => $rotulo)
                        <label><input type="checkbox" name="aee_activities[]" value="{{ $valor }}" @checked(in_array($valor, $atividades, true))> {{ $rotulo }}</label>
                    @endforeach
                </fieldset>
            </div>
        </section>

        <section class="folha especial-secao" aria-labelledby="titulo-recursos">
            <div class="folha-cabecalho"><div><h2 id="titulo-recursos">Recursos para provas e avaliações</h2><p>Usados no Saeb e no SARESP. O Censo só aceita os recursos compatíveis com as categorias e transtornos marcados.</p></div></div>
            <div class="folha-corpo">
                <fieldset class="especial-opcoes">
                    <legend class="sr-only">Recursos para provas</legend>
                    @foreach (Especial::EXAM_RESOURCES as $valor => $rotulo)
                        <label><input type="checkbox" name="exam_resources[]" value="{{ $valor }}" @checked(in_array($valor, $recursos, true))> {{ $rotulo }}</label>
                    @endforeach
                </fieldset>
                <p class="texto-ajuda">Gravar pede o código do segundo fator. A auditoria registra quais campos mudaram, nunca o conteúdo.</p>
                <div class="formulario-acoes"><button class="botao" type="submit">Salvar educação especial</button></div>
            </div>
        </section>
    </form>
@endsection
