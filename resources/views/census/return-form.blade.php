@extends('layouts.municipal')

@section('title', 'Retorno do Educacenso')
@section('body-class', 'pagina-censo-turmas')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/censo-turmas.css') }}?v={{ filemtime(public_path('css/censo-turmas.css')) }}">
@endpush

@section('trilha')
    <a href="{{ route('census.readiness.index', ['municipio' => $municipality->slug]) }}">Conferência do Censo</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Retorno do Educacenso</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Censo Escolar {{ $ano }}</span>
            <h1>Retorno do Educacenso</h1>
            <p>Grava os códigos que o Inep devolve: ID Inep de alunos e servidores, código Inep das turmas e código das matrículas. Com eles, o próximo envio não duplica pessoas na base nacional.</p>
        </div>
        <a class="botao secundario" href="{{ route('census.readiness.index', ['municipio' => $municipality->slug]) }}">Voltar à conferência</a>
    </div>

    @if (session('status'))
        <div class="status-sucesso" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="erro" role="alert">
            <x-icon name="alerta" :size="18" />
            <div><strong>Revise o envio</strong><ul>@foreach ($errors->all() as $mensagem)<li>{{ $mensagem }}</li>@endforeach</ul></div>
        </div>
    @endif

    <section class="folha" aria-labelledby="titulo-envio">
        <div class="folha-cabecalho"><div><h2 id="titulo-envio">Arquivo de exportação</h2><p>O .txt ou o .zip baixado no Educacenso, de até 20 MB. Primeiro o Astuti mostra o que vai gravar; nada muda antes da sua confirmação. O arquivo não fica guardado.</p></div></div>
        <div class="folha-corpo">
            <form method="POST" enctype="multipart/form-data" action="{{ route('census.return.preview', ['municipio' => $municipality->slug]) }}">
                @csrf
                <label class="rotulo" for="arquivo">Arquivo do Educacenso</label>
                <input class="entrada envio-arquivo" id="arquivo" name="arquivo" type="file" accept=".txt,.zip,text/plain,application/zip" required>
                <div class="formulario-acoes censo-secao">
                    <p class="texto-ajuda">Ler o arquivo pede o código do segundo fator. Pessoas só são vinculadas quando nome e data de nascimento do arquivo conferem com o cadastro.</p>
                    <button class="botao" type="submit">Ler arquivo e conferir</button>
                </div>
            </form>
        </div>
    </section>

    <section class="folha censo-secao" aria-labelledby="titulo-gravados">
        <div class="folha-cabecalho"><div><h2 id="titulo-gravados">Códigos já gravados</h2><p>Total da rede, de todas as importações.</p></div></div>
        <div class="folha-corpo">
            <dl class="censo-lote">
                @foreach (\App\Models\Tenant\InepIdentifier::KINDS as $tipo => $rotulo)
                    <div><dt class="texto-suave">{{ $rotulo }}</dt><dd><strong>{{ $gravados[$tipo] ?? 0 }}</strong></dd></div>
                @endforeach
            </dl>
        </div>
    </section>
@endsection
