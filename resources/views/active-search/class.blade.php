@extends('layouts.municipal')
@section('title', 'Alertas da turma')
@section('body-class', 'pagina-busca-ativa')
@push('head')
    <link rel="stylesheet" href="{{ asset('css/busca-ativa.css') }}?v={{ filemtime(public_path('css/busca-ativa.css')) }}">
@endpush
@section('content')
    <div class="folha-titulo"><div><span class="contexto">{{ $school->name }} - {{ $class->name }}</span><h1>Alertas da turma</h1><p>Consulta limitada à frequência dos alunos da turma autorizada.</p></div></div>
    <section class="folha">
        <div class="folha-cabecalho"><div><h2>Alunos em alerta</h2><p>Limites: {{ $settings['days'] }} faltas não justificadas consecutivas ou frequência mensal inferior a {{ $settings['percentage'] }}%.</p></div></div>
        <div class="rolagem busca-ativa-rolagem"><table class="tabela busca-ativa-tabela">
            <thead><tr><th scope="col">Aluno</th><th scope="col">Faltas consecutivas</th><th scope="col">Frequência no mês</th></tr></thead>
            <tbody>
            @forelse($alerted as $entry)
                <tr><th scope="row">{{ $entry->student->full_name }}</th><td>{{ $summaries[$entry->id]['streak'] }}</td><td>{{ $summaries[$entry->id]['monthly'] === null ? 'Sem chamada' : number_format($summaries[$entry->id]['monthly'], 2, ',', '.').'%' }}</td></tr>
            @empty
                <tr><td colspan="3">Nenhum aluno em alerta na turma.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="folha-corpo"><p class="texto-ajuda">Comunique a direção quando necessário. Os contatos familiares, motivos e encaminhamentos ficam na ficha administrativa restrita.</p></div>
    </section>
@endsection
