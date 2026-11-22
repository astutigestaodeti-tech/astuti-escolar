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
        <p>Atualize somente a situação do transporte de matrículas existentes, após conferir cada mudança.</p>
    </div></div>

    @if (session('status'))
        <div class="nota"><x-icon name="info" /><div><strong>{{ session('status') }}</strong></div></div>
    @endif

    @if ($errors->any())
        <div class="nota" role="alert"><x-icon name="info" /><div><strong>O lote não foi aplicado.</strong><ul>@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div></div>
    @endif

    <section class="folha" aria-labelledby="transporte-titulo">
        <div class="folha-cabecalho"><div><h2 id="transporte-titulo">Lista de transporte</h2><p>CSV de até 2 MB, UTF-8 ou Windows-1252, separado por vírgula ou ponto e vírgula.</p></div></div>
        <form method="post" enctype="multipart/form-data" action="{{ route('school.imports.transport.preview', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}" style="padding:20px;display:grid;gap:18px;max-width:720px">
            @csrf
            <label for="school_year_id">Ano letivo</label>
            <select id="school_year_id" name="school_year_id" required>
                <option value="">Escolha o ano</option>
                @foreach ($years as $year)
                    <option value="{{ $year->id }}" @selected(old('school_year_id') === $year->id)>{{ $year->year }}</option>
                @endforeach
            </select>
            <label for="arquivo">Arquivo CSV</label>
            <input id="arquivo" name="arquivo" type="file" accept=".csv,text/csv,text/plain" required>
            <button class="botao" type="submit">Ler lista e conferir</button>
        </form>
    </section>

    <div class="nota"><x-icon name="info" /><div><strong>Regras de segurança</strong><p>O arquivo deve ter as colunas RA e Situação (SIM, NÃO, ATIVO ou CANCELADO). Nome é opcional. Nenhum aluno será criado; RAs fora desta escola e ano impedem a importação. Um transporte CANCELADO nunca será reativado por CSV. Os dados não são gravados antes da confirmação com segundo fator.</p></div></div>
@endsection
