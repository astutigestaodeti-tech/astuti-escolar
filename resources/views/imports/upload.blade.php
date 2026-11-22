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
    <div class="folha-titulo">
        <div>
            <span class="contexto">Importação assistida</span>
            <h1>Importar alunos de {{ $school->name }}</h1>
            <p>Envie a planilha, o PDF ou a foto da lista desta escola, revise a prévia e confirme os alunos antes de gravar.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="nota"><x-icon name="info" /><div><strong>{{ session('status') }}</strong></div></div>
    @endif

    @if ($errors->any())
        <div class="nota nota-erro" role="alert"><x-icon name="alerta" /><div><strong>Não foi possível concluir.</strong><ul>@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div></div>
    @endif

    <section class="folha" aria-labelledby="importar-titulo">
        <div class="folha-cabecalho"><div><h2 id="importar-titulo">Lista de alunos da escola</h2><p>Planilha .xlsx, PDF (digital ou escaneado) ou foto JPG/PNG, até 10 MB. Use somente dados fictícios neste ambiente.</p></div></div>
        <form method="post" enctype="multipart/form-data" action="{{ route('school.imports.students.preview', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}" class="envio-form">
            @csrf
            <label class="rotulo" for="school_year_id">Ano letivo <span aria-hidden="true">*</span></label>
            <select class="entrada" id="school_year_id" name="school_year_id" required>
                <option value="">Selecione o ano letivo</option>
                @foreach ($years as $year)
                    <option value="{{ $year->id }}" @selected(old('school_year_id') === $year->id)>{{ $year->year }}</option>
                @endforeach
            </select>
            <label class="rotulo" for="arquivo">Arquivo da lista <span aria-hidden="true">*</span></label>
            <input class="entrada envio-arquivo" id="arquivo" name="arquivo" type="file" accept=".xlsx,.pdf,.jpg,.jpeg,.png,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/pdf,image/jpeg,image/png" required>
            <button class="botao" type="submit">Ler arquivo e conferir</button>
        </form>
    </section>

    <div class="nota"><x-icon name="info" /><div><strong>Conferência obrigatória</strong><p>O envio apenas mostra uma prévia. Nenhum aluno é gravado até a confirmação com segundo fator. O arquivo não é guardado no servidor; transporte, endereço e observações permanecem pendentes.</p></div></div>
    <div class="nota"><x-icon name="info" /><div><strong>PDF e foto</strong><p>A leitura é feita no próprio servidor, sem enviar o documento para fora. Nomes lidos com pouca certeza aparecem para você conferir com o papel antes de gravar. Para uma foto boa: lista inteira no quadro, sem sombra e sem inclinar muito.</p></div></div>
@endsection
