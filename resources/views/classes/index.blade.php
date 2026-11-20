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
    <a class="voltar" href="{{ route('schools.show', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}"><x-icon name="voltar" :size="18" /> Voltar à escola</a>

    <div class="folha-titulo">
        <div>
            <span class="contexto">Organização acadêmica</span>
            <h1>Turmas de {{ $school->name }}</h1>
            <p>Somente as turmas ativas desta unidade.</p>
        </div>
        <span class="marcador"><x-icon name="turmas" :size="16" /> {{ $classes->count() }} {{ $classes->count() === 1 ? 'turma' : 'turmas' }}</span>
    </div>

    <section class="folha" aria-labelledby="titulo-tabela">
        <div class="folha-cabecalho"><div><h2 id="titulo-tabela">Turmas ativas</h2><p>Etapa, modalidade, turno e vagas de cada turma.</p></div></div>
        @if ($classes->isEmpty())
            <div class="vazio">Nenhuma turma ativa cadastrada nesta escola. As turmas aparecem aqui assim que a Secretaria abrir o ano letivo.</div>
        @else
            <div class="rolagem">
                <table class="tabela">
                    <thead><tr><th scope="col">Turma</th><th scope="col">Etapa</th><th scope="col">Modalidade</th><th scope="col">Turno</th><th scope="col">Vagas</th><th scope="col">Ano letivo</th></tr></thead>
                    <tbody>
                    @foreach ($classes as $class)
                        <tr>
                            <td><strong>{{ $class->name }}</strong></td>
                            <td>{{ $class->stage }}</td>
                            <td>{{ $class->modality }}</td>
                            <td>{{ $class->shift }}</td>
                            <td class="numero">{{ $class->capacity !== null ? $class->capacity.' vagas' : 'Não informada' }}</td>
                            <td><span class="etiqueta neutra numero">{{ $class->schoolYear->year }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <div class="nota"><x-icon name="escudo" /><div><strong>Somente consulta, por enquanto</strong><p>Alterações em anos e turmas permanecem indisponíveis até a ativação da autenticação reforçada e da auditoria.</p></div></div>
@endsection
