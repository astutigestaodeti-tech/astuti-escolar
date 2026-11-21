@extends('layouts.municipal')

@section('title', 'Matrículas de '.$school->name)

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('schools.show', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Matrículas</span>
@endsection

@section('content')
    <a class="voltar" href="{{ route('schools.show', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}"><x-icon name="voltar" :size="18" /> Voltar à escola</a>

    <div class="folha-titulo">
        <div>
            <span class="contexto">Secretaria escolar</span>
            <h1>Matrículas ativas de {{ $school->name }}</h1>
            <p>Relação de vínculos atuais desta escola, com ano letivo e situação. Acesso restrito à sua conta.</p>
        </div>
        <span class="marcador"><x-icon name="alunos" :size="16" /> {{ $enrollments->total() }} {{ $enrollments->total() === 1 ? 'matrícula' : 'matrículas' }}</span>
    </div>

    <section class="folha" aria-labelledby="titulo-matriculas">
        <div class="folha-cabecalho"><div><h2 id="titulo-matriculas">Matrículas ativas</h2><p>Pesquise pelo nome para localizar um vínculo desta unidade.</p></div></div>

        <form class="busca busca-matriculas" method="GET" action="{{ route('school.enrollments.index', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}" role="search">
            <div class="campo">
                <x-icon name="busca" :size="18" />
                <label class="sr-only" for="busca-matricula">Nome do aluno</label>
                <input id="busca-matricula" type="search" name="busca" value="{{ $search }}" placeholder="Buscar matrícula pelo nome do aluno" maxlength="120" autocomplete="off">
            </div>
            <div class="filtro-ano">
                <label class="sr-only" for="ano-letivo-matricula">Ano letivo</label>
                <select id="ano-letivo-matricula" name="ano_letivo">
                    <option value="">Todos os anos</option>
                    @foreach ($availableYears as $year)
                        <option value="{{ $year }}" @selected($selectedYear === $year)>Ano letivo {{ $year }}</option>
                    @endforeach
                </select>
            </div>
            <button class="botao" type="submit"><x-icon name="busca" :size="16" /> Pesquisar</button>
        </form>

        @if ($errors->has('ano_letivo') || $errors->has('busca'))
            <div class="erro erro-matriculas" role="alert">
                @error('ano_letivo')<p>{{ $message }}</p>@enderror
                @error('busca')<p>{{ $message }}</p>@enderror
            </div>
        @endif

        @if ($enrollments->isEmpty())
            <div class="vazio">Nenhuma matrícula ativa {{ $search !== '' || $selectedYear !== null ? 'encontrada para os filtros informados.' : 'cadastrada nesta escola.' }}</div>
        @else
            <div class="rolagem">
                <table class="tabela">
                    <thead><tr><th scope="col">Aluno</th><th scope="col">Ano letivo</th><th scope="col">Situação</th><th scope="col">Início</th></tr></thead>
                    <tbody>
                    @foreach ($enrollments as $enrollment)
                        <tr>
                            <td><a href="{{ route('school.students.show', ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $enrollment->student_id]) }}" aria-label="Abrir ficha de {{ $enrollment->student->full_name }}"><strong>{{ $enrollment->student->full_name }}</strong></a></td>
                            <td><span class="etiqueta neutra numero">Ano letivo {{ $enrollment->schoolYear?->year ?? 'Não informado' }}</span></td>
                            <td><span class="etiqueta ok">Ativa</span></td>
                            <td class="numero">{{ $enrollment->started_at?->format('d/m/Y') ?? 'Não informada' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($enrollments->hasPages())
            <nav class="paginacao" aria-label="Páginas de matrículas">
                <span class="paginacao-resumo">Página {{ $enrollments->currentPage() }} de {{ $enrollments->lastPage() }}</span>
                <div class="paginacao-acoes">
                    @if ($enrollments->onFirstPage())
                        <span class="paginacao-inativa">Anterior</span>
                    @else
                        <a class="botao secundario" rel="prev" href="{{ $enrollments->previousPageUrl() }}"><x-icon name="voltar" :size="16" /> Anterior</a>
                    @endif
                    @if ($enrollments->hasMorePages())
                        <a class="botao secundario" rel="next" href="{{ $enrollments->nextPageUrl() }}">Próxima <x-icon name="seta" :size="16" /></a>
                    @else
                        <span class="paginacao-inativa">Próxima</span>
                    @endif
                </div>
            </nav>
        @endif
    </section>

    <div class="nota"><x-icon name="escudo" /><div><strong>Somente consulta, por enquanto</strong><p>A inclusão, transferência e alteração de matrículas permanecem indisponíveis neste ambiente de desenvolvimento. Utilize apenas dados fictícios.</p></div></div>
@endsection
