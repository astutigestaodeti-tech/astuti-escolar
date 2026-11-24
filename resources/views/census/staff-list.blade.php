@extends('layouts.municipal')

@php
    $parametros = ['municipio' => $municipality->slug, 'escola' => $school->id];
@endphp

@section('title', 'Servidores no Censo')
@section('body-class', 'pagina-censo-turmas')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/censo-turmas.css') }}?v={{ filemtime(public_path('css/censo-turmas.css')) }}">
@endpush

@section('trilha')
    <a href="{{ route('schools.show', $parametros) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Servidores no Censo</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Censo Escolar</span>
            <h1>Servidores no Censo</h1>
            <p>Gestores e profissionais com atribuição vigente. Conferência dos registros 30, 40 e 50.</p>
        </div>
        <a class="botao secundario" href="{{ route('school.staff.index', $parametros) }}">Voltar aos servidores</a>
    </div>
    <section class="folha" aria-labelledby="titulo-servidores-censo">
        <div class="folha-cabecalho"><div><h2 id="titulo-servidores-censo">Prontidão dos servidores</h2><p>{{ count(array_filter($rows, fn ($linha) => $linha['pronto'])) }} de {{ count($rows) }} sem pendências impeditivas locais.</p></div></div>
        @if ($rows === [])
            <div class="folha-corpo"><p>Nenhum gestor ou profissional em sala encontrado.</p></div>
        @else
            <div class="rolagem">
                <table class="tabela censo-tabela-turmas">
                    <thead><tr><th scope="col">Servidor</th><th scope="col">Situação e pendências</th></tr></thead>
                    <tbody>
                        @foreach ($rows as $linha)
                            <tr>
                                <th scope="row">
                                    <a href="{{ route('school.staff.census.edit', [...$parametros, 'servidor' => $linha['servidor']->id]) }}">{{ $linha['servidor']->full_name }}</a>
                                </th>
                                <td>
                                    <strong>{{ $linha['pronto'] ? 'Pronto (conferência local)' : 'Pendente' }}</strong>
                                    <ul class="censo-pendencias">
                                        @foreach ([$linha['r30'], $linha['r40']] as $registro)
                                            @if ($registro)
                                                @foreach ($registro->pendencias as $p)
                                                    <li>{{ $p->recusa() ? 'Pendência' : 'Aviso' }} {{ $p->registro }}{{ $p->campo ? '/'.$p->campo : '' }}: {{ $p->mensagem }}</li>
                                                @endforeach
                                            @endif
                                        @endforeach
                                        @foreach ($linha['r50'] as $item)
                                            <li>
                                                <a href="{{ route('school.classes.teachers.census.edit', [...$parametros, 'turma' => $item['atribuicao']->school_class_id, 'atribuicao' => $item['atribuicao']->id]) }}">Conferir atribuição (registro 50)</a>
                                                <ul>
                                                    @foreach ($item['registro']->pendencias as $p)
                                                        <li>{{ $p->recusa() ? 'Pendência' : 'Aviso' }} {{ $p->registro }}/{{ $p->campo }}: {{ $p->mensagem }}</li>
                                                    @endforeach
                                                </ul>
                                            </li>
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
