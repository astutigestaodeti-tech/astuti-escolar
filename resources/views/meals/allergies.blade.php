@extends('layouts.municipal')
@section('title', 'Restrições alimentares: consulta nominal')
@section('body-class', 'pagina-alimentacao')
@push('head')<link rel="stylesheet" href="{{ asset('css/alimentacao.css') }}?v={{ filemtime(public_path('css/alimentacao.css')) }}">@endpush
@section('content')
<div class="folha-titulo"><div><span class="contexto">{{ $school->name }}</span><h1>Consulta nominal restrita</h1><p>Exclusiva da direção e secretaria escolar. Não imprimir nem compartilhar com a cozinha.</p></div></div>
<section class="folha"><div class="folha-cabecalho"><div><h2>Alunos com alerta alimentar {{ $year?->year ?? '' }}</h2><p>Apenas o tipo geral: consulte a ficha de saúde autorizada para instruções específicas.</p></div></div>
<div class="rolagem alimentacao-rolagem"><table class="tabela alimentacao-tabela"><thead><tr><th>Aluno</th><th>Tipo de alerta</th></tr></thead><tbody>
@forelse($names as $row)<tr><td>{{ $row['student'] }}</td><td>{{ $row['type'] }}</td></tr>
@empty<tr><td colspan="2">Nenhum alerta cadastrado para matrículas ativas do ano vigente.</td></tr>@endforelse
</tbody></table></div></section>
@endsection
