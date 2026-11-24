@extends('layouts.municipal')

@section('title', 'Tempo integral')
@section('body-class', 'pagina-tempo-integral')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/tempo-integral.css') }}?v={{ filemtime(public_path('css/tempo-integral.css')) }}">
@endpush

@php
    $params = ['municipio' => $municipality->slug];
    $route = $school
        ? route('integral.school', [...$params, 'escola' => $school->id])
        : route('integral.index', $params);
    $currency = fn (int $cents): string => 'R$ '.number_format($cents / 100, 2, ',', '.');
@endphp

@section('trilha')
    <a href="{{ $school ? route('schools.show', [...$params, 'escola' => $school->id]) : route('network.panel.index', $params) }}">{{ $school ? $school->name : 'Painel da rede' }}</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Tempo integral</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">{{ $school ? 'Acompanhamento da unidade' : 'Secretaria Municipal de Educação' }}</span>
            <h1>Tempo integral</h1>
            <p>Jornada diária, matrículas integrais, metas do programa e aplicação dos recursos do Fundeb.</p>
        </div>
        @if ($year)<span class="marcador">Ano {{ $year->year }}</span>@endif
    </div>
    @if (session('status'))<div class="status-sucesso" role="status">{{ session('status') }}</div>@endif
    @if ($errors->any())
        <div class="erro" role="alert">
            <strong>Revise os dados informados.</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="folha" aria-labelledby="integral-filtro">
        <div class="folha-cabecalho">
            <div><h2 id="integral-filtro">Ano letivo</h2><p>Selecione o ano para acompanhar as matrículas e a pactuação.</p></div>
        </div>
        <div class="folha-corpo">
            <form class="integral-filtros" action="{{ $route }}" method="GET">
                <label for="integral-ano">Ano de referência
                    <select id="integral-ano" name="ano" class="entrada" required>
                        @foreach ($years as $option)
                            <option value="{{ $option->year }}" @selected($option->id === $year?->id)>{{ $option->year }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="botao" type="submit">Consultar</button>
            </form>
        </div>
    </section>

    @if ($data)
        <section class="folha integral-secao" aria-labelledby="integral-resumo">
            <div class="folha-cabecalho">
                <div><h2 id="integral-resumo">Retrato das matrículas</h2><p>Alunos com matrícula ativa em turmas de jornada integral.</p></div>
            </div>
            <div class="folha-corpo">
                <dl class="integral-indicadores">
                    <div><dt>Integrais hoje</dt><dd>{{ $data['totals']['current'] }}</dd></div>
                    <div><dt>Novas ou convertidas</dt><dd>{{ $data['totals']['new'] }}</dd></div>
                    <div><dt>Integrais só pelo turno</dt><dd>{{ $data['totals']['inferred'] }}</dd></div>
                    <div><dt>Sem etapa classificada</dt><dd>{{ $data['totals']['without_stage'] }}</dd></div>
                </dl>
                <p class="texto-ajuda">
                    Integral: jornada informada de pelo menos 7 horas por dia. Quando não há horas, o turno cadastrado é usado provisoriamente.
                    @if ($data['baselineYear'] !== null)
                        A coluna de novas matrículas compara com os registros do ano {{ $data['baselineYear'] }}. Uma matrícula é marcada como nova se não houver vínculo integral desse aluno no ano anterior.
                    @else
                        Não existe ano letivo anterior cadastrado. Todas as matrículas integrais deste ano são tratadas como novas na comparação disponível.
                    @endif
                    É um indicador gerencial, não prova automática de criação de vagas perante o MEC.
                </p>
            </div>
        </section>

        <section class="folha integral-secao" aria-labelledby="integral-etapas">
            <div class="folha-cabecalho">
                <div><h2 id="integral-etapas">Matrículas por etapa</h2><p>Realizado no cadastro e meta pactuada no Programa Escola em Tempo Integral.</p></div>
            </div>
            <div class="rolagem integral-rolagem">
                <table class="tabela integral-tabela">
                    <thead><tr><th scope="col">Etapa</th><th scope="col">Integrais hoje</th><th scope="col">Novas ou convertidas</th>
                        @if ($manager && $school === null)<th scope="col">Meta pactuada</th><th scope="col">Diferença para a meta</th>@endif
                    </tr></thead>
                    <tbody>
                        @foreach ($data['byStage'] as $key => $stage)
                            @php($target = $targets->get($key))
                            <tr>
                                <th scope="row">{{ $stage['label'] }}</th>
                                <td class="numero">{{ $stage['current'] }}</td><td class="numero">{{ $stage['new'] }}</td>
                                @if ($manager && $school === null)
                                    <td class="numero">{{ $target?->target_enrollments ?? 'Não pactuada' }}</td>
                                    <td class="numero">{{ $target ? max(0, $target->target_enrollments - $stage['current']) : 'Não calculada' }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($manager && $school === null)
                <div class="folha-corpo">
                    <h3>Atualizar metas pactuadas</h3>
                    <p class="texto-ajuda">Informe a meta oficial por etapa. Zero é permitido quando a etapa não recebeu pactuação.</p>
                    <div class="integral-metas">
                        @foreach ($data['byStage'] as $key => $stage)
                            <form method="POST" action="{{ route('integral.target', $params) }}" class="integral-meta-form">
                                @csrf
                                <input type="hidden" name="ano_id" value="{{ $year->id }}">
                                <input type="hidden" name="etapa" value="{{ $key }}">
                                <label for="meta-{{ $key }}">{{ $stage['label'] }}
                                    <input class="entrada" id="meta-{{ $key }}" name="meta" type="number" min="0" max="1000000" step="1"
                                        value="{{ old('etapa') === $key ? old('meta') : $targets->get($key)?->target_enrollments }}" placeholder="Ex.: 20" required>
                                </label>
                                <button class="botao secundario" type="submit">Salvar meta</button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>

        @if ($manager && $school === null)
            <section class="folha integral-secao" aria-labelledby="integral-escolas">
                <div class="folha-cabecalho">
                    <div><h2 id="integral-escolas">Por escola</h2><p>Matrículas integrais da rede e evolução em relação ao ano anterior.</p></div>
                </div>
                <div class="rolagem integral-rolagem">
                    <table class="tabela integral-tabela">
                        <thead><tr><th scope="col">Escola</th><th scope="col">Integrais hoje</th><th scope="col">Novas ou convertidas</th></tr></thead>
                        <tbody>
                            @foreach ($data['bySchool'] as $item)
                                <tr><th scope="row"><a href="{{ route('integral.school', [...$params, 'escola' => $item['id'], 'ano' => $year->year]) }}">{{ $item['name'] }}</a></th>
                                    <td class="numero">{{ $item['current'] }}</td><td class="numero">{{ $item['new'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section class="folha integral-secao" aria-labelledby="integral-turmas">
            <div class="folha-cabecalho">
                <div><h2 id="integral-turmas">Turmas integrais</h2><p>A jornada informada prevalece sobre o texto do turno.</p></div>
            </div>
            @if ($data['classes']->isEmpty())
                <div class="folha-corpo"><p>Nenhuma turma integral no ano e recorte selecionados.</p></div>
            @else
                <div class="rolagem integral-rolagem">
                    <table class="tabela integral-tabela">
                        <thead><tr><th scope="col">Escola</th><th scope="col">Turma</th><th scope="col">Jornada diária</th><th scope="col">Conferência</th></tr></thead>
                        <tbody>
                            @foreach ($data['classes'] as $item)
                                <tr><td>{{ $item['school'] }}</td><th scope="row"><a href="{{ route('school.classes.show', [...$params, 'escola' => $item['school_id'], 'turma' => $item['id']]) }}">{{ $item['name'] }}</a></th>
                                    <td class="numero">{{ $item['hours'] === null ? 'Não informada' : number_format((float) $item['hours'], 1, ',', '.').' h' }}</td>
                                    <td>{{ $item['inferred'] ? 'Integral inferida pelo turno' : ($item['mismatch'] ? 'Turno e horas divergem' : 'Conferida') }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="folha integral-secao" aria-labelledby="integral-matriculas">
            <div class="folha-cabecalho">
                <div><h2 id="integral-matriculas">Matrículas integrais</h2><p>Consulta nominal restrita à gestão autorizada para acompanhar cada matrícula.</p></div>
            </div>
            @if ($data['rows']->isEmpty())
                <div class="folha-corpo"><p>Nenhuma matrícula integral ativa no recorte.</p></div>
            @else
                <div class="rolagem integral-rolagem">
                    <table class="tabela integral-tabela">
                        <thead><tr><th scope="col">Aluno</th><th scope="col">Escola</th><th scope="col">Turma</th><th scope="col">Etapa</th><th scope="col">Comparação anterior</th></tr></thead>
                        <tbody>
                            @foreach ($data['rows'] as $item)
                                <tr><th scope="row">{{ $item['student'] }}</th><td>{{ $item['school'] }}</td><td>{{ $item['class'] }}</td>
                                    <td>{{ \App\Integral\FullTimeReadiness::STAGES[$item['stage']] ?? 'Etapa não reconhecida' }}</td>
                                    <td>{{ $item['new'] ? 'Nova ou convertida' : 'Já integral' }}{{ $item['verified'] ? '' : ' (turno sem horas)' }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        @if ($manager)
            <section class="folha integral-secao" aria-labelledby="integral-fundeb">
                <div class="folha-cabecalho">
                    <div><h2 id="integral-fundeb">Aplicação mínima de 4% do Fundeb</h2>
                        <p>Registro administrativo de receita e aplicações em matrículas integrais no ano.</p></div>
                </div>
                <div class="folha-corpo">
                    <dl class="integral-indicadores">
                        <div><dt>Receita informada</dt><dd>{{ $finance ? $currency((int) $finance->revenue_cents) : 'Não informada' }}</dd></div>
                        <div><dt>Mínimo de 4%</dt><dd>{{ $minimum === null ? 'Indisponível' : $currency($minimum) }}</dd></div>
                        <div><dt>Aplicado registrado</dt><dd>{{ $currency($applied) }}</dd></div>
                        <div><dt>Falta aplicar</dt><dd>{{ $remaining === null ? 'Indisponível' : $currency($remaining) }}</dd></div>
                    </dl>
                    <form method="POST" action="{{ route('integral.revenue', $params) }}" class="integral-financeiro-form">
                        @csrf
                        <input type="hidden" name="ano_id" value="{{ $year->id }}">
                        <label for="fundeb-receita">Receita anual do Fundeb (R$)
                            <input type="text" inputmode="decimal" class="entrada" id="fundeb-receita" name="receita" maxlength="20"
                                value="{{ $finance ? number_format($finance->revenue_cents / 100, 2, ',', '.') : '' }}" placeholder="Ex.: 1.000.000,00" required>
                        </label>
                        <button type="submit" class="botao secundario">Salvar receita</button>
                    </form>
                    <p class="texto-ajuda">O mínimo é 4% da receita informada, arredondado para cima ao centavo. Não confundir registro interno de despesas com reconhecimento oficial de aplicação pelo órgão competente.</p>
                </div>
                <div class="folha-corpo">
                    <h3>Registrar valor aplicado</h3>
                    <form method="POST" action="{{ route('integral.expense.store', $params) }}" enctype="multipart/form-data" class="integral-despesa-form">
                        @csrf
                        <input type="hidden" name="ano_id" value="{{ $year->id }}">
                        <label for="fundeb-descricao">Descrição do investimento
                            <input type="text" class="entrada" id="fundeb-descricao" name="descricao" maxlength="240" placeholder="Ex.: Adequação da cozinha" required>
                        </label>
                        <label for="fundeb-valor">Valor aplicado (R$)
                            <input type="text" inputmode="decimal" class="entrada" id="fundeb-valor" name="valor" maxlength="20" placeholder="Ex.: 15.000,00" required>
                        </label>
                        <label for="fundeb-pdf">Documento PDF opcional (até 8 MB)
                            <input type="file" class="entrada" id="fundeb-pdf" name="documento" accept=".pdf,application/pdf">
                        </label>
                        <button type="submit" class="botao">Registrar aplicação</button>
                    </form>
                </div>
                <div class="folha-cabecalho"><div><h3>Aplicações registradas</h3><p>Documentos no armazenamento privado, com acesso exclusivo da gestão municipal.</p></div></div>
                @if ($expenses->isEmpty())
                    <div class="folha-corpo"><p>Ainda não foram registrados valores aplicados neste ano.</p></div>
                @else
                    <div class="rolagem integral-rolagem">
                        <table class="tabela integral-tabela">
                            <thead><tr><th scope="col">Descrição</th><th scope="col">Valor</th><th scope="col">Data</th><th scope="col">Comprovante</th></tr></thead>
                            <tbody>
                                @foreach ($expenses as $expense)
                                    <tr><th scope="row">{{ $expense->description }}</th><td class="numero">{{ $currency((int) $expense->amount_cents) }}</td>
                                        <td>{{ $expense->created_at?->format('d/m/Y') }}</td>
                                        <td>@if ($expense->evidence_path)<a href="{{ route('integral.expense.evidence', [...$params, 'despesa' => $expense->id]) }}">Baixar PDF</a>@else Sem anexo @endif</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @endif
    @else
        <section class="folha integral-secao"><div class="folha-corpo"><p>Cadastre um ano letivo para acompanhar as matrículas integrais.</p></div></section>
    @endif
@endsection
