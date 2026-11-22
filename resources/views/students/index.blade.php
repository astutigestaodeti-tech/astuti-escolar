@extends('layouts.municipal')

@section('title', 'Alunos de '.$school->name)

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('schools.show', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Alunos</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Secretaria escolar</span>
            <h1>Alunos de {{ $school->name }}</h1>
            <p>Consulta de alunos com vínculo ativo nesta unidade. A lista respeita as permissões da sua conta.</p>
        </div>
        <div class="acoes-titulo">
            <span class="marcador"><x-icon name="alunos" :size="16" /> {{ $students->total() }} {{ $students->total() === 1 ? 'aluno' : 'alunos' }}</span>
            @if ($canManage)
                <a class="botao" href="{{ route('school.students.create', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}"><x-icon name="alunos" :size="16" /> Novo aluno</a>
            @endif
        </div>
    </div>

    @if (session('status'))
        <div class="status-sucesso" role="status"><x-icon name="info" :size="18" /> {{ session('status') }}</div>
    @endif

    <section class="folha" aria-labelledby="titulo-lista-alunos">
        <div class="folha-cabecalho">
            <div>
                <h2 id="titulo-lista-alunos">Alunos com vínculo ativo</h2>
                <p>Procure pelo nome do aluno ou do responsável, pelo RA ou pelo CPF, com ou sem pontuação.</p>
            </div>
        </div>

        <form class="busca" method="GET" action="{{ route('school.students.index', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}" role="search">
            <div class="campo">
                <x-icon name="busca" :size="18" />
                <label class="sr-only" for="busca-aluno">Nome, responsável, RA ou CPF</label>
                <input id="busca-aluno" type="search" name="busca" value="{{ $search }}" placeholder="Nome do aluno ou do responsável, RA ou CPF" maxlength="120" autocomplete="off">
            </div>
            @if ($transport !== '')
                <input type="hidden" name="transporte" value="{{ $transport }}">
            @endif
            <button class="botao" type="submit"><x-icon name="busca" :size="16" /> Pesquisar</button>
        </form>

        @php
            $parametrosLista = ['municipio' => $municipality->slug, 'escola' => $school->id];
            $contagemTransporte = [
                'usa' => $transportCounts['active'] ?? 0,
                'nao_solicitado' => $transportCounts['not_requested'] ?? 0,
                'cancelado' => $transportCounts['cancelled'] ?? 0,
                'sem_informacao' => $transportCounts['sem_informacao'] ?? 0,
            ];
        @endphp
        <nav class="filtro-transporte" aria-label="Filtrar por transporte escolar">
            <span class="filtro-transporte-rotulo">Transporte escolar:</span>
            <a href="{{ route('school.students.index', array_filter([...$parametrosLista, 'busca' => $search ?: null])) }}" @class(['filtro-chip', 'ativo' => $transport === '']) @if ($transport === '') aria-current="true" @endif>Todos</a>
            @foreach ($transportFilters as $chave => $filtro)
                <a href="{{ route('school.students.index', array_filter([...$parametrosLista, 'busca' => $search ?: null, 'transporte' => $chave])) }}" @class(['filtro-chip', 'ativo' => $transport === $chave]) @if ($transport === $chave) aria-current="true" @endif>{{ $filtro['label'] }} <span class="numero">{{ $contagemTransporte[$chave] }}</span></a>
            @endforeach
        </nav>

        @if ($students->isEmpty())
            <div class="vazio">Nenhum aluno ativo {{ $search !== '' || $transport !== '' ? 'encontrado com esses filtros.' : 'cadastrado nesta escola.' }}</div>
        @else
            <div class="rolagem">
                <table class="tabela">
                    <thead><tr><th scope="col">Aluno</th><th scope="col">Data de nascimento</th><th scope="col">Transporte escolar</th></tr></thead>
                    <tbody>
                    @foreach ($students as $student)
                        <tr>
                            <td><a href="{{ route('school.students.show', ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id]) }}" aria-label="Abrir ficha de {{ $student->full_name }}"><strong>{{ $student->full_name }}</strong></a>
                                @if ($student->ra)<small class="lista-ra numero">RA {{ $student->ra }}</small>@endif</td>
                            <td class="numero">{{ $student->birth_date?->format('d/m/Y') ?? 'Não informada' }}</td>
                            @php($situacaoTransporte = $student->enrollments->first()?->transport_status)
                            <td>@include('students.partials.transporte', ['situacao' => $situacaoTransporte])</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($students->hasPages())
            <nav class="paginacao" aria-label="Páginas de alunos">
                <span class="paginacao-resumo">Página {{ $students->currentPage() }} de {{ $students->lastPage() }}</span>
                <div class="paginacao-acoes">
                    @if ($students->onFirstPage())
                        <span class="paginacao-inativa">Anterior</span>
                    @else
                        <a class="botao secundario" rel="prev" href="{{ $students->previousPageUrl() }}"><x-icon name="voltar" :size="16" /> Anterior</a>
                    @endif
                    @if ($students->hasMorePages())
                        <a class="botao secundario" rel="next" href="{{ $students->nextPageUrl() }}">Próxima <x-icon name="seta" :size="16" /></a>
                    @else
                        <span class="paginacao-inativa">Próxima</span>
                    @endif
                </div>
            </nav>
        @endif
    </section>

    @if ($canManage)
        <div class="nota"><x-icon name="escudo" /><div><strong>Alterações protegidas</strong><p>Incluir, importar e alterar alunos exige o segundo fator confirmado, e cada alteração fica no histórico. Para trazer vários alunos de uma vez, use "Importar alunos" na página da escola.</p></div></div>
    @else
        <div class="nota"><x-icon name="escudo" /><div><strong>Somente consulta</strong><p>Sua conta pode consultar os alunos desta escola, mas não incluir nem alterar cadastros. Não utilize dados reais de alunos neste ambiente de desenvolvimento.</p></div></div>
    @endif
@endsection
