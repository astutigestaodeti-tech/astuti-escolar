@extends('layouts.municipal')

@php
    $parametros = ['municipio' => $municipality->slug, 'escola' => $school->id];
    $prontas = collect($rows)->filter(fn ($linha) => $linha['registro']->pronto())->count();
@endphp

@section('title', 'Turmas no Censo - '.$school->name)
@section('body-class', 'pagina-censo-turmas')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/censo-turmas.css') }}?v={{ filemtime(public_path('css/censo-turmas.css')) }}">
@endpush

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('schools.show', $parametros) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Turmas no Censo</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Censo Escolar</span>
            <h1>Turmas no Censo</h1>
            <p>Dados de cada turma no formato do Educacenso. Conferido com o leiaute oficial {{ $tabelas->ano }} do Inep.</p>
        </div>
        <div class="acoes-titulo">
            @if ($year)<span class="marcador">Ano letivo {{ $year->year }}</span>@endif
            <span class="marcador"><x-icon name="conferir" :size="16" /> {{ $prontas }} de {{ count($rows) }} prontas</span>
        </div>
    </div>

    @if (session('status'))
        <div class="status-sucesso" role="status"><x-icon name="info" :size="18" /> {{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="erro" role="alert">
            <x-icon name="alerta" :size="18" />
            <div><strong>Revise os valores</strong><ul>@foreach ($errors->all() as $mensagem)<li>{{ $mensagem }}</li>@endforeach</ul></div>
        </div>
    @endif

    @if ($rows === [])
        <div class="folha vazio">Nenhuma turma ativa no ano letivo atual.</div>
    @else
        <form method="POST" action="{{ route('school.census.classes.batch', $parametros) }}">
            @csrf
            <section class="folha" aria-labelledby="titulo-turmas">
                <div class="folha-cabecalho"><div><h2 id="titulo-turmas">Turmas ativas</h2><p>Cada envio ao Educacenso substitui a escola inteira: uma turma com pendência impede o envio da escola.</p></div></div>
                <div class="rolagem">
                    <table class="tabela censo-tabela-turmas">
                        <thead><tr><th scope="col" class="censo-escolha"><span class="sr-only">Escolher</span></th><th scope="col">Turma</th><th scope="col">Etapa do Inep</th><th scope="col" class="censo-col-situacao">Situação</th><th scope="col">Ação</th></tr></thead>
                        <tbody>
                            @foreach ($rows as $linha)
                                @php
                                    $turma = $linha['turma'];
                                    $censo = $linha['censo'];
                                    $registro = $linha['registro'];
                                    $etapa = $censo?->inep_stage_code ? $tabelas->etapa($censo->inep_stage_code) : null;
                                    $sugerida = $suggestions[$turma->id] ?? null;
                                    $pendencias = array_values(array_filter($registro->pendencias, fn ($p) => $p->recusa()));
                                @endphp
                                <tr>
                                    <td><input type="checkbox" name="turmas[]" value="{{ $turma->id }}" aria-label="Escolher {{ $turma->name }}" @checked(in_array($turma->id, old('turmas', []), true))></td>
                                    <th scope="row">{{ $turma->name }}<br><small class="texto-ajuda">{{ $turma->stage }} · {{ $turma->shift }}</small></th>
                                    <td>
                                        @if ($etapa)
                                            {{ $etapa['nome'] }}
                                        @elseif ($censo?->class_type === 5)
                                            AEE (sem etapa)
                                        @elseif ($censo?->class_type === 4)
                                            Atividade complementar (sem etapa)
                                        @elseif ($sugerida)
                                            <span class="etiqueta conferir">Sugerida</span> {{ $tabelas->etapa($sugerida)['nome'] ?? '' }}
                                        @else
                                            <span class="etiqueta neutra">Não informada</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($pendencias === [])
                                            <span class="etiqueta ok">Pronta</span>
                                        @else
                                            <span class="etiqueta conferir">{{ count($pendencias) }} {{ count($pendencias) === 1 ? 'pendência' : 'pendências' }}</span>
                                            <ul class="censo-pendencias">
                                                @foreach (array_slice($pendencias, 0, 4) as $pendencia)
                                                    <li>{{ $pendencia->mensagem }}</li>
                                                @endforeach
                                                @if (count($pendencias) > 4)<li>e mais {{ count($pendencias) - 4 }}.</li>@endif
                                            </ul>
                                        @endif
                                    </td>
                                    <td><a href="{{ route('school.census.classes.edit', [...$parametros, 'turma' => $turma->id]) }}">{{ $censo ? 'Editar' : 'Preencher' }}</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="folha censo-secao" aria-labelledby="titulo-lote">
                <div class="folha-cabecalho"><div><h2 id="titulo-lote">Valores comuns às turmas escolhidas</h2><p>Marque as turmas na lista acima. Só os campos preenchidos aqui são alterados; etapa, componentes e o restante se ajustam em cada turma.</p></div></div>
                <div class="folha-corpo">
                    <div class="censo-lote">
                        <div class="formulario-campo">
                            <label class="rotulo" for="aplicar_etapa_sugerida">Etapa do Inep</label>
                            <select class="entrada" id="aplicar_etapa_sugerida" name="aplicar_etapa_sugerida">
                                <option value="">Não alterar</option>
                                <option value="1" @selected(old('aplicar_etapa_sugerida') === '1')>Usar a sugerida nas turmas sem etapa</option>
                            </select>
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="mediation">Mediação</label>
                            <select class="entrada" id="mediation" name="mediation"><option value="">Não alterar</option><option value="1" @selected(old('mediation') === '1')>Presencial</option></select>
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="class_type">Tipo de turma</label>
                            <select class="entrada" id="class_type" name="class_type"><option value="">Não alterar</option><option value="6" @selected(old('class_type') === '6')>Curricular (etapa de ensino)</option></select>
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="organization">Forma de organização</label>
                            <select class="entrada" id="organization" name="organization"><option value="">Não alterar</option><option value="1" @selected(old('organization') === '1')>Série/ano (fora da educação infantil)</option></select>
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="differentiated_location">Local de funcionamento</label>
                            <select class="entrada" id="differentiated_location" name="differentiated_location">
                                <option value="">Não alterar</option>
                                <option value="0" @selected(old('differentiated_location') === '0')>Não é local diferenciado</option>
                                <option value="1" @selected(old('differentiated_location') === '1')>Sala anexa</option>
                            </select>
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="special_class">Classe especial</label>
                            <select class="entrada" id="special_class" name="special_class"><option value="">Não alterar</option><option value="nao" @selected(old('special_class') === 'nao')>Não</option><option value="sim" @selected(old('special_class') === 'sim')>Sim</option></select>
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="alternance">Formação por alternância</label>
                            <select class="entrada" id="alternance" name="alternance"><option value="">Não alterar</option><option value="nao" @selected(old('alternance') === 'nao')>Não</option></select>
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="bilingual_deaf">Bilíngue de surdos</label>
                            <select class="entrada" id="bilingual_deaf" name="bilingual_deaf"><option value="">Não alterar</option><option value="nao" @selected(old('bilingual_deaf') === 'nao')>Não</option><option value="sim" @selected(old('bilingual_deaf') === 'sim')>Sim</option></select>
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="semana_inicio">Segunda a sexta: início</label>
                            <input class="entrada" type="time" step="300" id="semana_inicio" name="semana_inicio" value="{{ old('semana_inicio') }}">
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="semana_fim">Segunda a sexta: fim</label>
                            <input class="entrada" type="time" step="300" id="semana_fim" name="semana_fim" value="{{ old('semana_fim') }}">
                        </div>
                    </div>
                    <p class="texto-ajuda">Gravar pede o código do segundo fator. Toda alteração fica na auditoria.</p>
                    <div class="formulario-acoes"><button class="botao" type="submit">Aplicar às turmas escolhidas</button></div>
                </div>
            </section>
        </form>
    @endif
@endsection
