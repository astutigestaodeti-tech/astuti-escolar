@extends('layouts.municipal')

@section('title', 'Conferência do Censo')
@section('body-class', 'pagina-censo')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/conferencia-censo.css') }}?v={{ filemtime(public_path('css/conferencia-censo.css')) }}">
@endpush

@php
    $reais = fn (?float $valor): string => $valor === null ? '' : 'R$ '.number_format($valor, 2, ',', '.');
    $numero = fn (float $valor): string => rtrim(rtrim(number_format($valor, 2, ',', '.'), '0'), ',');
    $totais = $data['totals'];
@endphp

@section('trilha')
    <a href="{{ route('network.panel.index', ['municipio' => $municipality->slug]) }}">Painel da rede</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Conferência do Censo</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Secretaria Municipal de Educação</span>
            <h1>Conferência do Censo</h1>
            <p>O que corrigir antes de enviar o Censo Escolar, para a rede não receber menos do Fundeb, da merenda e do transporte.</p>
        </div>
        <span class="marcador">Referência {{ config('censo.year_reference') }}</span>
    </div>

    <section class="folha" aria-labelledby="titulo-censo-resumo">
        <div class="folha-cabecalho">
            <div><h2 id="titulo-censo-resumo">Situação da rede</h2><p>Matrículas ativas nas escolas ativas.</p></div>
        </div>
        <div class="folha-corpo">
            <dl class="censo-resumo">
                <div><dt>Matrículas ativas</dt><dd>{{ $totais['enrollments'] }}</dd></div>
                <div><dt>Com algo a corrigir</dt><dd @if ($totais['with_warnings'] > 0) class="censo-atencao" @endif>{{ $totais['with_warnings'] }}</dd></div>
                <div><dt>Fora do Censo ou sem etapa</dt><dd @if ($totais['at_risk_enrollments'] > 0) class="censo-atencao" @endif>{{ $totais['at_risk_enrollments'] }}</dd></div>
                <div><dt>Educação especial / em AEE</dt><dd>{{ $totais['special'] }} / {{ $totais['aee'] }}</dd></div>
                <div><dt>Escolas sem código INEP</dt><dd @if ($data['schoolsWithoutInep']->isNotEmpty()) class="censo-atencao" @endif>{{ $data['schoolsWithoutInep']->count() }}</dd></div>
            </dl>
            @if ($totais['at_risk_enrollments'] > 0)
                <p class="censo-risco">
                    <x-icon name="alerta" :size="18" />
                    <span>
                        {{ $totais['at_risk_enrollments'] }} {{ $totais['at_risk_enrollments'] === 1 ? 'matrícula' : 'matrículas' }} sem turma ou sem etapa reconhecida
                        @php $verbo = $totais['at_risk_enrollments'] === 1 ? 'pode' : 'podem'; @endphp
                        @if ($vaafBase !== null)
                            {{ $verbo }} deixar de render cerca de <strong>{{ $reais($totais['at_risk_fundeb']) }}</strong> do Fundeb e
                        @else
                            {{ $verbo }} deixar de contar no Fundeb e deixar de render
                        @endif
                        cerca de <strong>{{ $reais($totais['at_risk_pnae']) }}</strong> da merenda por ano.
                    </span>
                </p>
            @endif
            @if ($data['schoolsWithoutInep']->isNotEmpty())
                <p class="texto-ajuda">Sem código INEP: {{ $data['schoolsWithoutInep']->pluck('name')->implode(', ') }}. Sem ele, a escola não aparece no Censo.</p>
            @endif
        </div>
    </section>

    <section class="folha censo-secao" aria-labelledby="titulo-censo-estimativa">
        <div class="folha-cabecalho">
            <div>
                <h2 id="titulo-censo-estimativa">Estimativa de receita por ano</h2>
                <p>Calculada com as matrículas de hoje. É uma estimativa: o valor final depende do Censo, do Inep e do FNDE.</p>
            </div>
        </div>
        <div class="rolagem censo-rolagem">
            <table class="tabela censo-tabela">
                <thead>
                    <tr>
                        <th scope="col">Categoria do Censo</th>
                        <th scope="col" class="numero">Matrículas</th>
                        <th scope="col" class="numero">Peso no Fundeb</th>
                        <th scope="col" class="numero">Matrículas ponderadas</th>
                        @if ($vaafBase !== null)<th scope="col" class="numero">Fundeb estimado</th>@endif
                        <th scope="col" class="numero">Merenda (PNAE)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data['categories'] as $categoria)
                        <tr @if ($categoria['count'] === 0) class="censo-vazia" @endif>
                            <th scope="row">{{ $categoria['label'] }}</th>
                            <td class="numero">{{ $categoria['count'] }}</td>
                            <td class="numero">{{ number_format($categoria['weight'], 2, ',', '.') }}</td>
                            <td class="numero">{{ $numero($categoria['weighted']) }}</td>
                            @if ($vaafBase !== null)<td class="numero">{{ $reais($categoria['fundeb']) }}</td>@endif
                            <td class="numero">{{ $reais($categoria['pnae']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tbody class="censo-adicional">
                    <tr>
                        <th scope="row">AEE no contraturno (adicional)</th>
                        <td class="numero">{{ $totais['aee'] }}</td>
                        <td class="numero">adicional</td>
                        <td class="numero"></td>
                        @if ($vaafBase !== null)<td class="numero"></td>@endif
                        <td class="numero">{{ $reais($totais['aee_pnae']) }}</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <th scope="row">Total contado no Censo</th>
                        <td class="numero">{{ $data['categories'] ? array_sum(array_column($data['categories'], 'count')) : 0 }}</td>
                        <td></td>
                        <td class="numero">{{ $numero($totais['weighted']) }}</td>
                        @if ($vaafBase !== null)<td class="numero">{{ $reais($totais['fundeb']) }}</td>@endif
                        <td class="numero">{{ $reais($totais['pnae'] + $totais['aee_pnae']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="folha-corpo">
            @if (session('status'))
                <div class="status-sucesso" role="status"><x-icon name="info" :size="18" /> {{ session('status') }}</div>
            @endif
            <form method="POST" action="{{ route('census.readiness.parameters', ['municipio' => $municipality->slug]) }}" class="censo-parametros">
                @csrf
                <label for="vaaf_base">Valor anual por aluno do Fundeb no estado ({{ config('censo.year_reference') }})
                    <input class="entrada" id="vaaf_base" name="vaaf_base" inputmode="decimal" required placeholder="Ex.: 6.123,45"
                        value="{{ old('vaaf_base', $parameter ? number_format((float) $parameter->vaaf_base, 2, ',', '.') : '') }}">
                </label>
                <label for="source_note">De onde veio o valor
                    <input class="entrada" id="source_note" name="source_note" maxlength="200" required placeholder="Ex.: portaria do FNDE"
                        value="{{ old('source_note', $parameter?->source_note) }}">
                </label>
                <button class="botao secundario" type="submit">Salvar valor</button>
            </form>
            @error('vaaf_base')<p class="erro-campo">{{ $message }}</p>@enderror
            @error('source_note')<p class="erro-campo">{{ $message }}</p>@enderror
            <p class="texto-ajuda">
                A creche e a pré-escola em tempo integral têm os maiores pesos no Fundeb, e o aluno integral recebe quase três vezes mais na merenda.
                Confira se o turno de cada turma está certo. A matrícula de AEE no contraturno também tem peso adicional no Fundeb; o valor depende da resolução do ano e não entra nesta estimativa.
                @if ($vaafBase === null)
                    Para ver o Fundeb em reais, informe acima o valor anual por aluno do estado, publicado pelo FNDE para o ano (fator 1,00).
                @endif
            </p>
        </div>
    </section>

    <section class="folha censo-secao" aria-labelledby="titulo-censo-escolas">
        <div class="folha-cabecalho">
            <div><h2 id="titulo-censo-escolas">Por escola</h2><p>O número de matrículas com algo a corrigir abre a lista da escola. Turmas e alunos prontos seguem o formato do Educacenso (leiaute oficial do Inep).</p></div>
        </div>
        <div class="rolagem censo-rolagem">
            <table class="tabela censo-tabela">
                <thead>
                    <tr>
                        <th scope="col">Escola</th>
                        <th scope="col">Código INEP</th>
                        <th scope="col" class="numero">Matrículas ativas</th>
                        <th scope="col" class="numero">Com algo a corrigir</th>
                        <th scope="col" class="numero">Turmas prontas para o Educacenso</th>
                        <th scope="col" class="numero">Alunos prontos para o Educacenso</th>
                        <th scope="col">Escola pronta para o Educacenso</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data['schools'] as $linha)
                        <tr>
                            <th scope="row">{{ $linha['school']->name }}</th>
                            <td class="censo-codigo">
                                @if (trim((string) $linha['school']->inep_code) !== '')
                                    {{ $linha['school']->inep_code }}
                                @else
                                    <span class="censo-atencao">Não informado</span>
                                @endif
                            </td>
                            <td class="numero">{{ $linha['enrollments'] }}</td>
                            <td class="numero">
                                <a href="{{ route('census.readiness.index', ['municipio' => $municipality->slug, 'escola' => $linha['school']->id]) }}#titulo-censo-lista" @if ($linha['with_warnings'] > 0) class="censo-atencao" @endif>{{ $linha['with_warnings'] }}</a>
                            </td>
                            <td class="numero">
                                @php
                                    $turmasEscola = $classes->get($linha['school']->id, ['total' => 0, 'ready' => 0]);
                                @endphp
                                <a href="{{ route('school.census.classes.index', ['municipio' => $municipality->slug, 'escola' => $linha['school']->id]) }}" @if ($turmasEscola['ready'] < $turmasEscola['total']) class="censo-atencao" @endif>{{ $turmasEscola['ready'] }} de {{ $turmasEscola['total'] }}</a>
                            </td>
                            <td class="numero">
                                @php
                                    $alunosEscola = $students->get($linha['school']->id, ['total' => 0, 'ready' => 0]);
                                @endphp
                                <a href="{{ route('school.census.students.index', ['municipio' => $municipality->slug, 'escola' => $linha['school']->id]) }}" @if ($alunosEscola['ready'] < $alunosEscola['total']) class="censo-atencao" @endif>{{ $alunosEscola['ready'] }} de {{ $alunosEscola['total'] }}</a>
                            </td>
                            <td>
                                <a href="{{ route('school.census.school.edit', ['municipio' => $municipality->slug, 'escola' => $linha['school']->id]) }}" @if (! $schoolReadiness->get($linha['school']->id, false)) class="censo-atencao" @endif>{{ $schoolReadiness->get($linha['school']->id, false) ? 'Sim' : 'Não - conferir' }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="folha censo-secao" aria-labelledby="titulo-censo-lista">
        <div class="folha-cabecalho">
            <div><h2 id="titulo-censo-lista">O que corrigir</h2><p>O nome abre a ficha do aluno, onde a correção é feita.</p></div>
        </div>
        <div class="folha-corpo">
            <form method="GET" action="{{ route('census.readiness.index', ['municipio' => $municipality->slug]) }}#titulo-censo-lista" class="censo-filtros">
                <label for="censo-escola">Escola
                    <select name="escola" id="censo-escola">
                        <option value="">Todas</option>
                        @foreach ($data['schools'] as $linha)
                            <option value="{{ $linha['school']->id }}" @selected($filters['escola'] === $linha['school']->id)>{{ $linha['school']->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label for="censo-aviso">O que corrigir
                    <select name="aviso" id="censo-aviso">
                        <option value="">Tudo</option>
                        @foreach (\App\Census\CensusReadiness::WARNINGS as $chave => $rotulo)
                            <option value="{{ $chave }}" @selected($filters['aviso'] === $chave)>{{ $rotulo }} ({{ $data['byType'][$chave] }})</option>
                        @endforeach
                    </select>
                </label>
                <button class="botao secundario" type="submit">Filtrar</button>
            </form>
            @if ($listTotal > $limit)
                <p class="texto-ajuda">Mostrando {{ $limit }} de {{ $listTotal }}. Filtre por escola ou pelo que corrigir para ver o restante.</p>
            @endif
        </div>
        @if ($list->isEmpty())
            <div class="folha-corpo"><p>Nada a corrigir com esses filtros.</p></div>
        @else
            <div class="rolagem censo-rolagem">
                <table class="tabela censo-tabela censo-lista">
                    <thead>
                        <tr>
                            <th scope="col">Aluno</th>
                            <th scope="col">Escola</th>
                            <th scope="col">Turma</th>
                            <th scope="col">O que corrigir</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($list as $item)
                            <tr>
                                <th scope="row"><a href="{{ route('school.students.show', ['municipio' => $municipality->slug, 'escola' => $item['school_id'], 'aluno' => $item['student_id']]) }}">{{ $item['name'] }}</a></th>
                                <td>{{ $item['school'] }}</td>
                                <td>{{ $item['class'] ?? 'Sem turma' }}</td>
                                <td>
                                    <ul class="censo-avisos">
                                        @foreach ($item['warnings'] as $aviso)
                                            <li>{{ \App\Census\CensusReadiness::WARNINGS[$aviso] }}</li>
                                        @endforeach
                                    </ul>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
