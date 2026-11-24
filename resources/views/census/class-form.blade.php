@extends('layouts.municipal')

@php
    $parametros = ['municipio' => $municipality->slug, 'escola' => $school->id];
    $base = $census ?? new \App\Models\Tenant\SchoolClassCensus($suggested ?? []);
    $tipo = (string) old('class_type', $base->class_type);
    $etapaAtual = (string) old('inep_stage_code', $base->inep_stage_code);
    $mediacao = (string) old('mediation', $base->mediation ?? ($census ? '' : '1'));
    $simNao = fn ($valor) => $valor === null ? '' : ($valor ? 'sim' : 'nao');
    $areasMarcadas = array_map('strval', old('curricular_areas', $base->curricular_areas ?? []));
    $atividadesMarcadas = array_map('strval', old('complementary_activities', $base->complementary_activities ?? []));
    $leiaute = \App\Educacenso\Leiaute::do($tabelas->ano);
    $nomesAreas = [];
    foreach ($tabelas->camposDasAreas() as $codigo => $campo) {
        $nomesAreas[$codigo] = preg_replace('/^\d+\.\s*/', '', $leiaute->campo('20', $campo)->nome);
    }
    $etapasPorArea = [];
    foreach ($etapas as $codigoEtapa => $dados) {
        foreach ($tabelas->areasPermitidas($codigoEtapa) as $area) {
            $etapasPorArea[$area][] = $codigoEtapa;
        }
    }
    $organizacoes = ['1' => 'Série/ano (séries anuais)', '2' => 'Períodos semestrais', '3' => 'Ciclo(s)', '4' => 'Grupos não seriados com base na idade ou competência', '5' => 'Módulos'];
    $porAgregada = collect($etapas)->groupBy('nome_agregada');
    $curricular = in_array($tipo, ['6', '9', ''], true);
    $comAtividade = in_array($tipo, ['4', '9'], true);
    $atividades = collect($tabelas->atividadesComplementares())->groupBy('area');
@endphp

@section('title', 'Censo da turma '.$class->name)
@section('body-class', 'pagina-censo-turmas')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/censo-turmas.css') }}?v={{ filemtime(public_path('css/censo-turmas.css')) }}">
@endpush

