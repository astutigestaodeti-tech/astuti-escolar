@extends('layouts.municipal')

@section('title', 'Painel de equidade e VAAR')
@section('body-class', 'pagina-equidade-vaar')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/equidade-vaar.css') }}?v={{ filemtime(public_path('css/equidade-vaar.css')) }}">
@endpush

@php
    $params = ['municipio' => $municipality->slug];
    $selection = array_filter(['ano' => $year?->year, 'escola' => $schoolId, 'turma' => $classId]);
    $url = $school
        ? route('equity.vaar.school', [...$params, 'escola' => $school->id])
        : route('equity.vaar.index', $params);
    $formatNumber = fn (?float $n): string => $n === null ? 'Dados insuficientes' : number_format($n, 1, ',', '.');
    $metric = function (array $metrics, string $key) use ($formatNumber): string {
        if ($metrics['small']) {
            return 'grupo pequeno';
        }
        if ($key === 'count') {
            return (string) $metrics['count'];
        }
        if ($key === 'retained') {
            return $metrics['retained'] === null ? 'Dados insuficientes' : (string) $metrics['retained'];
        }

        return $metrics[$key] === null ? 'Dados insuficientes'
            : $formatNumber($metrics[$key]).($key === 'attendance' ? '%' : '');
    };
@endphp

