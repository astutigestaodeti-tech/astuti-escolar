@extends('layouts.municipal')
@section('title', 'Relatório mensal PNAE')
@section('body-class', 'pagina-alimentacao')
@push('head')<link rel="stylesheet" href="{{ asset('css/alimentacao.css') }}?v={{ filemtime(public_path('css/alimentacao.css')) }}">@endpush
@php
$params = ['municipio' => $municipality->slug];
$stages = \App\Meals\SchoolMeals::STAGES;
$url = $school ? route('school.meals.report', [...$params, 'escola' => $school->id]) : route('meals.report', $params);
$brl = fn ($n) => 'R$ '.number_format((float) $n, 2, ',', '.');
@endphp
@section('trilha')
<a href="{{ $school ? route('schools.show', [...$params, 'escola' => $school->id]) : route('network.panel.index', $params) }}">{{ $school?->name ?? 'Painel da rede' }}</a><x-icon name="seta" :size="16" /><span aria-current="page">Relatório PNAE</span>
@endsection
@section('content')
<div class="folha-titulo"><div><span class="contexto">{{ $school?->name ?? 'Secretaria Municipal de Educação' }}</span>
<h1>Relatório mensal de alimentação</h1><p>Controle operacional de refeições e referência estimada para o PNAE e o CAE.</p></div></div>
<section class="folha"><div class="folha-cabecalho"><div><h2>Período</h2><p>Imprima esta página em A4 para conferência administrativa.</p></div></div>
<div class="folha-corpo"><form class="alimentacao-form" action="{{ $url }}" method="GET">
<label>Mês <input class="entrada" name="mes" type="month" value="{{ $month }}" required></label>
<button class="botao" type="submit">Consultar</button></form>
<p class="texto-ajuda">Referência: {{ $month }}. O sistema conta apenas dias com pelo menos uma refeição servida em quantidade positiva.</p>
</div></section>
@if($data)
<section class="folha"><div class="folha-cabecalho"><div><h2>Consolidado {{ $school ? 'da escola' : 'da rede' }}</h2><p>Quantidade efetivamente confirmada nas unidades.</p></div></div>
<div class="folha-corpo">
<dl class="alimentacao-indicadores">
<div><dt>Dias distintos com refeição</dt><dd>{{ $data['totals']['days'] }}</dd></div>
<div><dt>Escola-dias com refeição</dt><dd>{{ $data['schoolDays'] }}</dd></div>
<div><dt>Refeições servidas</dt><dd>{{ number_format($data['totals']['total'], 0, ',', '.') }}</dd></div>
<div><dt>Média por escola-dia</dt><dd>{{ number_format($data['totals']['average'], 1, ',', '.') }}</dd></div>
</dl>
<p class="texto-ajuda">A média da rede divide as refeições pelo total de dias registrados em cada escola (escola-dias). Não é a média de alunos nem de calorias.</p>
</div></section>
<section class="folha"><div class="folha-cabecalho"><div><h2>Por escola e etapa</h2><p>Refeições confirmadas por creche, pré-escola e Ensino Fundamental.</p></div></div>
<div class="rolagem alimentacao-rolagem"><table class="tabela alimentacao-tabela"><thead><tr><th>Escola</th><th>Dias</th>
@foreach($stages as $label)<th>{{ $label }}</th>@endforeach
<th>Total servido</th><th>Média diária</th><th>PNAE ref. dos dias (R$)</th></tr></thead><tbody>
@foreach($data['rows'] as $row)<tr><th scope="row">{{ $row['school']->name }}</th><td class="numero">{{ $row['days'] }}</td>
@foreach($stages as $stage => $label)<td class="numero">{{ $row['byStage'][$stage] }}</td>@endforeach
<td class="numero">{{ $row['total'] }}</td><td class="numero">{{ number_format($row['average'], 1, ',', '.') }}</td>
<td class="numero">{{ $brl($row['reference']) }}</td></tr>@endforeach
<tr><th scope="row">Total</th><td class="numero">{{ $data['schoolDays'] }} escola-dias</td>
@foreach($stages as $stage => $label)<td class="numero">{{ $data['totals']['byStage'][$stage] }}</td>@endforeach
<td class="numero">{{ $data['totals']['total'] }}</td><td class="numero">{{ number_format($data['totals']['average'], 1, ',', '.') }}</td>
<td class="numero">{{ $brl($data['totals']['reference']) }}</td></tr>
</tbody></table></div></section>
<section class="folha"><div class="folha-cabecalho"><div><h2>Referência financeira do PNAE</h2><p>Comparação de cobertura de dias, não demonstração de despesas.</p></div></div>
<div class="folha-corpo"><dl class="alimentacao-indicadores">
<div><dt>Estimativa anual de repasse ({{ config('censo.pnae_days', 200) }} dias)</dt><dd>{{ $brl($data['totals']['pnae_annual']) }}</dd></div>
<div><dt>Parcela estimada para escola-dias atendidos</dt><dd>{{ $brl($data['totals']['reference']) }}</dd></div>
<div><dt>Matrículas sem classificação para esta estimativa</dt><dd>{{ $data['totals']['unclassified'] }}</dd></div>
</dl>
<p class="texto-ajuda">Base: taxas de alimentação por matrícula e dia de config/censo.php, tal como a Conferência do Censo. A estimativa proporcional usa os dias com refeição registrados por unidade. Não representa repasse recebido, gasto real, custo por refeição, prestação de contas oficial nem transferência automática de recursos. Confira valores e censo homologados com o FNDE e o nutricionista responsável técnico.</p>
</div></section>
@else<section class="folha"><div class="folha-corpo"><p>Não há ano letivo cadastrado para o mês informado.</p></div></section>@endif
@endsection
