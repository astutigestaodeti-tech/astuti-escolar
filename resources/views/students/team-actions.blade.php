@extends('layouts.municipal')

@section('title', 'Acompanhamento pela direção')

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('schools.show', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}#hoje-na-escola">Hoje na escola</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Acompanhamento pela direção</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div><span class="contexto">{{ $school->name }}</span>
            <h1>{{ $teamFilter === 'guidance' ? 'Devolutivas recentes' : 'Pedidos aguardando atendimento' }}</h1>
            <p>Consulta exclusiva da direção, com segundo fator. Abra a ficha para acompanhamento escolar.</p>
        </div>
        <a class="botao secundario" href="{{ route('schools.show', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}#hoje-na-escola">Voltar</a>
    </div>
    <section class="folha">
        <div class="folha-cabecalho"><h2>Alunos para acompanhamento</h2></div>
        <div class="folha-corpo">
            @if ($students->isEmpty())
                <p>Tudo em dia</p>
            @else
                <ul class="hoje-lista">
                    @foreach ($students as $student)
                        <li><a href="{{ route('school.students.show', ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id]) }}">{{ $student->full_name }}</a></li>
                    @endforeach
                </ul>
            @endif
            @if ($students->hasPages())
                <nav class="paginacao" aria-label="Páginas do acompanhamento">
                    <span class="paginacao-resumo">Página {{ $students->currentPage() }} de {{ $students->lastPage() }}</span>
                    <div class="paginacao-acoes">
                        @if ($students->previousPageUrl())<a class="botao secundario" href="{{ $students->previousPageUrl() }}">Anterior</a>@endif
                        @if ($students->nextPageUrl())<a class="botao secundario" href="{{ $students->nextPageUrl() }}">Próxima</a>@endif
                    </div>
                </nav>
            @endif
        </div>
    </section>
@endsection
