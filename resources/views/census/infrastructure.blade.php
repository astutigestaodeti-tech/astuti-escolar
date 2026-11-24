@extends('layouts.municipal')

@php
    $parametros = ['municipio' => $municipality->slug, 'escola' => $school->id];
    $respostas = old('answers', $answers);
    $respostas = is_array($respostas) ? $respostas : [];
    $valor = fn (int $numero) => is_scalar($respostas[$numero] ?? null) ? (string) $respostas[$numero] : '';
@endphp

@section('title', 'Infraestrutura no Censo - '.$school->name)
@section('body-class', 'pagina-censo-turmas pagina-censo-infraestrutura')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/censo-turmas.css') }}?v={{ filemtime(public_path('css/censo-turmas.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/censo-infraestrutura.css') }}?v={{ filemtime(public_path('css/censo-infraestrutura.css')) }}">
    <script type="module" src="{{ asset('js/censo-infraestrutura.js') }}?v={{ filemtime(public_path('js/censo-infraestrutura.js')) }}"></script>
@endpush

@section('trilha')
    <a href="{{ route('schools.show', $parametros) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('school.census.school.edit', $parametros) }}">Escola no Censo</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Infraestrutura no Censo</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Censo Escolar {{ $ano }}</span>
            <h1>Infraestrutura no Censo</h1>
            <p>Questionário de caracterização e infraestrutura da escola (registro 10). Código Inep: {{ $school->inep_code ?: 'não informado' }}.</p>
        </div>
        <a class="botao secundario" href="{{ route('school.census.school.edit', $parametros) }}">Voltar à escola no Censo</a>
    </div>

    @if (session('status'))
        <div class="status-sucesso" role="status"><x-icon name="info" :size="18" /> {{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="erro" role="alert">
            <x-icon name="alerta" :size="18" />
            <div><strong>Revise o formulário</strong><ul>
                @foreach ($errors->messages() as $chave => $mensagens)
                    @foreach ($mensagens as $mensagem)
                        <li>
                            @if (preg_match('/^answers\.(\d+)$/', $chave, $m))
                                <a href="#campo-{{ $m[1] }}">{{ $mensagem }}</a>
                            @else
                                {{ $mensagem }}
                            @endif
                        </li>
                    @endforeach
                @endforeach
            </ul></div>
        </div>
    @endif
    @if ($copiado)
        <div class="censo-sugestao" role="note">
            <x-icon name="info" :size="18" />
            <p>Copiar do ano anterior: as respostas de {{ $ano - 1 }} foram trazidas como sugestões pelo número do campo. Confira todos os rótulos e respostas, pois o leiaute pode ter mudado. Nada foi gravado ainda.</p>
        </div>
    @endif

    @if (! $registro->aplicavel)
        <section class="folha">
            <div class="folha-corpo"><p>Escola paralisada ou extinta: o Educacenso não recebe o registro 10. Os questionários já gravados permanecem no histórico.</p></div>
        </section>
    @else
        <section class="folha" aria-labelledby="titulo-orientacao">
            <div class="folha-cabecalho"><div><h2 id="titulo-orientacao">Como preencher</h2></div></div>
            <div class="folha-corpo">
                <p>Marque as caixas para responder Sim (ou Oferece). As caixas desmarcadas significam Não, quando o campo se aplica. Nos campos condicionais, deixe vazios os que não se aplicam. Confira todas as respostas antes de salvar.</p>
                <p class="texto-ajuda">Os números ao lado das perguntas são os números de campo do leiaute oficial. Quantidades são totais, sem nomes ou identificação de alunos e servidores.</p>
                <nav aria-label="Seções do questionário" class="infra-indice">
                    @foreach ($blocos as $bloco)
                        <a href="#grupo-{{ $loop->index }}">{{ $bloco['nome'] }}</a>
                    @endforeach
                </nav>
            </div>
        </section>

        <form method="POST" action="{{ route('school.census.infrastructure.update', $parametros) }}" id="infra-form" novalidate>
            @csrf
            @method('PUT')

            @foreach ($blocos as $bloco)
                <section class="folha censo-secao" aria-labelledby="grupo-{{ $loop->index }}">
                    <div class="folha-cabecalho"><div><h2 id="grupo-{{ $loop->index }}">{{ $bloco['nome'] }}</h2></div></div>
                    <div class="folha-corpo">
                        @foreach ($bloco['regras'] ?? [] as $regra)
                            <p class="texto-ajuda">{{ $regra['texto'] }}</p>
                        @endforeach
                        <div class="infra-grade">
                            @foreach ($bloco['campos'] as $numero => $campo)
                                @php
                                    $opcoes = [];
                                    if ($campo->tipo === 'selec' || $numero === 139) {
                                        foreach (explode("\n", (string) $campo->permitidos) as $linha) {
                                            if (preg_match('/^(\d+)\s*-\s*(.+)$/u', trim($linha), $m)) {
                                                $opcoes[$m[1]] = $m[2];
                                            }
                                        }
                                    }
                                    $binario = array_keys($opcoes) === [0, 1];
                                    $somenteSim = array_keys($opcoes) === [1];
                                    $condicional = \App\Educacenso\Registro10::CONDICIONAIS_BINARIOS[$numero] ?? null;
                                    $codigo = in_array($numero, range(11, 16), true);
                                    $chave = 'answers.'.$numero;
                                    $nome = 'answers['.$numero.']';
                                @endphp
                                <div class="formulario-campo infra-campo"
                                    @if ($condicional) data-condicional="{{ $condicional[0] }}" data-esperado="{{ $condicional[1] }}" @endif>
                                    @if ($binario || $somenteSim)
                                        <input type="hidden" name="{{ $nome }}" value="{{ $somenteSim ? '' : '0' }}" data-infra-fallback>
                                        <label class="infra-checkbox" for="campo-{{ $numero }}">
                                            <input type="checkbox" name="{{ $nome }}" id="campo-{{ $numero }}" value="1" @checked($valor($numero) === '1') @if ($errors->has($chave)) aria-invalid="true" aria-describedby="erro-{{ $numero }}" @endif>
                                            <span>{{ $numero }}. {{ $campo->nome }} <small>({{ $opcoes[1] }})</small></span>
                                        </label>
                                    @else
                                        <label class="rotulo" for="campo-{{ $numero }}">{{ $numero }}. {{ $campo->nome }}</label>
                                        @if (in_array($numero, [161, 162, 163], true))
                                            <select class="entrada" id="campo-{{ $numero }}" name="{{ $nome }}" @if ($errors->has($chave)) aria-invalid="true" aria-describedby="erro-{{ $numero }}" @endif>
                                                <option value="">Não informado / não se aplica</option>
                                                @foreach ($linguas as $cod => $rotulo)
                                                    <option value="{{ $cod }}" @selected($valor($numero) === (string) $cod)>{{ $cod }} - {{ $rotulo }}</option>
                                                @endforeach
                                            </select>
                                        @elseif ($opcoes !== [])
                                            <select class="entrada" id="campo-{{ $numero }}" name="{{ $nome }}" @if ($errors->has($chave)) aria-invalid="true" aria-describedby="erro-{{ $numero }}" @endif>
                                                <option value="">Não informado / não se aplica</option>
                                                @foreach ($opcoes as $cod => $rotulo)
                                                    <option value="{{ $cod }}" @selected($valor($numero) === (string) $cod)>{{ $rotulo }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <input class="entrada" id="campo-{{ $numero }}" name="{{ $nome }}" type="{{ $codigo ? 'text' : 'number' }}" inputmode="numeric" value="{{ $valor($numero) }}" @if ($codigo) maxlength="{{ $campo->tamanho }}" @else min="1" max="9999" step="1" @endif @if ($errors->has($chave)) aria-invalid="true" aria-describedby="erro-{{ $numero }}" @endif>
                                        @endif
                                    @endif
                                    @if ($campo->obrigatorio === 'c')
                                        <small class="texto-ajuda">Somente quando se aplicar.</small>
                                    @endif
                                    @if ($errors->has($chave))
                                        <p class="texto-ajuda" id="erro-{{ $numero }}">{{ $errors->first($chave) }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>
            @endforeach
            <div class="formulario-acoes censo-secao">
                <p class="texto-ajuda">Salvar confirma todas as respostas para {{ $ano }}. O segundo fator é exigido e o ano anterior não é alterado.</p>
                <button class="botao" type="submit">Salvar infraestrutura no Censo</button>
            </div>
        </form>
    @endif

    <section class="folha censo-secao" aria-labelledby="titulo-conferencia">
        <div class="folha-cabecalho"><div><h2 id="titulo-conferencia">Conferência do registro 10</h2><p>Resultado do questionário já gravado, sem gerar arquivo.</p></div></div>
        <div class="folha-corpo">
            <p><strong>{{ ! $registro->aplicavel ? 'Não se aplica' : ($registro->pronto() ? 'Sem pendências impeditivas' : 'Questionário com pendências') }}</strong></p>
            @if ($registro->pendencias !== [])
                <ul class="censo-pendencias">
                    @foreach ($registro->pendencias as $pendencia)
                        <li>{{ $pendencia->recusa() ? 'Pendência' : 'Aviso' }}{{ $pendencia->campo ? ' (campo '.$pendencia->campo.')' : '' }}: {{ $pendencia->mensagem }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
@endsection
