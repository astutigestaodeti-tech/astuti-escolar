@extends('layouts.municipal')
@section('title', 'Quadro da cozinha')
@section('body-class', 'pagina-alimentacao')
@push('head')<link rel="stylesheet" href="{{ asset('css/alimentacao.css') }}?v={{ filemtime(public_path('css/alimentacao.css')) }}">@endpush
@section('content')
<div class="folha-titulo"><div><span class="contexto">{{ $school->name }}</span><h1>Quadro da cozinha</h1><p>Contagem de alertas alimentares nas matrículas ativas {{ $year?->year ?? '' }}. Sem identificação de crianças.</p></div></div>
<section class="folha"><div class="folha-cabecalho"><div><h2>Alergias e intolerâncias por tipo</h2><p>Uso operacional na preparação das refeições.</p></div></div>
<div class="rolagem alimentacao-rolagem"><table class="tabela alimentacao-tabela"><thead><tr><th>Tipo de restrição</th><th>Quantidade de alunos</th></tr></thead><tbody>
@forelse($counts as $name => $total)<tr><th scope="row">{{ $name }}</th><td class="numero">{{ $total }}</td></tr>
@empty<tr><td colspan="2">Não há alertas cadastrados para o ano vigente. Isso não garante ausência de alergias.</td></tr>@endforelse
</tbody></table></div><div class="folha-corpo"><p class="texto-ajuda">Em caso de dúvida, confirme as orientações específicas com a direção. Um aluno pode constar em mais de um tipo. Este quadro não substitui o protocolo individual de prevenção de contaminação cruzada.</p></div></section>
@endsection
