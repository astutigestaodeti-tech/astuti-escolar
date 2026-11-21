@extends('layouts.municipal')

@section('title', 'Importar alunos - '.$school->name)

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('schools.show', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Importar alunos</span>
@endsection

@section('content')
    <a class="voltar" href="{{ route('schools.show', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">Voltar à tela anterior</a>
    <div class="folha-titulo">
        <div>
            <span class="contexto">Importação assistida</span>
            <h1>Importar alunos de {{ $school->name }}</h1>
            <p>Envie a planilha desta escola, revise a prévia e confirme os alunos antes de gravar.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="nota"><x-icon name="info" /><div><strong>{{ session('status') }}</strong></div></div>
    @endif

    @if ($errors->any())
        <div class="nota" role="alert"><x-icon name="info" /><div><strong>Não foi possível concluir.</strong><ul>@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div></div>
    @endif

    <section class="folha" aria-labelledby="importar-titulo">
        <div class="folha-cabecalho"><div><h2 id="importar-titulo">Planilha da escola</h2><p>Arquivo .xlsx até 5 MB. Use somente dados fictícios neste ambiente.</p></div></div>
        <form method="post" enctype="multipart/form-data" action="{{ route('school.imports.students.preview', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}" style="padding:20px;display:grid;gap:18px;max-width:720px">
            @csrf
            <label for="school_year_id">Ano letivo <span aria-hidden="true">*</span></label>
            <select id="school_year_id" name="school_year_id" required>
                <option value="">Selecione o ano letivo</option>
                @foreach ($years as $year)
                    <option value="{{ $year->id }}" @selected(old('school_year_id') === $year->id)>{{ $year->year }}</option>
                @endforeach
            </select>
            <label for="arquivo">Planilha dos alunos <span aria-hidden="true">*</span></label>
            <input id="arquivo" name="arquivo" type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
            <button class="botao" type="submit">Ler planilha e conferir</button>
        </form>
    </section>

    <div class="nota"><x-icon name="info" /><div><strong>Conferência obrigatória</strong><p>O envio apenas mostra uma prévia. Nenhum aluno é gravado até a confirmação com segundo fator. O arquivo não é guardado no servidor; transporte, endereço e observações permanecem pendentes.</p></div></div>
@endsection
