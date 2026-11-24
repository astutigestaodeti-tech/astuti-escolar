@extends('layouts.municipal')

@php
    use App\Educacenso\RetornoDoEducacenso;
    use App\Models\Tenant\InepIdentifier;
@endphp

@section('title', 'Conferir retorno do Educacenso')
@section('body-class', 'pagina-censo-turmas')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/censo-turmas.css') }}?v={{ filemtime(public_path('css/censo-turmas.css')) }}">
@endpush

@section('trilha')
    <a href="{{ route('census.readiness.index', ['municipio' => $municipality->slug]) }}">Conferência do Censo</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('census.return.create', ['municipio' => $municipality->slug]) }}">Retorno do Educacenso</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Conferir</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Censo Escolar {{ $retorno->ano }}</span>
            <h1>Conferir retorno do Educacenso</h1>
            <p>Nada foi gravado ainda. Confira e confirme no fim da página.</p>
        </div>
        <a class="botao secundario" href="{{ route('census.return.create', ['municipio' => $municipality->slug]) }}">Enviar outro arquivo</a>
    </div>

    @if ($retorno->erros !== [])
        <div class="erro" role="alert">
            <x-icon name="alerta" :size="18" />
            <div><strong>O arquivo não está no leiaute do Educacenso {{ $retorno->ano }}. Nada será gravado.</strong><ul>@foreach ($retorno->erros as $mensagem)<li>{{ $mensagem }}</li>@endforeach</ul></div>
        </div>
    @else
        <section class="folha" aria-labelledby="titulo-resumo">
            <div class="folha-cabecalho"><div><h2 id="titulo-resumo">Resumo</h2><p>{{ $retorno->escolas }} escola(s) da rede no arquivo.@if ($retorno->escolasDeFora !== []) {{ count($retorno->escolasDeFora) }} escola(s) de fora da rede foram ignoradas.@endif</p></div></div>
            <div class="folha-corpo">
                <dl class="censo-lote">
                    @foreach (RetornoDoEducacenso::SITUACOES as $situacao => $rotulo)
                        <div><dt class="texto-suave">{{ $rotulo }}</dt><dd><strong>{{ $contagem[$situacao] }}</strong></dd></div>
                    @endforeach
                </dl>
                @if ($temPlano)
                    <p class="texto-ajuda">Serão gravados:
                        @foreach (InepIdentifier::KINDS as $tipo => $rotulo)
                            @if ($porTipo->get($tipo)){{ $rotulo }}: {{ $porTipo->get($tipo) }}. @endif
                        @endforeach
                    </p>
                @endif
            </div>
        </section>

        @if ($totalProblemas > 0)
            <section class="folha censo-secao" aria-labelledby="titulo-problemas">
                <div class="folha-cabecalho"><div><h2 id="titulo-problemas">Não serão gravados</h2><p>Corrija o cadastro e envie o arquivo de novo, ou confira com a escola. Código já gravado nunca é trocado pela importação.@if ($totalProblemas > $problemas->count()) Mostrando {{ $problemas->count() }} de {{ $totalProblemas }}.@endif</p></div></div>
                <div class="rolagem">
                    <table class="tabela">
                        <thead><tr><th scope="col">Escola</th><th scope="col">Cadastro</th><th scope="col">Código no arquivo</th><th scope="col">Motivo</th></tr></thead>
                        <tbody>
                            @foreach ($problemas as $item)
                                <tr>
                                    <td>{{ $item['escola'] }}</td>
                                    <td>{{ $item['descricao'] }}</td>
                                    <td>{{ $item['codigo'] }}</td>
                                    <td>{{ $item['motivo'] ?? RetornoDoEducacenso::SITUACOES[$item['situacao']] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section class="folha censo-secao" aria-labelledby="titulo-confirmar">
            <div class="folha-cabecalho"><div><h2 id="titulo-confirmar">Confirmar</h2><p>A auditoria registra a soma SHA-256 do arquivo e as quantidades, nunca nomes ou códigos.</p></div></div>
            <div class="folha-corpo">
                @if ($temPlano)
                    <form method="POST" action="{{ route('census.return.store', ['municipio' => $municipality->slug]) }}">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">
                        <button class="botao" type="submit">Gravar {{ $contagem[RetornoDoEducacenso::NOVO] }} código(s)</button>
                    </form>
                @else
                    <p class="texto-suave">Não há código novo para gravar neste arquivo.</p>
                @endif
            </div>
        </section>
    @endif
@endsection
