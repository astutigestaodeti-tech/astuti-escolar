@extends('layouts.municipal')

@php
    $parametros = ['municipio' => $municipality->slug, 'escola' => $school->id];
    $linhas = collect($rows)->map(function ($linha) {
        $pendencias = array_values(array_filter([...$linha['r30']->pendencias, ...$linha['r60']->pendencias], fn ($p) => $p->recusa()));
        $avisos = array_values(array_filter([...$linha['r30']->pendencias, ...$linha['r60']->pendencias], fn ($p) => ! $p->recusa()));

        return [...$linha, 'pendencias' => $pendencias, 'avisos' => $avisos];
    });
    $prontos = $linhas->filter(fn ($l) => $l['pendencias'] === [])->count();
    $visiveis = $filter === 'pendentes' ? $linhas->filter(fn ($l) => $l['pendencias'] !== []) : $linhas;
@endphp

@section('title', 'Alunos no Censo - '.$school->name)
@section('body-class', 'pagina-censo-turmas')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/censo-turmas.css') }}?v={{ filemtime(public_path('css/censo-turmas.css')) }}">
@endpush

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('schools.show', $parametros) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Alunos no Censo</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Censo Escolar</span>
            <h1>Alunos no Censo</h1>
            <p>Cada aluno no formato do Educacenso (registros 30 e 60), com o que falta para o envio. Conferido com o leiaute oficial do Inep.</p>
        </div>
        <div class="acoes-titulo">
            @if ($year)<span class="marcador">Ano letivo {{ $year->year }}</span>@endif
            <span class="marcador"><x-icon name="conferir" :size="16" /> {{ $prontos }} de {{ $linhas->count() }} prontos</span>
        </div>
    </div>

    @if ($linhas->isEmpty())
        <div class="folha vazio">Nenhum aluno com matrícula ativa no ano letivo atual.</div>
    @else
        <section class="folha" aria-labelledby="titulo-alunos-censo">
            <div class="folha-cabecalho">
                <div><h2 id="titulo-alunos-censo">Alunos com matrícula ativa</h2><p>Cada envio ao Educacenso substitui a escola inteira: um aluno com pendência impede o envio da escola.</p></div>
                @if ($filter === 'pendentes')
                    <a class="botao secundario" href="{{ route('school.census.students.index', $parametros) }}">Mostrar todos</a>
                @else
                    <a class="botao secundario" href="{{ route('school.census.students.index', [...$parametros, 'situacao' => 'pendentes']) }}">Só os com pendência</a>
                @endif
            </div>
            <div class="rolagem">
                <table class="tabela censo-tabela-turmas">
                    <thead><tr><th scope="col">Aluno</th><th scope="col" class="censo-col-situacao">Situação</th><th scope="col">Corrigir em</th></tr></thead>
                    <tbody>
                        @foreach ($visiveis as $linha)
                            @php
                                $m = $linha['matricula'];
                                $aluno = $m->student;
                                $pendencias = $linha['pendencias'];
                                $ficha = route('school.students.show', [...$parametros, 'aluno' => $aluno->id]);
                            @endphp
                            <tr>
                                <th scope="row"><a href="{{ $ficha }}">{{ $aluno->full_name }}</a></th>
                                <td>
                                    @if ($pendencias === [])
                                        <span class="etiqueta ok">Pronto</span>
                                    @else
                                        <span class="etiqueta conferir">{{ count($pendencias) }} {{ count($pendencias) === 1 ? 'pendência' : 'pendências' }}</span>
                                        <ul class="censo-pendencias">
                                            @foreach (array_slice($pendencias, 0, 4) as $pendencia)
                                                <li>{{ $pendencia->mensagem }}</li>
                                            @endforeach
                                            @if (count($pendencias) > 4)<li>e mais {{ count($pendencias) - 4 }}.</li>@endif
                                        </ul>
                                    @endif
                                    @foreach ($linha['avisos'] as $aviso)
                                        <p class="texto-ajuda">{{ $aviso->mensagem }}</p>
                                    @endforeach
                                </td>
                                <td>
                                    <a href="{{ route('school.students.census.edit', [...$parametros, 'aluno' => $aluno->id]) }}">Dados do Censo</a><br>
                                    <a href="{{ route('school.students.edit', [...$parametros, 'aluno' => $aluno->id]) }}">Cadastro</a>
                                    @if ($canManageHealth)
                                        <br><a href="{{ route('school.students.special-education.edit', [...$parametros, 'aluno' => $aluno->id]) }}">Educação especial</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
