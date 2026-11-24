@extends('layouts.municipal')
@section('title', 'Refeições servidas')
@section('body-class', 'pagina-alimentacao')
@push('head')<link rel="stylesheet" href="{{ asset('css/alimentacao.css') }}?v={{ filemtime(public_path('css/alimentacao.css')) }}">@endpush
@php
    $params = ['municipio' => $municipality->slug, 'escola' => $school->id];
    $stages = \App\Meals\SchoolMeals::STAGES;
    $types = \App\Meals\SchoolMeals::MEALS;
@endphp
@section('trilha')
<a href="{{ route('schools.show', $params) }}">{{ $school->name }}</a><x-icon name="seta" :size="16" /><span aria-current="page">Refeições servidas</span>
@endsection
@section('content')
<div class="folha-titulo"><div><span class="contexto">{{ $school->name }}</span><h1>Alimentação escolar</h1>
<p>Registro diário de refeições servidas e conferência humana das quantidades.</p></div></div>
@if(session('status'))<div class="status-sucesso" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="erro" role="alert"><strong>Confira os dados.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<section class="folha"><div class="folha-cabecalho"><div><h2>Data da refeição</h2><p>Selecione um dia até hoje, dentro do período letivo.</p></div></div>
<div class="folha-corpo">
<form method="GET" class="alimentacao-form" action="{{ route('school.meals.index', $params) }}">
<label>Data <input class="entrada" type="date" name="data" value="{{ $date->toDateString() }}" max="{{ today()->toDateString() }}" required></label>
<button class="botao" type="submit">Consultar</button>
</form>
<div class="alimentacao-links">
<a class="botao secundario" href="{{ route('school.meals.menu', [...$params, 'ano' => $year?->year, 'semana' => $date->startOfWeek()->toDateString()]) }}">Cardápio da semana</a>
<a class="botao secundario" href="{{ route('school.meals.report', [...$params, 'mes' => $date->format('Y-m')]) }}">Relatório mensal</a>
<a class="botao secundario" href="{{ route('school.meals.kitchen', $params) }}">Quadro para a cozinha</a>
@can('viewMealAllergyNames', [$school, $municipality])
<a class="botao secundario" href="{{ route('school.meals.allergies', $params) }}">Consulta nominal restrita</a>
@endcan
</div></div></section>
@if($validDate)
<section class="folha"><div class="folha-cabecalho"><div><h2>Sugestão da chamada</h2><p>A presença não comprova a refeição. Confirme a quantidade realmente servida.</p></div></div>
<div class="folha-corpo">
@if($suggested === null)<p>Sem chamada registrada nessa data. Informe a contagem real das refeições.</p>
@else
<dl class="alimentacao-indicadores">@foreach($stages as $stage => $label)<div><dt>{{ $label }}</dt><dd>{{ $suggested[$stage] }}</dd></div>@endforeach</dl>
@endif
</div></section>
<section class="folha"><div class="folha-cabecalho"><div><h2>Confirmar quantidade servida</h2><p>Direção ou secretaria registra por etapa e tipo de refeição, com segundo fator.</p></div></div>
<div class="folha-corpo">
<form action="{{ route('school.meals.save', $params) }}" method="POST" class="alimentacao-form">
@csrf<input type="hidden" name="data" value="{{ $date->toDateString() }}">
<label>Etapa <select class="entrada" name="etapa" required>@foreach($stages as $stage => $label)<option value="{{ $stage }}">{{ $label }}</option>@endforeach</select></label>
<label>Refeição <select class="entrada" name="refeicao" required>@foreach($types as $type => $meal)<option value="{{ $type }}">{{ $meal }}</option>@endforeach</select></label>
<label>Quantidade real <input class="entrada" type="number" name="quantidade" min="0" max="1000000" step="1" value="{{ old('quantidade') }}" required></label>
<button class="botao" type="submit">Confirmar registro</button>
</form>
<p class="texto-ajuda">Zero indica nenhuma refeição servida nesta combinação. Somente quantidades maiores que zero entram na contagem de dias com refeição.</p>
</div></section>
<section class="folha"><div class="folha-cabecalho"><div><h2>Lançamentos do dia</h2><p>Registrante, horário e eventual sugestão da chamada.</p></div></div>
<div class="rolagem alimentacao-rolagem"><table class="tabela alimentacao-tabela">
<thead><tr><th>Etapa</th><th>Refeição</th><th>Servidas</th><th>Sugestão</th><th>Confirmado em</th><th>Identificador do registrante</th></tr></thead>
<tbody>@forelse($entries as $row)
<tr><td>{{ $stages[$row->stage] ?? $row->stage }}</td><td>{{ $types[$row->meal_type] ?? $row->meal_type }}</td>
<td class="numero">{{ $row->quantity }}</td><td class="numero">{{ $row->suggested_present ?? 'Sem chamada' }}</td>
<td>{{ $row->confirmed_at?->format('d/m/Y H:i') }}</td><td>{{ $row->confirmed_by }}</td></tr>
@empty<tr><td colspan="6">Ainda não há refeições confirmadas nesta data.</td></tr>@endforelse</tbody></table></div></section>
<section class="folha"><div class="folha-cabecalho"><div><h2>Alertas de restrição alimentar</h2><p>Somente contagem por tipo, sem identificação dos alunos.</p></div></div>
<div class="rolagem alimentacao-rolagem"><table class="tabela alimentacao-tabela"><thead><tr><th>Tipo</th><th>Alunos</th></tr></thead><tbody>
@forelse($allergies as $type => $count)<tr><td>{{ $type }}</td><td class="numero">{{ $count }}</td></tr>
@empty<tr><td colspan="2">Nenhum alerta de alergia ou intolerância cadastrado nas matrículas ativas do ano.</td></tr>@endforelse
</tbody></table></div></section>
@else
<section class="folha"><div class="folha-corpo"><p>Não foi encontrado um ano letivo que inclua esta data. Selecione uma data dentro de um ano cadastrado.</p></div></section>
@endif
@endsection
