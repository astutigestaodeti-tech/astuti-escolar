@extends('layouts.municipal')
@section('title', 'Cardápio escolar')
@section('body-class', 'pagina-alimentacao')
@push('head')
<link rel="stylesheet" href="{{ asset('css/alimentacao.css') }}?v={{ filemtime(public_path('css/alimentacao.css')) }}">
@endpush
@php
    $params = ['municipio' => $municipality->slug];
    $base = $school ? route('school.meals.menu', [...$params, 'escola' => $school->id]) : route('meals.menu', $params);
    $start = $week?->toDateString();
    $weeks = $week ? [$week->subWeek(), $week->addWeek()] : [];
    $stages = \App\Meals\SchoolMeals::STAGES;
    $types = \App\Meals\SchoolMeals::MEALS;
    $days = \App\Meals\SchoolMeals::DAYS;
@endphp
@section('trilha')
<a href="{{ $school ? route('schools.show', [...$params, 'escola' => $school->id]) : route('network.panel.index', $params) }}">{{ $school ? $school->name : 'Painel da rede' }}</a>
<x-icon name="seta" :size="16" /><span aria-current="page">Cardápio escolar</span>
@endsection
@section('content')
<div class="folha-titulo"><div><span class="contexto">{{ $school ? $school->name : 'Gestão municipal' }}</span>
<h1>Cardápio escolar</h1><p>Planejamento semanal por etapa, disponível para todas as escolas da rede.</p></div>
@if ($week)<span class="marcador">{{ $week->format('d/m') }} a {{ $week->addDays(6)->format('d/m/Y') }}</span>@endif</div>
@if(session('status'))<div class="status-sucesso" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="erro" role="alert"><strong>Confira os dados.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<section class="folha alimentacao-filtro">
<div class="folha-cabecalho"><div><h2>Consultar semana</h2><p>O cardápio é único para a rede e separado por etapa.</p></div></div>
<div class="folha-corpo">
<form action="{{ $base }}" method="GET" class="alimentacao-form">
<label>Ano letivo <select class="entrada" name="ano" required>@foreach($years as $option)<option value="{{ $option->year }}" @selected($option->id === $year?->id)>{{ $option->year }}</option>@endforeach</select></label>
<label>Data da semana <input class="entrada" type="date" name="semana" value="{{ $start }}"></label>
<button class="botao" type="submit">Consultar</button>
</form>
@if($week)
<div class="alimentacao-links">
@if($canEdit)<a class="botao secundario" href="{{ route('meals.report', $params) }}">Relatório mensal PNAE</a>@endif
@if($weeks[0]->startOfWeek(\Carbon\CarbonInterface::MONDAY)->greaterThanOrEqualTo($year->start_date->copy()->startOfWeek(\Carbon\CarbonInterface::MONDAY)))
<a class="botao secundario" href="{{ $base.'?'.http_build_query(['ano' => $year->year, 'semana' => $weeks[0]->toDateString()]) }}">Semana anterior</a>
@endif
@if($weeks[1]->startOfWeek(\Carbon\CarbonInterface::MONDAY)->lessThanOrEqualTo($year->end_date->copy()->startOfWeek(\Carbon\CarbonInterface::MONDAY)))
<a class="botao secundario" href="{{ $base.'?'.http_build_query(['ano' => $year->year, 'semana' => $weeks[1]->toDateString()]) }}">Próxima semana</a>
@endif
<a class="botao secundario" href="{{ $base.'?'.http_build_query(['ano' => $year->year, 'semana' => $start, 'imprimir' => 1]) }}">Versão A4 para o mural</a>
</div>
@endif
</div></section>
@if($year && $week)
@foreach($stages as $stage => $label)
<section class="folha">
<div class="folha-cabecalho"><div><h2>{{ $label }}</h2><p>Semana iniciada em {{ $week->format('d/m/Y') }}.</p></div></div>
<div class="rolagem alimentacao-rolagem">
<table class="tabela alimentacao-tabela"><thead><tr><th scope="col">Dia</th>@foreach($types as $type)<th scope="col">{{ $type }}</th>@endforeach</tr></thead>
<tbody>
@foreach($days as $number => $day)
<tr><th scope="row">{{ $day }}<small>{{ $week->addDays($number - 1)->format('d/m') }}</small></th>
@foreach($types as $type => $meal)
<td>{{ $entries->get($stage.'|'.$number.'|'.$type)?->description ?? 'Não informado' }}</td>
@endforeach</tr>
@endforeach
</tbody></table></div></section>
@endforeach
@if($canEdit)
<section class="folha"><div class="folha-cabecalho"><div><h2>Editar cardápio</h2><p>Somente a gestão municipal pode alterar o planejamento. Todas as alterações são auditadas.</p></div></div>
<div class="folha-corpo">
<form method="POST" action="{{ route('meals.menu.save', $params) }}" class="alimentacao-form">
@csrf<input type="hidden" name="ano_id" value="{{ $year->id }}"><input type="hidden" name="semana" value="{{ $start }}">
<label>Etapa <select class="entrada" name="etapa">@foreach($stages as $stage => $label)<option value="{{ $stage }}">{{ $label }}</option>@endforeach</select></label>
<label>Dia <select class="entrada" name="dia">@foreach($days as $number => $day)<option value="{{ $number }}">{{ $day }}</option>@endforeach</select></label>
<label>Refeição <select class="entrada" name="refeicao">@foreach($types as $type => $meal)<option value="{{ $type }}">{{ $meal }}</option>@endforeach</select></label>
<label class="alimentacao-largo">Alimentos e preparações <textarea class="entrada" name="cardapio" maxlength="500" rows="2" required placeholder="Ex.: arroz, feijão, legumes e fruta">{{ old('cardapio') }}</textarea></label>
<button class="botao" type="submit">Salvar cardápio</button>
</form></div></section>
<section class="folha"><div class="folha-cabecalho"><div><h2>Copiar semana</h2><p>A cópia substitui os itens correspondentes no destino e deixa trilha de auditoria.</p></div></div>
<div class="folha-corpo">
<form method="POST" action="{{ route('meals.menu.copy', $params) }}" class="alimentacao-form">
@csrf<input type="hidden" name="ano_id" value="{{ $year->id }}">
<label>Semana de origem (segunda-feira) <input class="entrada" type="date" name="origem" value="{{ $start }}" required></label>
<label>Semana de destino (segunda-feira) <input class="entrada" type="date" name="destino" value="{{ $week->addWeek()->toDateString() }}" required></label>
<button class="botao secundario" type="submit">Copiar cardápio</button>
</form></div></section>
@endif
@else
<section class="folha"><div class="folha-corpo"><p>Não há ano letivo disponível para consultar o cardápio.</p></div></section>
@endif
@endsection
