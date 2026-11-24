@extends('layouts.municipal')

@php
    $parametros = ['municipio' => $municipality->slug, 'escola' => $school->id];
    $primeiro = $record === null;
    $filiacao = (string) old('filiacao', $record?->filiation ?? (($suggestions['mother'] || $suggestions['father']) ? 1 : ''));
    $pai1 = old('filiacao_1', $primeiro ? $suggestions['mother'] : $record->parent1_name);
    $pai2 = old('filiacao_2', $primeiro ? $suggestions['father'] : $record->parent2_name);
    $nascimento = $birthMunicipality ?? $suggestions['birth'];
    $nascimentoUf = old('nascimento_uf', $nascimento['uf'] ?? $student->birthplace_state);
    $nascimentoMunicipio = old('nascimento_municipio', $nascimento['nome'] ?? $student->birthplace_city);
    $residencia = $residenceMunicipality ?? $suggestions['residence'];
    $residenciaPais = (string) old('residencia_pais', $record?->residence_country_code ?? \App\Models\Tenant\StudentCensus::BRASIL);
    $residenciaUf = old('residencia_uf', $residencia['uf'] ?? $student->address_state);
    $residenciaMunicipio = old('residencia_municipio', $residencia['nome'] ?? $student->address_city);
    $localizacao = (string) old('localizacao_diferenciada', $record?->differentiated_location ?? ($primeiro ? 7 : ''));
    $nacionalidade = $student->nationality;
    $nacionalidades = \App\Http\Controllers\StudentRecordController::NATIONALITY_LABELS;
    $conferencia = function (?string $nome, ?string $uf) use ($tabelas): ?string {
        if (blank($nome)) {
            return null;
        }
        $codigo = $tabelas->municipioPorNome($nome, $uf);
        if ($codigo === null) {
            return 'Não está na tabela de municípios do Inep para esta UF. Confira a grafia; município fictício ou de outra UF não é aceito.';
        }
        $m = $tabelas->municipio($codigo);

        return 'Na tabela do Inep: '.$m['nome'].'/'.$m['uf'].', código '.$codigo.'.';
    };
@endphp

@section('title', 'Dados do Censo de '.$student->full_name)
@section('body-class', 'pagina-censo-turmas')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/censo-turmas.css') }}?v={{ filemtime(public_path('css/censo-turmas.css')) }}">
@endpush