@section('trilha')
    @if ($manager)
        <a href="{{ route('network.panel.index', $params) }}">Painel da rede</a>
    @else
        <a href="{{ route('schools.show', [...$params, 'escola' => $schoolId]) }}">Escola</a>
    @endif
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Equidade e VAAR</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">{{ $manager ? 'Secretaria Municipal de Educação' : 'Direção escolar' }}</span>
            <h1>Painel de equidade e VAAR</h1>
            <p>Frequência, médias e resultados escolares por grupos. Sinais para agir, não aferição oficial do Inep.</p>
        </div>
        @if ($year)<span class="marcador">Ano letivo {{ $year->year }}</span>@endif
    </div>
    @if (session('status'))<div class="status-sucesso" role="status">{{ session('status') }}</div>@endif
    @if ($errors->any())
        <div class="erro" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <section class="folha" aria-labelledby="equidade-filtros">
        <div class="folha-cabecalho">
            <div>
                <h2 id="equidade-filtros">Recorte dos indicadores</h2>
                <p>Compare a rede, uma escola ou uma turma sem revelar dados individuais sensíveis.</p>
            </div>
        </div>
        <div class="folha-corpo">
            <form method="GET" action="{{ $url }}" class="equidade-filtros">
                <label for="equidade-ano">Ano letivo
                    <select name="ano" id="equidade-ano">
                        @foreach ($years as $option)
                            <option value="{{ $option->year }}" @selected($year?->year === $option->year)>{{ $option->year }}</option>
                        @endforeach
                    </select>
                </label>
                @if ($manager && $school === null)
                    <label for="equidade-escola">Escola
                        <select name="escola" id="equidade-escola">
                            <option value="">Toda a rede</option>
                            @foreach ($allSchools as $option)
                                <option value="{{ $option->id }}" @selected($schoolId === $option->id)>{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                @if ($schoolId && $data)
                    <label for="equidade-turma">Turma
                        <select name="turma" id="equidade-turma">
                            <option value="">Todas as turmas</option>
                            @foreach ($data['classes']->where('school_id', $schoolId) as $option)
                                <option value="{{ $option->id }}" @selected($classId === $option->id)>{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <button type="submit" class="botao">Aplicar filtros</button>
                <a class="botao secundario" href="{{ $url }}">Limpar filtros</a>
            </form>
            <p class="texto-ajuda">Para selecionar uma turma de outra escola, escolha primeiro a escola e aplique o filtro.</p>
        </div>
    </section>

    @if ($data)
        <section class="folha equidade-secao" aria-labelledby="equidade-resumo">
            <div class="folha-cabecalho">
                <div><h2 id="equidade-resumo">Resumo do recorte</h2>
                    <p>Somente matrículas ativas ou concluídas do ano escolhido. Grupos com menos de cinco alunos são ocultados.</p>
                </div>
            </div>
            <div class="folha-corpo">
                <dl class="equidade-indicadores">
                    <div><dt>Matrículas</dt><dd>{{ $metric($data['summary'], 'count') }}</dd></div>
                    <div><dt>Frequência média</dt><dd>{{ $metric($data['summary'], 'attendance') }}</dd></div>
                    <div><dt>Média de notas</dt><dd>{{ $metric($data['summary'], 'average') }}</dd></div>
                    <div><dt>Retidos confirmados</dt><dd>{{ $metric($data['summary'], 'retained') }}</dd></div>
                </dl>
                <p class="texto-ajuda">Média de notas: média das médias por componente lançadas para cada aluno, sem tratar notas ausentes como zero. Frequência: presença nas chamadas registradas. Retenção: somente resultado final confirmado.</p>
            </div>
        </section>

        @foreach ($data['groups'] as $dimensionKey => $dimension)
            <section class="folha equidade-secao" aria-labelledby="equidade-grupo-{{ $dimensionKey }}">
                <div class="folha-cabecalho">
                    <div><h2 id="equidade-grupo-{{ $dimensionKey }}">{{ $dimension['label'] }}</h2>
                        <p>Indicadores agregados. Não há relação nominal entre estudantes e grupos.</p>
                    </div>
                </div>
                @if ($dimension['rows'] === [])
                    <div class="folha-corpo"><p>Não há matrículas neste recorte.</p></div>
                @else
                    <div class="rolagem equidade-rolagem">
                        <table class="tabela equidade-tabela">
                            <thead><tr>
                                <th scope="col">Grupo</th>
                                <th scope="col">Alunos</th>
                                <th scope="col">Frequência média</th>
                                <th scope="col">Média de notas</th>
                                <th scope="col">Retidos confirmados</th>
                            </tr></thead>
                            <tbody>
                                @foreach ($dimension['rows'] as $item)
                                    <tr>
                                        <th scope="row">{{ $item['label'] }}</th>
                                        @if ($item['suppressed'])
                                            <td colspan="4" class="equidade-protegido">grupo pequeno</td>
                                        @else
                                            <td class="numero">{{ $metric($item['metrics'], 'count') }}</td>
                                            <td class="numero">{{ $metric($item['metrics'], 'attendance') }}</td>
                                            <td class="numero">{{ $metric($item['metrics'], 'average') }}</td>
                                            <td class="numero">{{ $metric($item['metrics'], 'retained') }}</td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @endforeach

        <section class="folha equidade-secao" aria-labelledby="equidade-deficiencia">
            <div class="folha-cabecalho">
                <div><h2 id="equidade-deficiencia">Deficiência</h2>
                    <p>Indicador indisponível até existir campo institucional estruturado e autorizado.</p>
                </div>
            </div>
            <div class="folha-corpo">
                <p>Não é correto deduzir deficiência de textos livres de saúde, diagnósticos ou alertas. O sistema não expõe essa classificação neste painel.</p>
            </div>
        </section>

        @if ($manager && ! $schoolId)
            <section class="folha equidade-secao" aria-labelledby="equidade-escolas">
                <div class="folha-cabecalho">
                    <div><h2 id="equidade-escolas">Comparar escolas</h2>
                        <p>Abra uma escola para ver seus grupos. Não há nomes de alunos nesta tabela.</p>
                    </div>
                </div>
                <div class="rolagem equidade-rolagem">
                    <table class="tabela equidade-tabela">
                        <thead><tr><th scope="col">Escola</th><th scope="col">Matrículas</th>
                            <th scope="col">Frequência</th><th scope="col">Média</th><th scope="col">Retidos</th></tr></thead>
                        <tbody>
                            @foreach ($data['schoolSummaries'] as $item)
                                <tr>
                                    <th scope="row"><a href="{{ route('equity.vaar.index', [...$params, 'ano' => $year->year, 'escola' => $item['id']]) }}">{{ $item['name'] }}</a></th>
                                    <td class="numero">{{ $metric($item['metrics'], 'count') }}</td>
                                    <td class="numero">{{ $metric($item['metrics'], 'attendance') }}</td>
                                    <td class="numero">{{ $metric($item['metrics'], 'average') }}</td>
                                    <td class="numero">{{ $metric($item['metrics'], 'retained') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if ($schoolId && ! $classId)
            <section class="folha equidade-secao" aria-labelledby="equidade-turmas">
                <div class="folha-cabecalho">
                    <div><h2 id="equidade-turmas">Comparar turmas</h2>
                        <p>Abra uma turma para consultar os grupos com a mesma regra de proteção.</p>
                    </div>
                </div>
                <div class="rolagem equidade-rolagem">
                    <table class="tabela equidade-tabela">
                        <thead><tr><th scope="col">Turma</th><th scope="col">Matrículas</th>
                            <th scope="col">Frequência</th><th scope="col">Média</th><th scope="col">Retidos</th></tr></thead>
                        <tbody>
                            @foreach ($data['classSummaries'] as $item)
                                <tr>
                                    <th scope="row">
                                        <a href="{{ $school ? route('equity.vaar.school', [...$params, 'escola' => $schoolId, 'ano' => $year->year, 'turma' => $item['id']]) : route('equity.vaar.index', [...$params, 'escola' => $schoolId, 'ano' => $year->year, 'turma' => $item['id']]) }}">{{ $item['name'] }}</a>
                                    </th>
                                    <td class="numero">{{ $metric($item['metrics'], 'count') }}</td>
                                    <td class="numero">{{ $metric($item['metrics'], 'attendance') }}</td>
                                    <td class="numero">{{ $metric($item['metrics'], 'average') }}</td>
                                    <td class="numero">{{ $metric($item['metrics'], 'retained') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section class="folha equidade-secao" aria-labelledby="equidade-saeb">
            <div class="folha-cabecalho">
                <div><h2 id="equidade-saeb">Condicionalidade II: acompanhamento de faltas</h2>
                    <p>Alunos dos 2º, 5º e 9º anos com mais faltas no ano selecionado.</p>
                </div>
            </div>
            <div class="folha-corpo">
                <p class="texto-ajuda">Esta é uma lista operacional de frequência escolar, restrita à gestão autorizada. <strong>Frequência às aulas não é taxa de participação no Saeb.</strong> A exigência legal de participação mínima de 80% no Saeb deve ser comprovada no sistema oficial do Inep.</p>
            </div>
            @if ($data['saeb']->isEmpty())
                <div class="folha-corpo"><p>Nenhuma ausência registrada nos anos escolares acompanhados.</p></div>
            @else
                <div class="rolagem equidade-rolagem">
                    <table class="tabela equidade-tabela">
                        <thead><tr><th scope="col">Aluno</th><th scope="col">Escola</th>
                            <th scope="col">Turma</th><th scope="col">Faltas</th><th scope="col">Frequência escolar</th></tr></thead>
                        <tbody>
                            @foreach ($data['saeb'] as $item)
                                <tr><th scope="row">{{ $item['name'] }}</th><td>{{ $item['school'] }}</td>
                                    <td>{{ $item['class'] }}</td><td class="numero">{{ $item['absences'] }}</td>
                                    <td class="numero">{{ $formatNumber($item['attendance']) }}%</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="folha-corpo"><p class="texto-ajuda">Exibidos até 50 alunos com faltas, em ordem decrescente. Não inclui cor/raça, sexo, deficiência ou zona de residência.</p></div>
            @endif
        </section>
    @else
        <section class="folha equidade-secao"><div class="folha-corpo">
            <p>Nenhum ano letivo cadastrado. Cadastre um ano para iniciar o acompanhamento.</p>
        </div></section>
    @endif

    @if ($manager && $year)
        <section class="folha equidade-secao" aria-labelledby="equidade-condicionalidades">
            <div class="folha-cabecalho">
                <div><h2 id="equidade-condicionalidades">Condicionalidades I a V do VAAR</h2>
                    <p>Registro interno da Prefeitura. O status lançado não equivale a habilitação ou homologação pelo MEC/Inep.</p>
                </div>
            </div>
            <div class="folha-corpo">
                <p class="texto-ajuda">Anexe somente documentos institucionais, sem identificação de crianças. PDFs ficam em armazenamento privado e são baixados apenas pela gestão municipal. I, IV e V são avaliadas pelo MEC; II e III, pelo Inep.</p>
            </div>
            @foreach (\App\Equity\EquityReadiness::CONDITIONS as $number => $label)
                @php($condition = $conditions->get($number))
                <div class="folha-corpo equidade-condicao">
                    <div>
                        <h3>{{ ['I', 'II', 'III', 'IV', 'V'][$number - 1] }}. {{ $label }}</h3>
                        <p class="texto-ajuda">Situação: <strong>{{ \App\Equity\EquityReadiness::STATUSES[$condition?->status ?? 'pendente'] }}</strong></p>
                        @if ($condition?->evidence_path)
                            <p>
                                Anexo:
                                <a href="{{ route('equity.vaar.evidence', [...$params, 'condicao' => $number, 'ano' => $year->year]) }}">{{ $condition->evidence_name }}</a>
                            </p>
                        @else
                            <p class="texto-ajuda">Sem documento anexado.</p>
                        @endif
                    </div>
                    <form action="{{ route('equity.vaar.condition', [...$params, 'condicao' => $number]) }}"
                          method="POST" enctype="multipart/form-data" class="equidade-condicao-form">
                        @csrf
                        <input type="hidden" name="ano" value="{{ $year->year }}">
                        <label for="vaar-situacao-{{ $number }}">Situação
                            <select name="status" id="vaar-situacao-{{ $number }}">
                                @foreach (\App\Equity\EquityReadiness::STATUSES as $code => $text)
                                    <option value="{{ $code }}" @selected(($condition?->status ?? 'pendente') === $code)>{{ $text }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label for="vaar-responsavel-{{ $number }}">Responsável
                            <input class="entrada" type="text" name="responsavel" id="vaar-responsavel-{{ $number }}"
                                   maxlength="120" value="{{ $condition?->responsible }}" placeholder="Ex.: Coordenação" required>
                        </label>
                        <label for="vaar-pdf-{{ $number }}">Documento PDF (até 8 MB)
                            <input class="entrada" type="file" accept=".pdf,application/pdf" name="documento" id="vaar-pdf-{{ $number }}">
                        </label>
                        @if ($condition?->evidence_path)
                            <label class="equidade-caixa">
                                <input type="checkbox" name="remover_documento" value="1"> Remover anexo atual
                            </label>
                        @endif
                        <button type="submit" class="botao secundario">Salvar condicionalidade</button>
                    </form>
                </div>
            @endforeach
        </section>
    @endif

    <section class="folha equidade-secao" aria-labelledby="equidade-metodologia">
        <div class="folha-cabecalho"><div><h2 id="equidade-metodologia">Como interpretar os dados</h2></div></div>
        <div class="folha-corpo">
            <p>Os números usam lançamentos escolares existentes e não reproduzem os indicadores oficiais de equidade do Saeb/VAAR. Ocultei grupos com menos de cinco alunos e apliquei supressão complementar para evitar reconstruir sua quantidade pelo total. Se menos de cinco alunos tiverem notas ou chamadas, a respectiva média também não aparece.</p>
            <p>Não há campo de deficiência estruturado nesta versão. Não é permitido inferir deficiência de registros de saúde. Somente a gestão municipal acessa as condicionalidades; a direção escolar vê dados da própria unidade.</p>
            <p class="texto-ajuda">Referência normativa: Lei 14.113/2020, art. 14, e orientações oficiais do FNDE e do Inep. A avaliação de cumprimento é externa ao sistema.</p>
        </div>
    </section>
@endsection
