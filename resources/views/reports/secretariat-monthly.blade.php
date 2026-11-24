@extends('layouts.municipal')

@section('timbre-proprio', '1')
@section('title', 'Relatório mensal da Secretaria')
@section('body-class', 'pagina-relatorio-secretaria')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/relatorio-secretaria.css') }}?v={{ filemtime(public_path('css/relatorio-secretaria.css')) }}">
@endpush

@php
    $params = ['municipio' => $municipality->slug];
    $money = fn ($value) => $value === null ? 'Não informado' : 'R$ '.number_format($value, 2, ',', '.');
    $cents = fn ($value) => $value === null ? 'Não informado' : $money($value / 100);
    $percent = fn ($value) => $value === null ? 'Sem dados' : number_format($value, 1, ',', '.').'%';
    $monthLabel = $month->locale('pt_BR')->isoFormat('MMMM [de] YYYY');
    $perfil = \App\Models\Tenant\InstitutionProfile::current();
@endphp

@section('content')
    <div class="folha-titulo">
        <div><span class="contexto">Gestão municipal</span><h1>Relatório mensal da Secretaria</h1><p>Consolidação institucional para o prefeito e a Câmara, sem informações nominais de estudantes.</p></div>
        @if ($data)<button type="button" class="botao" data-action="print-report-card">Imprimir / salvar PDF A4</button>@endif
    </div>
    <section class="folha relatorio-secretaria-filtros">
        <div class="folha-cabecalho"><div><h2>Competência</h2><p>Selecione o mês de referência.</p></div></div>
        <div class="folha-corpo">
            <form method="GET" action="{{ route('secretariat.monthly.index', $params) }}" class="secretaria-form">
                <label>Mês e ano <input class="entrada" type="month" name="mes" value="{{ $month->format('Y-m') }}" max="{{ today()->format('Y-m') }}" required></label>
                <button class="botao secundario" type="submit">Consultar</button>
            </form>
            @if ($errors->any())<p class="erro" role="alert">{{ $errors->first() }}</p>@endif
            <p class="texto-ajuda">Para gerar o PDF, use o botão de impressão e selecione Salvar como PDF, papel A4 e orientação retrato. Desative os cabeçalhos e rodapés do navegador.</p>
        </div>
    </section>
    @if (! $data)
        <section class="folha"><div class="folha-cabecalho"><h2>Ano letivo não cadastrado</h2></div><div class="folha-corpo"><p>Não há ano letivo cadastrado para {{ $month->year }}. Selecione outro mês.</p></div></section>
    @else
        <article class="secretaria-papel" aria-label="Relatório institucional">
            <header class="folha secretaria-capa">
                <div class="folha-corpo">
                    <x-documento.timbre :municipality="$municipality" :perfil="$perfil" />
                    <h2>Relatório mensal de gestão educacional</h2>
                    <p><strong>Competência:</strong> {{ ucfirst($monthLabel) }} | Ano letivo {{ $year->year }}</p>
                    <p>Emitido em {{ now()->format('d/m/Y H:i') }}. Documento institucional para acompanhamento da rede municipal.</p>
                    <p class="texto-ajuda">Os indicadores datados consideram a competência selecionada, limitada à data de emissão. Os indicadores anuais e cadastrais representam o cadastro disponível na emissão, não uma fotografia histórica do último dia do mês. Não substituem as prestações de contas nem os indicadores oficiais.</p>
                </div>
            </header>

            <section class="folha">
                <div class="folha-cabecalho"><div><h2>1. Matrículas e movimentações</h2><p>Cadastro ativo do ano letivo na emissão e movimentos com data no mês.</p></div></div>
                <div class="folha-corpo">
                    <dl class="secretaria-indicadores">
                        <div><dt>Matrículas ativas</dt><dd>{{ $data['matriculas']['active'] }}</dd></div>
                        <div><dt>Entradas no mês</dt><dd>{{ $data['matriculas']['entries'] }}</dd></div>
                        <div><dt>Saídas no mês</dt><dd>{{ $data['matriculas']['exits'] }}</dd></div>
                    </dl>
                    <p class="texto-ajuda">Entradas e saídas são vínculos de matrícula com data registrada, inclusive transferências entre escolas. Não representam necessariamente pessoas distintas.</p>
                </div>
                <div class="rolagem secretaria-rolagem"><table class="tabela secretaria-tabela">
                    <thead><tr><th scope="col">Etapa / categoria do Censo</th><th scope="col">Matrículas</th></tr></thead>
                    <tbody>
                        @foreach ($data['matriculas']['categories'] as $row)<tr><th scope="row">{{ $row['label'] }}</th><td>{{ $row['count'] }}</td></tr>@endforeach
                        <tr><th scope="row">Sem classificação</th><td>{{ $data['matriculas']['unclassified'] }}</td></tr>
                    </tbody>
                </table></div>
            </section>

            <section class="folha">
                <div class="folha-cabecalho"><div><h2>2. Frequência e busca ativa</h2><p>Frequência mensal calculada pelas marcações registradas.</p></div></div>
                <div class="folha-corpo">
                    <dl class="secretaria-indicadores">
                        <div><dt>Frequência da rede</dt><dd>{{ $percent($data['attendance']['percentage']) }}</dd></div>
                        <div><dt>Alertas de faltas</dt><dd>{{ $data['search']['alerts'] }}</dd></div>
                        <div><dt>Casos em acompanhamento na emissão</dt><dd>{{ $data['search']['open'] }}</dd></div>
                        <div><dt>Casos abertos no mês</dt><dd>{{ $data['search']['opened'] }}</dd></div>
                        <div><dt>Retornos no mês</dt><dd>{{ $data['search']['returns'] }}</dd></div>
                    </dl>
                    <p class="texto-ajuda">Frequência = presenças divididas pelo total de marcações, incluindo faltas justificadas. Dias sem chamada não são faltas. Alertas até o corte do mês para as matrículas atualmente ativas: {{ $data['search']['settings']['days'] }} faltas não justificadas seguidas ou frequência inferior a {{ $data['search']['settings']['percentage'] }}%.</p>
                </div>
                <div class="rolagem secretaria-rolagem"><table class="tabela secretaria-tabela">
                    <thead><tr><th scope="col">Escola</th><th scope="col">Marcações</th><th scope="col">Presenças</th><th scope="col">Frequência</th></tr></thead>
                    <tbody>
                        @forelse ($data['attendance']['schools'] as $row)<tr><th scope="row">{{ $row['school'] }}</th><td>{{ $row['recorded'] }}</td><td>{{ $row['present'] }}</td><td>{{ $percent($row['percentage']) }}</td></tr>
                        @empty<tr><td colspan="4">Nenhuma escola ativa.</td></tr>@endforelse
                        <tr><th scope="row">Rede</th><td>{{ $data['attendance']['recorded'] }}</td><td>{{ $data['attendance']['present'] }}</td><td>{{ $percent($data['attendance']['percentage']) }}</td></tr>
                    </tbody>
                </table></div>
            </section>

            <section class="folha">
                <div class="folha-cabecalho"><div><h2>3. Conferência do Censo e estimativas</h2><p>Cadastro disponível na emissão; estimativas anuais, não valores recebidos.</p></div></div>
                <div class="folha-corpo">
                    <dl class="secretaria-indicadores">
                        <div><dt>Matrículas com pendências</dt><dd>{{ $data['census']['warnings'] }}</dd></div>
                        <div><dt>Matrículas em risco de não classificação</dt><dd>{{ $data['census']['at_risk'] }}</dd></div>
                        <div><dt>Escolas sem código Inep</dt><dd>{{ $data['census']['without_inep'] }}</dd></div>
                        <div><dt>Fundeb estimado (VAAF)</dt><dd>{{ $money($data['census']['fundeb']) }}</dd></div>
                        <div><dt>PNAE estimado</dt><dd>{{ $money($data['census']['pnae']) }}</dd></div>
                    </dl>
                    <p class="texto-ajuda">Parâmetros do Censo de referência: {{ $data['census']['reference_year'] }}. Fundeb depende do valor-base registrado e da classificação. PNAE estimado das matrículas comuns, sem adicional individual de AEE. Valores oficiais dependem do Censo, Inep e FNDE.</p>
                </div>
            </section>

            <section class="folha">
                <div class="folha-cabecalho"><div><h2>4. Tempo integral e Fundeb</h2><p>Realizado no cadastro do ano e metas registradas na emissão.</p></div></div>
                <div class="folha-corpo">
                    <p><strong>{{ $data['integral']['total'] }}</strong> matrículas integrais; {{ $data['integral']['inferred'] }} inferidas pelo turno, sem jornada informada; {{ $data['integral']['unclassified'] }} sem etapa classificada.</p>
                </div>
                <div class="rolagem secretaria-rolagem"><table class="tabela secretaria-tabela">
                    <thead><tr><th scope="col">Etapa</th><th scope="col">Realizado</th><th scope="col">Meta</th><th scope="col">Faltam</th></tr></thead>
                    <tbody>
                        @foreach ($data['integral']['stages'] as $row)<tr><th scope="row">{{ $row['label'] }}</th><td>{{ $row['current'] }}</td><td>{{ $row['target'] ?? 'Não pactuada' }}</td><td>{{ $row['target'] === null ? 'Não calculado' : max(0, $row['target'] - $row['current']) }}</td></tr>@endforeach
                    </tbody>
                </table></div>
                <div class="folha-corpo secretaria-apos-tabela">
                    <dl class="secretaria-indicadores">
                        <div><dt>Receita Fundeb registrada</dt><dd>{{ $cents($data['integral']['revenue']) }}</dd></div>
                        <div><dt>Mínimo de 4%</dt><dd>{{ $cents($data['integral']['minimum']) }}</dd></div>
                        <div><dt>Aplicações registradas</dt><dd>{{ $cents($data['integral']['applied']) }}</dd></div>
                        <div><dt>Saldo para o mínimo</dt><dd>{{ $cents($data['integral']['remaining']) }}</dd></div>
                    </dl>
                    <p><strong>Situação:</strong> {{ $data['integral']['minimum'] === null ? 'Receita não informada' : ($data['integral']['remaining'] === 0 ? 'Mínimo atingido no cadastro' : 'Abaixo do mínimo no cadastro') }}.</p>
                    <p class="texto-ajuda">Registro interno, não comprovação de vagas ou de cumprimento financeiro homologado.</p>
                </div>
            </section>

            <section class="folha">
                <div class="folha-cabecalho"><div><h2>5. Equidade e VAAR</h2><p>Indicadores anuais agregados com a supressão de grupos pequenos e complementar do painel de equidade.</p></div></div>
                <div class="folha-corpo">
                    <p>As células protegidas não podem ser interpretadas como zero. A frequência de equidade é a média individual anual do serviço, diferente da frequência mensal por marcações.</p>
                    @if ($data['equity']['summary']['small'])<p class="texto-ajuda">Indicadores gerais de equidade protegidos por grupo pequeno.</p>@endif
                </div>
                @foreach ($data['equity']['groups'] as $group)
                    <div class="folha-corpo secretaria-subtitulo"><h3>{{ $group['label'] }}</h3></div>
                    <div class="rolagem secretaria-rolagem"><table class="tabela secretaria-tabela">
                        <thead><tr><th scope="col">Grupo</th><th scope="col">Matrículas</th><th scope="col">Frequência anual</th><th scope="col">Média de notas</th><th scope="col">Retidos</th></tr></thead>
                        <tbody>
                            @forelse ($group['rows'] as $row)
                                <tr><th scope="row">{{ $row['label'] }}</th>
                                    @if ($row['suppressed'])<td colspan="4">Protegido</td>
                                    @else
                                        <td>{{ $row['metrics']['count'] ?? 'Protegido' }}</td>
                                        <td>{{ $row['metrics']['attendance'] === null ? 'Protegido ou sem dados' : $percent($row['metrics']['attendance']) }}</td>
                                        <td>{{ $row['metrics']['average'] === null ? 'Protegido ou sem dados' : number_format($row['metrics']['average'], 1, ',', '.') }}</td>
                                        <td>{{ $row['metrics']['retained'] ?? 'Protegido ou sem dados' }}</td>
                                    @endif
                                </tr>
                            @empty<tr><td colspan="5">Sem dados.</td></tr>@endforelse
                        </tbody>
                    </table></div>
                @endforeach
                <div class="folha-corpo secretaria-subtitulo"><h3>Condicionalidades do VAAR</h3></div>
                <div class="rolagem secretaria-rolagem"><table class="tabela secretaria-tabela">
                    <thead><tr><th scope="col">Condicionalidade</th><th scope="col">Situação interna</th></tr></thead>
                    <tbody>@foreach ($data['equity']['conditions'] as $row)<tr><th scope="row">{{ $row['label'] }}</th><td>{{ $row['status'] }}</td></tr>@endforeach</tbody>
                </table></div>
            </section>

            <section class="folha">
                <div class="folha-cabecalho"><div><h2>6. Alimentação escolar</h2><p>Refeições efetivamente registradas no mês, não estimativas de presença.</p></div></div>
                <div class="folha-corpo">
                    <dl class="secretaria-indicadores">
                        <div><dt>Refeições servidas</dt><dd>{{ $data['meals']['total'] }}</dd></div>
                        <div><dt>Datas com refeições registradas</dt><dd>{{ $data['meals']['days'] }}</dd></div>
                    </dl>
                </div>
                <div class="rolagem secretaria-rolagem"><table class="tabela secretaria-tabela">
                    <thead><tr><th scope="col">Etapa</th><th scope="col">Refeições</th></tr></thead>
                    <tbody>@foreach (\App\Meals\SchoolMeals::STAGES as $key => $label)<tr><th scope="row">{{ $label }}</th><td>{{ $data['meals']['stages'][$key] }}</td></tr>@endforeach</tbody>
                </table></div>
            </section>

            <section class="folha">
                <div class="folha-cabecalho"><div><h2>7. Lista de espera</h2><p>Pedidos para o ano letivo, sem dados das crianças ou responsáveis.</p></div></div>
                <div class="folha-corpo">
                    <dl class="secretaria-indicadores">
                        <div><dt>Aguardando ou convocados no corte do mês</dt><dd>{{ $data['waitlist']['waiting'] }}</dd></div>
                        <div><dt>Pedidos atendidos no mês</dt><dd>{{ $data['waitlist']['served'] }}</dd></div>
                    </dl>
                    <p class="texto-ajuda">Contagem de pedidos não arquivados, reconstruída pelo histórico de mudanças de situação. Um pedido atendido mais de uma vez no mês é contado uma única vez.</p>
                </div>
            </section>

            <section class="folha">
                <div class="folha-cabecalho"><div><h2>8. Alfabetização</h2><p>Sondagens do 1º e 2º ano disponíveis na emissão, em quatro bimestres.</p></div></div>
                <div class="folha-corpo">
                    <p><strong>Escrita alfabética ao fim do 2º ano:</strong> {{ $percent($data['literacy']['second']['alphabetic_pct']) }} entre os avaliados no 4º bimestre. {{ $data['literacy']['second']['assessed'] }} avaliados de {{ $data['literacy']['second']['total'] }} matrículas no recorte.</p>
                    <p class="texto-ajuda">Sondagem interna. Ausência de lançamento não equivale a dificuldade de alfabetização.</p>
                </div>
                <div class="rolagem secretaria-rolagem"><table class="tabela secretaria-tabela">
                    <thead><tr><th scope="col">Indicador</th>@foreach (range(1, 4) as $term)<th scope="col">{{ $term }}º bim.</th>@endforeach</tr></thead>
                    <tbody>
                        <tr><th scope="row">Avaliados</th>@foreach ($data['literacy']['terms'] as $row)<td>{{ $row['assessed'] }}</td>@endforeach</tr>
                        <tr><th scope="row">Sem sondagem</th>@foreach ($data['literacy']['terms'] as $row)<td>{{ $row['missing'] }}</td>@endforeach</tr>
                        @foreach (\App\Models\Tenant\LiteracyAssessment::WRITING as $key => $label)<tr><th scope="row">{{ $label }}</th>@foreach ($data['literacy']['terms'] as $row)<td>{{ $row['writing'][$key] }}</td>@endforeach</tr>@endforeach
                        <tr><th scope="row">Percentual alfabético</th>@foreach ($data['literacy']['terms'] as $row)<td>{{ $percent($row['alphabetic_pct']) }}</td>@endforeach</tr>
                        @foreach (\App\Models\Tenant\LiteracyAssessment::READING as $key => $label)<tr><th scope="row">{{ $label }}</th>@foreach ($data['literacy']['terms'] as $row)<td>{{ $row['reading'][$key] }}</td>@endforeach</tr>@endforeach
                    </tbody>
                </table></div>
                <div class="folha-corpo secretaria-apos-tabela"><p class="texto-ajuda">Indicadores internos, não equivalem ao SARESP ou às avaliações oficiais de alfabetização.</p></div>
            </section>

            <footer class="folha secretaria-assinatura">
                <div class="folha-corpo"><div class="secretaria-linha-assinatura"></div>
                    @if ($perfil->secretary_name)<p><strong>{{ $perfil->secretary_name }}</strong></p>@endif
                    <p>{{ $perfil->secretary_title ?: $perfil->department_name }}</p>
                    <x-documento.rodape :municipality="$municipality" :perfil="$perfil" />
                </div>
            </footer>
        </article>
        <script defer src="{{ asset('js/boletim-imprimir.js') }}"></script>
    @endif
@endsection
