@extends('layouts.municipal')

@section('title', 'Importar transporte - '.$school->name)

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('schools.show', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Transporte</span>
@endsection

@section('content')
    <div class="folha-titulo"><div>
        <span class="contexto">Importação com conferência</span>
        <h1>Transporte escolar de {{ $school->name }}</h1>
        <p>Atualize a situação do transporte das matrículas desta escola a partir da lista da Secretaria ou da própria escola. Nada é gravado antes da conferência.</p>
    </div></div>

    @if (session('status'))
        <div class="nota"><x-icon name="info" /><div><strong>{{ session('status') }}</strong>
            <p><a href="{{ route('school.students.index', ['municipio' => $municipality->slug, 'escola' => $school->id, 'transporte' => 'usa']) }}">Ver alunos que usam transporte</a></p></div></div>
    @endif

    @if ($errors->any())
        <div class="nota nota-erro" role="alert"><x-icon name="alerta" /><div><strong>O lote não foi aplicado.</strong><ul>@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div></div>
    @endif

    <section class="folha" aria-labelledby="transporte-titulo">
        <div class="folha-cabecalho"><div><h2 id="transporte-titulo">Lista de transporte</h2><p>Arquivo CSV de até 2 MB, separado por ponto e vírgula ou vírgula. Pode ser a lista da Secretaria com todas as escolas: só os alunos desta escola serão usados.</p></div></div>
        <form method="post" enctype="multipart/form-data" action="{{ route('school.imports.transport.preview', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}" class="envio-form">
            @csrf
            <label class="rotulo" for="school_year_id">Ano letivo</label>
            <select class="entrada" id="school_year_id" name="school_year_id" required>
                <option value="">Escolha o ano</option>
                @foreach ($years as $year)
                    <option value="{{ $year->id }}" @selected(old('school_year_id') === $year->id)>{{ $year->year }}</option>
                @endforeach
            </select>
            <label class="rotulo" for="arquivo">Arquivo CSV</label>
            <input class="entrada envio-arquivo" id="arquivo" name="arquivo" type="file" accept=".csv,text/csv,text/plain" required>
            <button class="botao" type="submit">Ler lista e conferir</button>
        </form>
    </section>

    <div class="nota"><x-icon name="info" /><div><strong>O que o arquivo precisa ter</strong>
        <p>Uma coluna "Situação" (SIM, NÃO, ATIVO ou CANCELADO) e uma forma de achar o aluno: a coluna "RA", ou as colunas "Nome" e "Data de nascimento". A coluna Escola é aceita e as demais (linha, ponto de embarque, adaptado) são ignoradas.</p>
        <p>Alunos achados pelo nome aparecem para você conferir antes de gravar. Alunos de outras escolas ou não encontrados ficam de fora, numa lista à parte. Nenhum aluno é criado, e um transporte CANCELADO nunca é reativado pelo arquivo.</p>
    </div></div>
@endsection
