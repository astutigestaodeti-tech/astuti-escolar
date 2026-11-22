@extends('layouts.municipal')

@section('title', 'Turmas de '.$school->name)

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('schools.show', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Turmas</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Organização acadêmica</span>
            <h1>Turmas de {{ $school->name }}</h1>
            <p>Somente as turmas ativas desta unidade.</p>
        </div>
        <div class="acoes-titulo">
            <span class="marcador"><x-icon name="turmas" :size="16" /> {{ $classes->count() }} {{ $classes->count() === 1 ? 'turma' : 'turmas' }}</span>
            @if ($canManage)
                <a class="botao" href="{{ route('school.classes.create', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}"><x-icon name="turmas" :size="16" /> Nova turma</a>
            @endif
        </div>
    </div>

    @if (session('status'))
        <div class="status-sucesso" role="status"><x-icon name="info" :size="18" /> {{ session('status') }}</div>
    @endif

    <section class="folha" aria-labelledby="titulo-tabela">
        <div class="folha-cabecalho"><div><h2 id="titulo-tabela">Turmas ativas</h2><p>Etapa, modalidade, turno e vagas de cada turma.</p></div></div>
        @if ($classes->isEmpty())
            <div class="vazio">Nenhuma turma ativa cadastrada nesta escola. Quem tem permissão de gestão pode usar o botão Nova turma.</div>
        @else
            <div class="rolagem">
                <table class="tabela">
                    <thead><tr><th scope="col">Turma</th><th scope="col">Etapa</th><th scope="col">Modalidade</th><th scope="col">Turno</th><th scope="col">Vagas</th><th scope="col">Ano letivo</th>@if ($canManage)<th scope="col">Ações</th>@endif</tr></thead>
                    <tbody>
                    @foreach ($classes as $class)
                        <tr>
                            <td><a class="turma-link" href="{{ route('school.classes.show', ['municipio' => $municipality->slug, 'escola' => $school->id, 'turma' => $class->id]) }}"><strong>{{ $class->name }}</strong></a></td>
                            <td>{{ $class->stage }}</td>
                            <td>{{ $class->modality }}</td>
                            <td>{{ $class->shift }}</td>
                            <td class="numero">{{ $class->capacity !== null ? $class->capacity.' vagas' : 'Não informada' }}</td>
                            <td><span class="etiqueta neutra numero">{{ $class->schoolYear->year }}</span></td>
                            @if ($canManage)
                                <td><a href="{{ route('school.classes.edit', ['municipio' => $municipality->slug, 'escola' => $school->id, 'turma' => $class->id]) }}">Editar</a></td>
                            @endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @if ($canManage)
        <div class="nota"><x-icon name="escudo" /><div><strong>Cadastro de turmas liberado</strong><p>Para incluir ou editar turmas, confirme o segundo fator. Toda alteração é auditada.</p></div></div>
    @else
        <div class="nota"><x-icon name="escudo" /><div><strong>Somente consulta</strong><p>Sua conta pode consultar turmas desta escola, mas não incluir nem editar.</p></div></div>
    @endif
@endsection