@section('trilha')
    <a href="{{ route('schools.show', $parametros) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('school.students.show', [...$parametros, 'aluno' => $student->id]) }}">{{ $student->full_name }}</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Dados do Censo</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Censo Escolar</span>
            <h1>Dados do Censo de {{ $student->full_name }}</h1>
            <p>Dados que só o Educacenso pede. Municípios e países são conferidos nas tabelas oficiais do Inep.</p>
        </div>
        <a class="botao secundario" href="{{ route('school.students.show', [...$parametros, 'aluno' => $student->id]) }}">Voltar à ficha</a>
    </div>

    @if ($errors->any())
        <div class="erro" role="alert">
            <x-icon name="alerta" :size="18" />
            <div><strong>Revise o formulário</strong><ul>@foreach ($errors->all() as $mensagem)<li>{{ $mensagem }}</li>@endforeach</ul></div>
        </div>
    @endif

    @if ($primeiro)
        <div class="censo-sugestao" role="note">
            <x-icon name="info" :size="18" />
            <p>Primeiro preenchimento: o Astuti trouxe a filiação dos responsáveis cadastrados como mãe e pai e os municípios do cadastro. Confira com a certidão antes de salvar; nada foi gravado ainda.</p>
        </div>
    @endif

    <form method="POST" action="{{ route('school.students.census.update', [...$parametros, 'aluno' => $student->id]) }}" novalidate>
        @csrf
        @method('PUT')

        <section class="folha" aria-labelledby="titulo-filiacao">
            <div class="folha-cabecalho"><div><h2 id="titulo-filiacao">Filiação</h2><p>Como está na certidão de nascimento, não quem é o responsável hoje.</p></div></div>
            <div class="folha-corpo">
                <div class="formulario-grade">
                    <div class="formulario-campo">
                        <label class="rotulo" for="filiacao">Filiação *</label>
                        <select class="entrada" id="filiacao" name="filiacao" required>
                            <option value="">Escolha</option>
                            @foreach (\App\Models\Tenant\StudentCensus::FILIATION as $valor => $rotulo)
                                <option value="{{ $valor }}" @selected($filiacao === (string) $valor)>{{ $rotulo }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="formulario-campo">
                        <label class="rotulo" for="filiacao_1">Filiação 1 (de preferência, a mãe)</label>
                        <input class="entrada" id="filiacao_1" name="filiacao_1" maxlength="100" autocomplete="off" value="{{ $pai1 }}">
                    </div>
                    <div class="formulario-campo">
                        <label class="rotulo" for="filiacao_2">Filiação 2 (de preferência, o pai)</label>
                        <input class="entrada" id="filiacao_2" name="filiacao_2" maxlength="100" autocomplete="off" value="{{ $pai2 }}">
                    </div>
                </div>
                <p class="texto-ajuda">Com "Não declarada", os nomes não são gravados.</p>
            </div>
        </section>

        <section class="folha" aria-labelledby="titulo-nascimento">
            <div class="folha-cabecalho"><div><h2 id="titulo-nascimento">Nacionalidade e nascimento</h2><p>Nacionalidade no cadastro: {{ $nacionalidades[$nacionalidade ?? ''] ?? 'não informada' }}.</p></div></div>
            <div class="folha-corpo">
                @if ($nacionalidade === null)
                    <p class="texto-suave">Informe a nacionalidade no cadastro do aluno para completar esta parte.</p>
                @elseif ($nacionalidade === 'brasileira')
                    <div class="formulario-grade">
                        <div class="formulario-campo">
                            <label class="rotulo" for="nascimento_uf">UF de nascimento</label>
                            <select class="entrada" id="nascimento_uf" name="nascimento_uf">
                                <option value="">Escolha</option>
                                @foreach ($states as $sigla => $nome)
                                    <option value="{{ $sigla }}" @selected($nascimentoUf === $sigla)>{{ $nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="nascimento_municipio">Município de nascimento</label>
                            <input class="entrada" id="nascimento_municipio" name="nascimento_municipio" maxlength="120" autocomplete="off" value="{{ $nascimentoMunicipio }}" aria-describedby="nascimento-conferencia">
                            @if ($texto = $conferencia($nascimentoMunicipio, $nascimentoUf))
                                <p class="texto-ajuda" id="nascimento-conferencia">{{ $texto }}</p>
                            @endif
                        </div>
                    </div>
                @elseif ($nacionalidade === 'naturalizada')
                    <p class="texto-suave">País de nacionalidade: Brasil. O Censo não pede município de nascimento para quem nasceu no exterior.</p>
                @else
                    <div class="formulario-grade">
                        <div class="formulario-campo">
                            <label class="rotulo" for="pais_nacionalidade">País de nacionalidade</label>
                            <select class="entrada" id="pais_nacionalidade" name="pais_nacionalidade">
                                <option value="">Escolha</option>
                                @foreach ($countries as $codigo => $nome)
                                    @continue($codigo === \App\Models\Tenant\StudentCensus::BRASIL)
                                    <option value="{{ $codigo }}" @selected((string) old('pais_nacionalidade', $record?->nationality_country_code) === (string) $codigo)>{{ $nome }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @endif

                @if ($student->race_color === 'indigena')
                    <div class="formulario-grade">
                        <div class="formulario-campo">
                            <label class="rotulo" for="povo_indigena">Povo indígena</label>
                            <select class="entrada" id="povo_indigena" name="povo_indigena">
                                <option value="">Escolha</option>
                                @foreach ($peoples as $codigo => $nome)
                                    <option value="{{ $codigo }}" @selected((string) old('povo_indigena', $record?->indigenous_people_code) === (string) $codigo)>{{ $nome }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @endif
            </div>
        </section>

        <section class="folha" aria-labelledby="titulo-residencia">
            <div class="folha-cabecalho"><div><h2 id="titulo-residencia">Residência</h2><p>A zona (urbana ou rural) vem do endereço do aluno.</p></div></div>
            <div class="folha-corpo">
                <div class="formulario-grade">
                    <div class="formulario-campo">
                        <label class="rotulo" for="residencia_pais">País de residência</label>
                        <select class="entrada" id="residencia_pais" name="residencia_pais">
                            @foreach (\App\Models\Tenant\StudentCensus::RESIDENCE_COUNTRIES as $codigo => $nome)
                                <option value="{{ $codigo }}" @selected($residenciaPais === (string) $codigo)>{{ $nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="formulario-campo">
                        <label class="rotulo" for="residencia_uf">UF</label>
                        <select class="entrada" id="residencia_uf" name="residencia_uf">
                            <option value="">Escolha</option>
                            @foreach ($states as $sigla => $nome)
                                <option value="{{ $sigla }}" @selected($residenciaUf === $sigla)>{{ $nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="formulario-campo">
                        <label class="rotulo" for="residencia_municipio">Município de residência</label>
                        <input class="entrada" id="residencia_municipio" name="residencia_municipio" maxlength="120" autocomplete="off" value="{{ $residenciaMunicipio }}" aria-describedby="residencia-conferencia">
                        @if ($texto = $conferencia($residenciaMunicipio, $residenciaUf))
                            <p class="texto-ajuda" id="residencia-conferencia">{{ $texto }}</p>
                        @endif
                    </div>
                    <div class="formulario-campo">
                        <label class="rotulo" for="localizacao_diferenciada">Localização diferenciada</label>
                        <select class="entrada" id="localizacao_diferenciada" name="localizacao_diferenciada">
                            <option value="">Escolha</option>
                            @foreach (\App\Models\Tenant\StudentCensus::DIFFERENTIATED_LOCATIONS as $valor => $rotulo)
                                <option value="{{ $valor }}" @selected($localizacao === (string) $valor)>{{ $rotulo }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <p class="texto-ajuda">Fora do Brasil, município e localização não vão para o Censo.</p>
            </div>
        </section>

        <div class="formulario-acoes censo-secao">
            <p class="texto-ajuda">Gravar pede o código do segundo fator. A auditoria registra quais campos mudaram, nunca o conteúdo.</p>
            <button class="botao" type="submit">Salvar dados do Censo</button>
        </div>
    </form>
@endsection