@section('trilha')
    <a href="{{ route('schools.show', $parametros) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('school.census.classes.index', $parametros) }}">Turmas no Censo</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">{{ $class->name }}</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Censo Escolar</span>
            <h1>Censo da turma {{ $class->name }}</h1>
            <p>{{ $class->stage }} · {{ $class->shift }}. Opções e combinações conforme o leiaute oficial {{ $tabelas->ano }} do Inep.</p>
        </div>
        <a class="botao secundario" href="{{ route('school.census.classes.index', $parametros) }}">Voltar às turmas</a>
    </div>

    @if ($errors->any())
        <div class="erro" role="alert">
            <x-icon name="alerta" :size="18" />
            <div><strong>Revise o formulário</strong><ul>@foreach ($errors->all() as $mensagem)<li>{{ $mensagem }}</li>@endforeach</ul></div>
        </div>
    @endif

    @if ($suggested !== null)
        <div class="censo-sugestao" role="note">
            <x-icon name="info" :size="18" />
            <p>Primeiro preenchimento: o Astuti sugeriu a etapa, o tipo e os componentes a partir do cadastro da turma, e marcou as respostas mais comuns (presencial, série/ano, sem classe especial, sem alternância e sem educação bilíngue). Confira tudo antes de salvar; nada foi gravado ainda.</p>
        </div>
    @endif

    <form method="POST" action="{{ route('school.census.classes.update', [...$parametros, 'turma' => $class->id]) }}" novalidate>
        @csrf
        @method('PUT')

        <section class="folha" aria-labelledby="titulo-turma">
            <div class="folha-cabecalho"><div><h2 id="titulo-turma">Tipo e etapa</h2><p>Os campos marcados com * são obrigatórios.</p></div></div>
            <div class="folha-corpo">
                <div class="formulario-grade">
                    <div class="formulario-campo">
                        <label class="rotulo" for="class_type">Tipo de turma *</label>
                        <select class="entrada" id="class_type" name="class_type" data-censo-tipo>
                            <option value="">Escolha</option>
                            <option value="6" @selected($tipo === '6')>Curricular (etapa de ensino)</option>
                            <option value="9" @selected($tipo === '9')>Curricular com atividade complementar</option>
                            <option value="5" @selected($tipo === '5')>Atendimento educacional especializado (AEE)</option>
                            <option value="4" @selected($tipo === '4')>Atividade complementar</option>
                        </select>
                    </div>
                    <div class="formulario-campo">
                        <label class="rotulo" for="mediation">Tipo de mediação *</label>
                        <select class="entrada" id="mediation" name="mediation">
                            <option value="">Escolha</option>
                            <option value="1" @selected($mediacao === '1')>Presencial</option>
                            <option value="2" @selected($mediacao === '2')>Semipresencial (só EJA)</option>
                        </select>
                    </div>
                    <div class="formulario-campo" data-so-curricular @if (! $curricular) hidden @endif>
                        <label class="rotulo" for="inep_stage_code">Etapa de ensino do Inep *</label>
                        <select class="entrada" id="inep_stage_code" name="inep_stage_code" data-censo-etapa>
                            <option value="">Escolha</option>
                            @foreach ($porAgregada as $agregada => $lista)
                                <optgroup label="{{ $agregada }}">
                                    @foreach ($lista as $dados)
                                        <option value="{{ $dados['codigo'] }}" @selected($etapaAtual === (string) $dados['codigo'])>{{ $dados['nome'] }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div class="formulario-campo" data-so-curricular @if (! $curricular) hidden @endif>
                        <label class="rotulo" for="special_class">Classe especial *</label>
                        <select class="entrada" id="special_class" name="special_class">
                            <option value="">Escolha</option>
                            <option value="nao" @selected(old('special_class', $simNao($base->special_class)) === 'nao')>Não</option>
                            <option value="sim" @selected(old('special_class', $simNao($base->special_class)) === 'sim')>Sim</option>
                        </select>
                    </div>
                    <div class="formulario-campo">
                        <label class="rotulo" for="organization">Forma de organização</label>
                        <select class="entrada" id="organization" name="organization">
                            <option value="">Não se aplica (educação infantil, AEE ou atividade complementar)</option>
                            @foreach ($organizacoes as $valor => $rotulo)
                                <option value="{{ $valor }}" @selected((string) old('organization', $base->organization) === (string) $valor)>{{ $rotulo }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="formulario-campo">
                        <label class="rotulo" for="differentiated_location">Local de funcionamento *</label>
                        <select class="entrada" id="differentiated_location" name="differentiated_location">
                            <option value="">Escolha</option>
                            <option value="0" @selected((string) old('differentiated_location', $base->differentiated_location ?? ($census ? null : 0)) === '0')>Não é local diferenciado</option>
                            <option value="1" @selected((string) old('differentiated_location', $base->differentiated_location) === '1')>Sala anexa</option>
                            <option value="2" @selected((string) old('differentiated_location', $base->differentiated_location) === '2')>Unidade de atendimento socioeducativo</option>
                            <option value="3" @selected((string) old('differentiated_location', $base->differentiated_location) === '3')>Unidade prisional</option>
                        </select>
                    </div>
                    <div class="formulario-campo">
                        <label class="rotulo" for="alternance">Formação por alternância (tempo-escola e tempo-comunidade) *</label>
                        <select class="entrada" id="alternance" name="alternance">
                            <option value="">Escolha</option>
                            <option value="nao" @selected(old('alternance', $simNao($base->alternance)) === 'nao')>Não</option>
                            <option value="sim" @selected(old('alternance', $simNao($base->alternance)) === 'sim')>Sim</option>
                        </select>
                    </div>
                    <div class="formulario-campo">
                        <label class="rotulo" for="bilingual_deaf">Educação bilíngue de surdos *</label>
                        <select class="entrada" id="bilingual_deaf" name="bilingual_deaf">
                            <option value="">Escolha</option>
                            <option value="nao" @selected(old('bilingual_deaf', $simNao($base->bilingual_deaf)) === 'nao')>Não</option>
                            <option value="sim" @selected(old('bilingual_deaf', $simNao($base->bilingual_deaf)) === 'sim')>Sim</option>
                        </select>
                    </div>
                </div>
            </div>
        </section>

        <section class="folha censo-secao" aria-labelledby="titulo-horario">
            <div class="folha-cabecalho"><div><h2 id="titulo-horario">Dias e horários</h2><p>Para turma presencial, pelo menos um dia. O Censo aceita minutos múltiplos de 5.</p></div></div>
            <div class="folha-corpo">
                <div class="rolagem">
                    <table class="censo-horarios">
                        <thead><tr><th scope="col">Dia</th><th scope="col">Início</th><th scope="col">Fim</th></tr></thead>
                        <tbody>
                            @foreach (\App\Models\Tenant\SchoolClassCensus::DAYS as $dia => $nomeDia)
                                @php
                                    $atual = $base->{'schedule_'.$dia};
                                    [$inicio, $fim] = $atual ? explode('-', $atual) : [null, null];
                                @endphp
                                <tr>
                                    <th scope="row">{{ $nomeDia }}</th>
                                    <td><input class="entrada" type="time" step="300" name="horario[{{ $dia }}][inicio]" aria-label="{{ $nomeDia }}: início" value="{{ old("horario.$dia.inicio", $inicio) }}" data-dia="{{ $dia }}" data-parte="inicio"></td>
                                    <td><input class="entrada" type="time" step="300" name="horario[{{ $dia }}][fim]" aria-label="{{ $nomeDia }}: fim" value="{{ old("horario.$dia.fim", $fim) }}" data-dia="{{ $dia }}" data-parte="fim"></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="formulario-acoes"><button class="botao secundario" type="button" data-copiar-segunda>Copiar o horário de segunda para terça a sexta</button></div>
            </div>
        </section>

        <section class="folha censo-secao" aria-labelledby="titulo-areas" data-so-curricular @if (! $curricular) hidden @endif>
            <div class="folha-cabecalho"><div><h2 id="titulo-areas">Componentes oferecidos</h2><p>Áreas do conhecimento ou componentes curriculares da turma. A educação infantil não informa. As opções que o Inep não aceita para a etapa ficam desativadas.</p></div></div>
            <div class="folha-corpo">
                <fieldset class="censo-opcoes">
                    <legend class="sr-only">Componentes oferecidos</legend>
                    @foreach ($nomesAreas as $codigo => $nome)
                        <label><input type="checkbox" name="curricular_areas[]" value="{{ $codigo }}" data-etapas="{{ implode(',', $etapasPorArea[$codigo] ?? []) }}" @checked(in_array((string) $codigo, $areasMarcadas, true))> {{ $nome }}</label>
                    @endforeach
                </fieldset>
            </div>
        </section>

        <section class="folha censo-secao" aria-labelledby="titulo-atividades" data-so-atividade @if (! $comAtividade) hidden @endif>
            <div class="folha-cabecalho"><div><h2 id="titulo-atividades">Atividades complementares</h2><p>Até 6, da tabela oficial do Inep. Só para turma de atividade complementar.</p></div></div>
            <div class="folha-corpo">
                <div class="censo-atividades">
                    @foreach ($atividades as $area => $lista)
                        <h3>{{ $area }}</h3>
                        <fieldset class="censo-opcoes">
                            <legend class="sr-only">{{ $area }}</legend>
                            @foreach ($lista as $atividade)
                                <label><input type="checkbox" name="complementary_activities[]" value="{{ $atividade['codigo'] }}" @checked(in_array((string) $atividade['codigo'], $atividadesMarcadas, true))> {{ $atividade['nome'] }}</label>
                            @endforeach
                        </fieldset>
                    @endforeach
                </div>
            </div>
        </section>

        <div class="formulario-acoes censo-secao">
            <p class="texto-ajuda">Gravar pede o código do segundo fator. Toda alteração fica na auditoria.</p>
            <button class="botao" type="submit">Salvar dados do Censo</button>
        </div>
    </form>

    <script type="module" src="{{ asset('js/censo-turma.js') }}?v={{ filemtime(public_path('js/censo-turma.js')) }}"></script>
@endsection
