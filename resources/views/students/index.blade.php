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
                <p>Localize um aluno pelo nome para conferir sua presença na unidade.</p>
            </div>
        </div>

        <form class="busca" method="GET" action="{{ route('school.students.index', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}" role="search">
            <div class="campo">
                <x-icon name="busca" :size="18" />
                <label class="sr-only" for="busca-aluno">Nome do aluno</label>
                <input id="busca-aluno" type="search" name="busca" value="{{ $search }}" placeholder="Buscar aluno pelo nome" maxlength="120" autocomplete="off">
            </div>
            <button class="botao" type="submit"><x-icon name="busca" :size="16" /> Pesquisar</button>
        </form>

        @if ($students->isEmpty())
            <div class="vazio">Nenhum aluno ativo {{ $search !== '' ? 'encontrado para esta pesquisa.' : 'cadastrado nesta escola.' }}</div>
        @else
            <div class="rolagem">
                <table class="tabela">
                    <thead><tr><th scope="col">Aluno</th><th scope="col">Ano de nascimento</th></tr></thead>
                    <tbody>
                    @foreach ($students as $student)
                        <tr>
                            <td><a href="{{ route('school.students.show', ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id]) }}" aria-label="Abrir ficha de {{ $student->full_name }}"><strong>{{ $student->full_name }}</strong></a></td>
                            <td class="numero">{{ $student->birth_date?->format('Y') ?? 'Não informado' }}</td>
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
        <div class="nota"><x-icon name="escudo" /><div><strong>Cadastro manual liberado para sua conta</strong><p>Incluir e alterar alunos exige o segundo fator confirmado, e cada alteração fica no histórico. A importação de planilhas e documentos virá em etapa própria, com conferência humana. Não utilize dados reais de alunos neste ambiente de desenvolvimento.</p></div></div>
    @else
        <div class="nota"><x-icon name="escudo" /><div><strong>Somente consulta</strong><p>Sua conta pode consultar os alunos desta escola, mas não incluir nem alterar cadastros. Não utilize dados reais de alunos neste ambiente de desenvolvimento.</p></div></div>
    @endif
@endsection
