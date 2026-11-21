@extends('layouts.municipal')

@section('title', 'Ficha de '.$student->full_name)

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('schools.show', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('school.students.index', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">Alunos</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Ficha do aluno</span>
@endsection

@section('content')
    <a class="voltar" href="{{ route('school.students.index', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}"><x-icon name="voltar" :size="18" /> Voltar à lista de alunos</a>

    <div class="folha-titulo">
        <div>
            <span class="contexto">Consulta individual</span>
            <h1>Ficha do aluno</h1>
            <p>Informações de consulta da unidade {{ $school->name }}.</p>
        </div>
        <span class="etiqueta ok">Vínculo ativo</span>
    </div>

    <div class="ficha-escola">
        <section class="folha" aria-labelledby="titulo-dados-aluno">
            <div class="folha-cabecalho"><div><h2 id="titulo-dados-aluno">Dados do aluno</h2><p>Informações disponíveis no cadastro municipal.</p></div></div>
            <dl>
                <div><dt>Nome completo</dt><dd>{{ $student->full_name }}</dd></div>
                <div><dt>Ano de nascimento</dt><dd class="numero">{{ $student->birth_date?->format('Y') ?? 'Não informado' }}</dd></div>
                <div><dt>Escola</dt><dd>{{ $school->name }}</dd></div>
            </dl>
        </section>

        <section class="folha cartao" aria-labelledby="titulo-vinculos">
            <span class="acao-icone"><x-icon name="turmas" /></span>
            <h2 id="titulo-vinculos">Vínculos ativos nesta escola</h2>
            <p>Anos letivos associados à unidade autorizada.</p>
            <ul class="documentos">
                @foreach ($enrollments as $enrollment)
                    <li><span class="etiqueta neutra numero">Ano letivo {{ $enrollment->schoolYear?->year ?? 'Não informado' }}</span></li>
                @endforeach
            </ul>
        </section>
    </div>

    <div class="nota"><x-icon name="escudo" /><div><strong>Somente consulta, por enquanto</strong><p>A inclusão e alteração de dados escolares continuam indisponíveis neste ambiente de desenvolvimento. Utilize apenas informações fictícias.</p></div></div>
@endsection
